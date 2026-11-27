<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestión de la Estructura Organizacional (organigrama DEDTE):
 * CRUD de cargos y asignación de personal a cada cargo.
 */
class CargoController extends Controller
{
    /**
     * Listar todos los cargos con sus usuarios (lectura para Administradores y Técnicos).
     */
    public function index()
    {
        $cargos = Cargo::with(['superior', 'usuarios.rol'])
            ->withCount('subordinados')
            ->orderBy('nivel')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'data' => $cargos->map(fn (Cargo $c) => $c->toApiArray())->values(),
        ]);
    }

    /**
     * Crear un nuevo cargo en la estructura.
     */
    public function store(Request $request)
    {
        $validated = $this->validateCargo($request);

        $nivel = $this->resolverNivel($validated['cargo_superior_codigo'] ?? null);
        if (is_array($nivel)) {
            return response()->json($nivel, 422);
        }

        $cargo = Cargo::create([
            'codigo'                => strtoupper(trim($validated['codigo'])),
            'nombre'                => trim($validated['nombre']),
            'seccion'               => trim($validated['seccion']),
            'cargo_superior_codigo' => $validated['cargo_superior_codigo'] ?? null,
            'dependencia_externa'   => $validated['dependencia_externa'] ?? null,
            'nivel'                 => $nivel,
            'orden'                 => $validated['orden'] ?? 0,
            'activo'                => $request->boolean('activo', true),
        ]);

        return response()->json([
            'message' => 'Cargo creado exitosamente. Recuerda asignarle al menos un usuario del personal.',
            'data'    => $this->fresh($cargo->codigo),
        ], 201);
    }

    /**
     * Actualizar un cargo (incluye cambio de código y de cargo superior).
     */
    public function update(Request $request, $codigo)
    {
        $cargo = Cargo::findOrFail($codigo);
        $validated = $this->validateCargo($request, $cargo);

        $nuevoCodigo = strtoupper(trim($validated['codigo']));
        $nuevoSuperior = $validated['cargo_superior_codigo'] ?? null;

        if (Cargo::generaCiclo($cargo->codigo, $nuevoSuperior)) {
            return response()->json([
                'message' => 'Un cargo no puede depender de sí mismo ni de uno de sus subordinados.',
                'error'   => 'cycle_detected',
            ], 422);
        }

        $nivel = $this->resolverNivel($nuevoSuperior);
        if (is_array($nivel)) {
            return response()->json($nivel, 422);
        }

        DB::transaction(function () use ($cargo, $validated, $nuevoCodigo, $nuevoSuperior, $nivel, $request) {
            // El cambio de código se propaga a usuarios y subordinados por ON UPDATE CASCADE.
            $cargo->codigo                = $nuevoCodigo;
            $cargo->nombre                = trim($validated['nombre']);
            $cargo->seccion               = trim($validated['seccion']);
            $cargo->cargo_superior_codigo = $nuevoSuperior;
            $cargo->dependencia_externa   = $validated['dependencia_externa'] ?? null;
            $cargo->nivel                 = $nivel;
            $cargo->orden                 = $validated['orden'] ?? $cargo->orden;
            $cargo->save();

            $cargo->recalcularNivelesSubordinados();
        });

        return response()->json([
            'message' => 'Cargo actualizado exitosamente.',
            'data'    => $this->fresh($nuevoCodigo),
        ]);
    }

    /**
     * Activar / desactivar un cargo.
     */
    public function toggleActive($codigo)
    {
        $cargo = Cargo::findOrFail($codigo);

        if ($cargo->activo) {
            if ($cargo->subordinados()->where('activo', true)->exists()) {
                return response()->json([
                    'message' => 'No se puede desactivar un cargo que tiene cargos subordinados activos.',
                    'error'   => 'has_active_subordinates',
                ], 422);
            }
            if ($cargo->usuarios()->where('activo', true)->exists()) {
                return response()->json([
                    'message' => 'No se puede desactivar un cargo que tiene personal activo asignado. Reubica primero a su personal.',
                    'error'   => 'has_active_users',
                ], 422);
            }
        } elseif ($cargo->superior && !$cargo->superior->activo) {
            return response()->json([
                'message' => "No se puede activar el cargo porque su cargo superior ({$cargo->superior->nombre}) está inactivo.",
                'error'   => 'inactive_superior',
            ], 422);
        }

        $cargo->activo = !$cargo->activo;
        $cargo->save();

        return response()->json([
            'message' => $cargo->activo ? 'Cargo activado.' : 'Cargo desactivado.',
            'data'    => $this->fresh($cargo->codigo),
        ]);
    }

    /**
     * Eliminar un cargo sin personal ni subordinados.
     */
    public function destroy($codigo)
    {
        $cargo = Cargo::findOrFail($codigo);

        if ($cargo->subordinados()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un cargo que tiene cargos subordinados.',
                'error'   => 'has_subordinates',
            ], 422);
        }

        if ($cargo->usuarios()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un cargo con personal asignado. Reubica primero a su personal.',
                'error'   => 'has_users',
            ], 422);
        }

        $cargo->delete();

        return response()->json(['message' => 'Cargo eliminado correctamente.']);
    }

    /**
     * Listar el personal (Directores y Técnicos) con su cargo actual.
     */
    public function personal()
    {
        $users = User::with(['rol', 'cargo'])
            ->whereHas('rol', fn ($q) => $q->whereIn('nombre', User::STAFF_ROLES))
            ->orderBy('nombre')
            ->get()
            ->map(fn (User $u) => $this->personalArray($u));

        return response()->json(['data' => $users]);
    }

    /**
     * Asignar (o mover) a un usuario del personal a un cargo.
     */
    public function assignPersonal(Request $request, $id)
    {
        $validated = $request->validate([
            'cargo_codigo' => 'required|string|exists:cargos,codigo',
        ], [
            'cargo_codigo.required' => 'Debes seleccionar un cargo.',
            'cargo_codigo.exists'   => 'El cargo seleccionado no existe.',
        ]);

        $user = User::with(['rol', 'cargo'])->findOrFail($id);

        if (!$user->isStaff()) {
            return response()->json([
                'message' => 'Solo el personal (Director o Técnico) puede ocupar un cargo en la estructura.',
                'error'   => 'not_staff',
            ], 422);
        }

        $destino = Cargo::findOrFail($validated['cargo_codigo']);
        if (!$destino->activo) {
            return response()->json([
                'message' => "El cargo «{$destino->nombre}» está inactivo.",
                'error'   => 'inactive_cargo',
            ], 422);
        }

        if ($user->cargo_codigo !== $destino->codigo) {
            $vacante = Cargo::quedariaVacanteSiSale($user);
            if ($vacante) {
                return response()->json([
                    'message' => "{$user->name} es el único titular activo de «{$vacante->nombre}». Asigna primero a otra persona a ese cargo para no dejarlo vacante.",
                    'error'   => 'cargo_would_be_vacant',
                ], 422);
            }

            $user->cargo_codigo = $destino->codigo;
            $user->save();
        }

        return response()->json([
            'message' => "{$user->name} fue asignado/a al cargo «{$destino->nombre}».",
            'data'    => $this->personalArray($user->fresh(['rol', 'cargo'])),
        ]);
    }

    // --- Helpers ---

    private function validateCargo(Request $request, ?Cargo $cargo = null): array
    {
        return $request->validate([
            'codigo'                => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-_.]+$/',
                Rule::unique('cargos', 'codigo')->ignore($cargo?->codigo, 'codigo'),
            ],
            'nombre'                => 'required|string|max:150',
            'seccion'               => 'required|string|max:80',
            'cargo_superior_codigo' => 'nullable|string|exists:cargos,codigo',
            'dependencia_externa'   => 'nullable|string|max:150',
            'orden'                 => 'nullable|integer|min:0|max:999',
            'activo'                => 'nullable|boolean',
        ], [
            'codigo.required'              => 'El código del cargo es obligatorio.',
            'codigo.unique'                => 'Ya existe un cargo con este código.',
            'codigo.regex'                 => 'El código solo puede contener letras, números y los caracteres - _ .',
            'nombre.required'              => 'El nombre del cargo es obligatorio.',
            'seccion.required'             => 'La sección funcional es obligatoria.',
            'cargo_superior_codigo.exists' => 'El cargo superior seleccionado no existe.',
        ]);
    }

    /**
     * Calcula el nivel de un cargo a partir de su superior. Devuelve un arreglo de error si no es válido.
     */
    private function resolverNivel(?string $superiorCodigo)
    {
        if (!$superiorCodigo) {
            return 1;
        }

        $superior = Cargo::find($superiorCodigo);
        if (!$superior->activo) {
            return [
                'message' => "El cargo superior «{$superior->nombre}» está inactivo.",
                'error'   => 'inactive_superior',
            ];
        }

        return $superior->nivel + 1;
    }

    private function fresh(string $codigo): array
    {
        return Cargo::with(['superior', 'usuarios.rol'])
            ->withCount('subordinados')
            ->findOrFail($codigo)
            ->toApiArray();
    }

    private function personalArray(User $u): array
    {
        return [
            'id'           => $u->ci,
            'ci'           => $u->ci,
            'name'         => $u->name,
            'email'        => $u->email,
            'role'         => $u->role,
            'activo'       => (bool) $u->activo,
            'cargo_codigo' => $u->cargo_codigo,
            'cargo'        => $u->cargo ? [
                'codigo'  => $u->cargo->codigo,
                'nombre'  => $u->cargo->nombre,
                'seccion' => $u->cargo->seccion,
                'nivel'   => $u->cargo->nivel,
            ] : null,
        ];
    }
}
