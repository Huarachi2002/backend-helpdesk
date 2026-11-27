<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    protected $primaryKey = 'clave';
    public $incrementing = false;
    protected $keyType = 'string';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'clave',
        'usuario_ci',
        'valor',
        'tipo',
        'grupo',
        'descripcion',
    ];

    protected $casts = [
        'fecha_creacion'      => 'datetime',
        'fecha_actualizacion' => 'datetime',
    ];

    protected $appends = ['id'];

    public function getIdAttribute()
    {
        return $this->attributes['clave'] ?? null;
    }

    /**
     * Usuario Administrador (Director) responsable de la configuración.
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_ci', 'ci');
    }

    /**
     * Obtiene el valor casteado de una configuración según su tipo.
     */
    public function getParsedValueAttribute()
    {
        return self::castValue($this->valor, $this->tipo);
    }

    /**
     * Helper estático para obtener el valor de una configuración.
     */
    public static function get(string $key, $default = null)
    {
        $config = self::where('clave', $key)->first();
        if (!$config) {
            return $default;
        }

        return self::castValue($config->valor, $config->tipo);
    }

    /**
     * Helper estático para guardar o actualizar una configuración.
     */
    public static function set(string $key, $value, ?string $tipo = null, ?string $grupo = null, ?string $descripcion = null): self
    {
        $config = self::firstOrNew(['clave' => $key]);

        if ($tipo) {
            $config->tipo = $tipo;
        } elseif (!$config->exists) {
            $config->tipo = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_array($value) ? 'json' : 'string'));
        }

        if ($grupo) {
            $config->grupo = $grupo;
        }

        if ($descripcion) {
            $config->descripcion = $descripcion;
        }

        if (is_bool($value)) {
            $config->valor = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $config->valor = json_encode($value);
        } else {
            $config->valor = (string) $value;
        }

        $config->save();
        return $config;
    }

    /**
     * Verifica si una notificación está permitida globalmente para un evento y canal específicos.
     */
    public static function allowsNotification(string $eventType, string $channel = 'email'): bool
    {
        if ($channel === 'email' || $channel === 'correo') {
            if (!self::get('notificaciones_email_activas', true)) {
                return false;
            }
        } elseif ($channel === 'in_app' || $channel === 'inapp') {
            if (!self::get('notificaciones_inapp_activas', true)) {
                return false;
            }
        }

        $eventMap = [
            'TICKET_CREADO'              => 'ticket_creado',
            'CAMBIO_ESTADO'              => 'cambio_estado',
            'SOLICITUD_REASIGNACION'     => 'solicitud_reasignacion',
            'REASIGNACION'               => 'reasignacion',
            'REASIGNACION_APROBADA'      => 'reasignacion',
            'REASIGNACION_RECHAZADA'     => 'reasignacion',
            'RECORDATORIO_PENDIENTE'     => 'recordatorio',
            'TICKET_SIN_RESPUESTA'       => 'sin_respuesta',
            'RESPUESTA_TICKET'           => 'respuesta_ticket',
            'TICKET_RELEVADO'            => 'relevamiento',
            'TICKET_DEVUELTO'            => 'relevamiento',
        ];

        $normalizedChannel = ($channel === 'in_app' || $channel === 'inapp') ? 'inapp' : 'email';
        $normalizedEvent = $eventMap[$eventType] ?? strtolower($eventType);
        $configKey = "notif_evento_{$normalizedEvent}_{$normalizedChannel}";

        return (bool) self::get($configKey, true);
    }

    private static function castValue($value, string $tipo)
    {
        switch ($tipo) {
            case 'integer':
            case 'int':
                return (int) $value;
            case 'boolean':
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
            case 'array':
                return json_decode($value, true) ?: [];
            default:
                return (string) $value;
        }
    }
}
