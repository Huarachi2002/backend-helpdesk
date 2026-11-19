<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ConfigurationController extends Controller
{
    /**
     * Lista todas las configuraciones del sistema agrupadas o filtradas.
     */
    public function index(Request $request)
    {
        $grupo = $request->get('grupo', 'notificaciones');

        $configs = Configuracion::when($grupo !== 'all', function ($q) use ($grupo) {
            $q->where('grupo', $grupo);
        })->get();

        return response()->json([
            'data' => $configs
        ]);
    }

    /**
     * Actualiza un lote de configuraciones del sistema (Exclusivo Administrador).
     */
    public function update(Request $request)
    {
        $request->validate([
            'configurations'         => 'required|array',
            'configurations.*.clave' => 'required|string',
            'configurations.*.valor' => 'present',
        ]);

        foreach ($request->configurations as $item) {
            $config = Configuracion::where('clave', $item['clave'])->first();
            if ($config) {
                $val = $item['valor'];
                if ($config->tipo === 'boolean') {
                    $config->valor = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
                } elseif ($config->tipo === 'json') {
                    $config->valor = is_array($val) ? json_encode($val) : (string) $val;
                } else {
                    $config->valor = (string) $val;
                }
                $config->save();
            }
        }

        return response()->json([
            'message' => 'Configuraciones actualizadas exitosamente.',
            'data'    => Configuracion::where('grupo', 'notificaciones')->get()
        ]);
    }

    /**
     * Ejecuta manualmente el comando de recordatorios desde el panel de control.
     */
    public function triggerReminders()
    {
        Artisan::call('tickets:enviar-recordatorios');
        $output = Artisan::output();

        return response()->json([
            'message' => 'Proceso de recordatorios ejecutado exitosamente.',
            'output'  => $output
        ]);
    }
}
