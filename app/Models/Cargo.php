<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cargo del organigrama institucional (DEDTE).
 * Árbol auto-referenciado sin límite de niveles, con PK natural = código institucional.
 */
class Cargo extends Model
{
    use HasFactory;

    protected $table = 'cargos';

    protected $primaryKey = 'codigo';
    public $incrementing = false;
    protected $keyType = 'string';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'codigo',
        'nombre',
        'seccion',
        'cargo_superior_codigo',
        'dependencia_externa',
        'nivel',
        'orden',
        'activo',
    ];

    protected $casts = [
        'nivel'  => 'integer',
        'orden'  => 'integer',
        'activo' => 'boolean',
    ];

    // --- Relaciones ---

    public function superior()
    {
        return $this->belongsTo(Cargo::class, 'cargo_superior_codigo', 'codigo');
    }

    public function subordinados()
    {
        return $this->hasMany(Cargo::class, 'cargo_superior_codigo', 'codigo')->orderBy('orden');
    }

    public function usuarios()
    {
        return $this->hasMany(User::class, 'cargo_codigo', 'codigo');
    }

    // --- Reglas de la estructura ---

    /**
     * Indica si asignar $nuevoSuperior como superior de $codigo generaría un ciclo
     * (el nuevo superior es el mismo cargo o uno de sus subordinados).
     */
    public static function generaCiclo(string $codigo, ?string $nuevoSuperior): bool
    {
        $actual = $nuevoSuperior;
        $visitados = [];

        while ($actual !== null) {
            if ($actual === $codigo || isset($visitados[$actual])) {
                return true;
            }
            $visitados[$actual] = true;
            $actual = static::where('codigo', $actual)->value('cargo_superior_codigo');
        }

        return false;
    }

    /**
     * Códigos de los cargos superiores en la cadena (del inmediato hacia la raíz).
     */
    public function codigosSuperiores(): array
    {
        $codigos = [];
        $actual = $this->cargo_superior_codigo;
        while ($actual !== null && !in_array($actual, $codigos, true)) {
            $codigos[] = $actual;
            $actual = static::where('codigo', $actual)->value('cargo_superior_codigo');
        }
        return $codigos;
    }

    /**
     * Códigos de todos los cargos subordinados (directos e indirectos).
     */
    public function codigosSubordinados(): array
    {
        $codigos = [];
        $pendientes = [$this->codigo];
        while ($pendientes) {
            $hijos = static::whereIn('cargo_superior_codigo', $pendientes)->pluck('codigo')->all();
            $hijos = array_values(array_diff($hijos, $codigos));
            $codigos = array_merge($codigos, $hijos);
            $pendientes = $hijos;
        }
        return $codigos;
    }

    /**
     * Recalcula el nivel de todos los subordinados (recursivo) a partir del nivel de este cargo.
     */
    public function recalcularNivelesSubordinados(): void
    {
        foreach ($this->subordinados()->get() as $hijo) {
            $hijo->nivel = $this->nivel + 1;
            $hijo->save();
            $hijo->recalcularNivelesSubordinados();
        }
    }

    /**
     * Devuelve el cargo que quedaría sin ningún usuario activo si $user dejara de ocuparlo
     * (por cambio de cargo, desactivación, cambio de rol o eliminación). Null si no aplica.
     */
    public static function quedariaVacanteSiSale(User $user): ?Cargo
    {
        if (!$user->cargo_codigo || !$user->activo) {
            return null;
        }

        $otrosActivos = User::where('cargo_codigo', $user->cargo_codigo)
            ->where('ci', '!=', $user->ci)
            ->where('activo', true)
            ->count();

        if ($otrosActivos > 0) {
            return null;
        }

        return static::find($user->cargo_codigo);
    }

    /**
     * Representación JSON uniforme para la API.
     */
    public function toApiArray(): array
    {
        $usuarios = $this->relationLoaded('usuarios') ? $this->usuarios : collect();
        $activos = $usuarios->where('activo', true);

        return [
            'codigo'                => $this->codigo,
            'nombre'                => $this->nombre,
            'seccion'               => $this->seccion,
            'cargo_superior_codigo' => $this->cargo_superior_codigo,
            'cargo_superior_nombre' => $this->relationLoaded('superior') ? $this->superior?->nombre : null,
            'dependencia_externa'   => $this->dependencia_externa,
            'nivel'                 => $this->nivel,
            'orden'                 => $this->orden,
            'activo'                => (bool) $this->activo,
            'subordinados_count'    => $this->subordinados_count ?? null,
            'usuarios_count'        => $usuarios->count(),
            'usuarios_activos_count'=> $activos->count(),
            'vacante'               => $this->activo && $activos->isEmpty(),
            'usuarios'              => $usuarios->map(fn ($u) => [
                'id'     => $u->ci,
                'ci'     => $u->ci,
                'name'   => $u->name,
                'email'  => $u->email,
                'role'   => $u->role,
                'activo' => (bool) $u->activo,
            ])->values(),
            'fecha_creacion'        => $this->fecha_creacion?->toDateTimeString(),
        ];
    }
}
