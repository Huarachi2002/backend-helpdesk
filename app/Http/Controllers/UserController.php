<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Rol;
use App\Models\TicketPrioridad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Listar usuarios con filtros y relaciones.
     */
    public function index(Request $request)
    {
        $query = User::query()->with(['rol', 'categorias', 'prioridades', 'cargo'])
            ->withCount([
                'assignedTickets' => function ($q) {
                    $q->whereNotIn('estado', ['RESUELTO', 'CERRADO']);
                },
                'createdTickets'
            ]);

        // Filtro por búsqueda de CI, nombre o correo
        $query->when($request->search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('ci', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('apellido', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%");
            });
        });

        // Filtro por Rol
        $query->when($request->role, function ($q, $role) {
            $val = strtoupper($role);
            if ($val === 'ADMIN') $val = 'DIRECTOR';
            if ($val === 'SUPPORT') $val = 'TECNICO';
            if ($val === 'TEACHER') $val = 'DOCENTE';
            $q->whereHas('rol', fn($rQ) => $rQ->where('nombre', $val));
        });

        // Filtro por Disponibilidad activa
        if ($request->has('is_active') && $request->is_active !== '') {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->where('activo', $isActive);
        }

        $sortField = $request->get('sort_by', 'fecha_creacion');
        if ($sortField === 'created_at') $sortField = 'fecha_creacion';
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        return response()->json($query->paginate(15));
    }

    /**
     * Ver detalle de un usuario.
     */
    public function show($id)
    {
        $user = User::with(['rol', 'categorias', 'prioridades', 'cargo'])
            ->withCount([
                'assignedTickets' => function ($q) {
                    $q->whereNotIn('estado', ['RESUELTO', 'CERRADO']);
                },
                'createdTickets'
            ])
            ->findOrFail($id);

        return response()->json(['data' => $user]);
    }

    /**
     * Crear nuevo usuario y configuración de roles/categorías.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ci'                 => 'required|string|max:30|unique:usuarios,ci',
            'name'               => 'required_without:nombre|string|max:255',
            'nombre'             => 'required_without:name|string|max:100',
            'apellido'           => 'nullable|string|max:100',
            'email'              => 'required_without:correo|email|max:150|unique:usuarios,correo',
            'correo'             => 'required_without:email|email|max:150|unique:usuarios,correo',
            'password'           => 'required|string|min:6',
            'role'               => 'nullable|string',
            'rol_id'             => 'nullable|exists:roles,id',
            'is_active'          => 'nullable|boolean',
            'sesion'             => 'nullable|boolean',
            'moodle_user_id'     => 'nullable|integer',
            'moodle_usuario_id'  => 'nullable|integer',
            'allowed_priorities' => 'nullable|array',
            'category_ids'       => 'nullable|array',
            'category_ids.*'     => 'integer|exists:categorias,id',
            'cargo_codigo'       => 'nullable|string|exists:cargos,codigo',
        ], [
            'ci.required'           => 'El CI del usuario es obligatorio.',
            'ci.unique'             => 'Ya existe un usuario registrado con este CI.',
            'email.unique'          => 'El correo electrónico ya está registrado.',
            'correo.unique'         => 'El correo electrónico ya está registrado.',
            'category_ids.*.exists' => 'Una de las categorías seleccionadas no es válida.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $nombre = $validated['nombre'] ?? explode(' ', trim($validated['name'] ?? ''), 2)[0];
            $apellido = $validated['apellido'] ?? (explode(' ', trim($validated['name'] ?? ''), 2)[1] ?? null);
            $correo = $validated['correo'] ?? $validated['email'];

            // Determinar rol
            $rolId = $validated['rol_id'] ?? null;
            if (!$rolId) {
                $roleCode = strtoupper($validated['role'] ?? 'ESTUDIANTE');
                if ($roleCode === 'ADMIN') $roleCode = 'DIRECTOR';
                if ($roleCode === 'SUPPORT') $roleCode = 'TECNICO';
                if ($roleCode === 'TEACHER') $roleCode = 'DOCENTE';

                $rol = Rol::where('nombre', $roleCode)->first();
                $rolId = $rol ? $rol->id : 4; // 4 = ESTUDIANTE
            }

            // Cargo en la estructura organizacional: obligatorio para el personal
            $cargoCodigo = null;
            if ($this->isStaffRolId($rolId)) {
                $cargoCodigo = $validated['cargo_codigo'] ?? null;
                if ($error = $this->validarCargoDestino($cargoCodigo)) {
                    return $error;
                }
            }

            $user = User::create([
                'ci'                => trim($validated['ci']),
                'rol_id'            => $rolId,
                'cargo_codigo'      => $cargoCodigo,
                'nombre'            => $nombre,
                'apellido'          => $apellido,
                'correo'            => $correo,
                'contrasenia'       => Hash::make($validated['password']),
                'sesion'            => $request->has('sesion') ? $request->boolean('sesion') : in_array($rolId, [1, 2]),
                'moodle_usuario_id' => $request->input('moodle_usuario_id', $request->input('moodle_user_id')),
                'activo'            => $request->boolean('is_active', true),
            ]);

            // Asignar categorías en usuario_categoria
            if (!empty($validated['category_ids'])) {
                $user->categorias()->sync($validated['category_ids']);
            }

            // Asignar prioridades en usuario_prioridad (PK: codigo)
            if (!empty($validated['allowed_priorities'])) {
                $priorityCodigos = TicketPrioridad::whereIn('codigo', array_map('strtoupper', $validated['allowed_priorities']))
                    ->orWhereIn(DB::raw('LOWER(codigo)'), array_map('strtolower', $validated['allowed_priorities']))
                    ->pluck('codigo')
                    ->toArray();
                $user->prioridades()->sync($priorityCodigos);
            }

            $user->load(['rol', 'categorias', 'prioridades', 'cargo']);

            return response()->json([
                'message' => 'Usuario creado exitosamente.',
                'data'    => $user,
            ], 201);
        });
    }

    /**
     * Actualizar usuario y configuración de roles/especialidad.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'ci'                 => 'nullable|string|max:30|unique:usuarios,ci,' . $user->ci . ',ci',
            'name'               => 'nullable|string|max:255',
            'nombre'             => 'nullable|string|max:100',
            'apellido'           => 'nullable|string|max:100',
            'email'              => 'nullable|email|max:150|unique:usuarios,correo,' . $user->ci . ',ci',
            'correo'             => 'nullable|email|max:150|unique:usuarios,correo,' . $user->ci . ',ci',
            'password'           => 'nullable|string|min:6',
            'role'               => 'nullable|string',
            'rol_id'             => 'nullable|exists:roles,id',
            'is_active'          => 'nullable|boolean',
            'sesion'             => 'nullable|boolean',
            'moodle_user_id'     => 'nullable|integer',
            'moodle_usuario_id'  => 'nullable|integer',
            'allowed_priorities' => 'nullable|array',
            'category_ids'       => 'nullable|array',
            'category_ids.*'     => 'integer|exists:categorias,id',
            'cargo_codigo'       => 'nullable|string|exists:cargos,codigo',
        ]);

        return DB::transaction(function () use ($user, $validated, $request) {
            $updateData = [];

            if (!empty($validated['ci']) && trim($validated['ci']) !== $user->ci) {
                $oldCi = $user->ci;
                $newCi = trim($validated['ci']);
                $updateData['ci'] = $newCi;
                // Mantener sincronizados tokens Sanctum si se cambia el CI
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->where('tokenable_id', $oldCi)
                    ->update(['tokenable_id' => $newCi]);
            }

            if (isset($validated['nombre'])) {
                $updateData['nombre'] = $validated['nombre'];
            } elseif (isset($validated['name'])) {
                $parts = explode(' ', trim($validated['name']), 2);
                $updateData['nombre'] = $parts[0];
                $updateData['apellido'] = $parts[1] ?? null;
            }

            if (isset($validated['apellido'])) {
                $updateData['apellido'] = $validated['apellido'];
            }

            if (isset($validated['correo']) || isset($validated['email'])) {
                $updateData['correo'] = $validated['correo'] ?? $validated['email'];
            }

            if (!empty($validated['password'])) {
                $updateData['contrasenia'] = Hash::make($validated['password']);
            }

            if (isset($validated['rol_id'])) {
                $updateData['rol_id'] = $validated['rol_id'];
            } elseif (isset($validated['role'])) {
                $roleCode = strtoupper($validated['role']);
                if ($roleCode === 'ADMIN') $roleCode = 'DIRECTOR';
                if ($roleCode === 'SUPPORT') $roleCode = 'TECNICO';
                if ($roleCode === 'TEACHER') $roleCode = 'DOCENTE';

                $rol = Rol::where('nombre', $roleCode)->first();
                if ($rol) {
                    $updateData['rol_id'] = $rol->id;
                }
            }

            if ($request->has('is_active')) {
                $updateData['activo'] = $request->boolean('is_active');
            }

            if ($request->has('sesion')) {
                $updateData['sesion'] = $request->boolean('sesion');
            }

            if ($request->has('moodle_usuario_id') || $request->has('moodle_user_id')) {
                $updateData['moodle_usuario_id'] = $request->input('moodle_usuario_id', $request->input('moodle_user_id'));
            }

            // Cargo en la estructura organizacional
            $finalRolId = $updateData['rol_id'] ?? $user->rol_id;
            $finalActivo = $updateData['activo'] ?? $user->activo;
            $finalCargo = null;
            if ($this->isStaffRolId($finalRolId)) {
                $finalCargo = $request->has('cargo_codigo') ? ($validated['cargo_codigo'] ?? null) : $user->cargo_codigo;
                if ($finalCargo !== $user->cargo_codigo || !$user->cargo_codigo) {
                    if ($error = $this->validarCargoDestino($finalCargo)) {
                        return $error;
                    }
                }
            }

            if ($finalCargo !== $user->cargo_codigo || !$finalActivo) {
                if ($error = $this->validarNoQuedaVacante($user)) {
                    return $error;
                }
            }
            $updateData['cargo_codigo'] = $finalCargo;

            $user->update($updateData);

            // Sincronizar categorías
            if (isset($validated['category_ids'])) {
                $user->categorias()->sync($validated['category_ids']);
            }

            // Sincronizar prioridades (PK: codigo)
            if (isset($validated['allowed_priorities'])) {
                $priorityCodigos = TicketPrioridad::whereIn('codigo', array_map('strtoupper', $validated['allowed_priorities']))
                    ->orWhereIn(DB::raw('LOWER(codigo)'), array_map('strtolower', $validated['allowed_priorities']))
                    ->pluck('codigo')
                    ->toArray();
                $user->prioridades()->sync($priorityCodigos);
            }

            $user->load(['rol', 'categorias', 'prioridades', 'cargo']);

            return response()->json([
                'message' => 'Usuario y configuración actualizados exitosamente.',
                'data'    => $user,
            ]);
        });
    }

    /**
     * Alternar disponibilidad activa del técnico.
     */
    public function toggleActive(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->activo && ($error = $this->validarNoQuedaVacante($user))) {
            return $error;
        }

        $user->activo = !$user->activo;
        $user->save();

        return response()->json([
            'message'   => 'Disponibilidad del usuario actualizada.',
            'is_active' => $user->activo,
            'data'      => $user->load(['rol', 'categorias', 'prioridades', 'cargo']),
        ]);
    }

    /**
     * Eliminar usuario.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propia cuenta en sesión.',
                'error'   => 'cannot_delete_self'
            ], 422);
        }

        $activeAssigned = $user->assignedTickets()
            ->whereNotIn('estado', ['RESUELTO', 'CERRADO'])
            ->count();

        if ($activeAssigned > 0) {
            return response()->json([
                'message' => "El usuario tiene {$activeAssigned} ticket(s) activo(s) asignado(s). Reasigna los tickets antes de eliminarlo.",
                'error'   => 'has_active_tickets'
            ], 422);
        }

        if ($error = $this->validarNoQuedaVacante($user)) {
            return $error;
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.'
        ]);
    }

    // --- Reglas de la Estructura Organizacional ---

    private function isStaffRolId($rolId): bool
    {
        $nombre = strtoupper(Rol::where('id', $rolId)->value('nombre') ?? '');
        return in_array($nombre, User::STAFF_ROLES, true);
    }

    /**
     * El personal debe ocupar un cargo activo de la estructura.
     */
    private function validarCargoDestino(?string $cargoCodigo)
    {
        if (!$cargoCodigo) {
            return response()->json([
                'message' => 'El cargo en la estructura organizacional es obligatorio para Directores y Técnicos.',
                'errors'  => ['cargo_codigo' => ['Debes seleccionar el cargo que ocupa el usuario.']],
                'error'   => 'cargo_required',
            ], 422);
        }

        $cargo = Cargo::find($cargoCodigo);
        if (!$cargo || !$cargo->activo) {
            return response()->json([
                'message' => 'El cargo seleccionado está inactivo.',
                'errors'  => ['cargo_codigo' => ['El cargo seleccionado está inactivo.']],
                'error'   => 'inactive_cargo',
            ], 422);
        }

        return null;
    }

    /**
     * Ningún cargo puede quedar sin al menos un usuario activo.
     */
    private function validarNoQuedaVacante(User $user)
    {
        $vacante = Cargo::quedariaVacanteSiSale($user);
        if (!$vacante) {
            return null;
        }

        return response()->json([
            'message' => "{$user->name} es el único titular activo del cargo «{$vacante->nombre}». Asigna primero a otra persona a ese cargo para no dejarlo vacante.",
            'error'   => 'cargo_would_be_vacant',
        ], 422);
    }
}
