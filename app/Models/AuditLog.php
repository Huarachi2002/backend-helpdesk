<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'bitacora_accesos';
    public $timestamps = false;
    protected $fillable = [
        'usuario_id', 'tipo_evento', 'ip', 'recurso',
        'metodo', 'descripcion', 'user_agent', 'correo_intento',
    ];
    protected $casts = ['created_at' => 'datetime'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public static function registrar(string $evento, ?int $userId = null, array $extra = []): void
    {
        try {
            static::create([
                'usuario_id'      => $userId,
                'tipo_evento'     => $evento,
                'ip'              => request()->ip(),
                'recurso'         => request()->path(),
                'metodo'          => request()->method(),
                'user_agent'      => request()->userAgent(),
                'descripcion'     => $extra['descripcion'] ?? null,
                'correo_intento'  => $extra['correo_intento'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // No interrumpir el flujo si falla el log
        }
    }
}
