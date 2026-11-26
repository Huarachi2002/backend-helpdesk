<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Ejecución diaria a las 08:00 para enviar recordatorios de tickets pendientes
        $schedule->command('tickets:enviar-recordatorios')->dailyAt('08:00');

        // Cada minuto: registra en los tickets las respuestas recibidas por correo.
        $schedule->command('tickets:procesar-correos')
            ->everyMinute()
            ->withoutOverlapping(10)
            ->when(fn () => config('helpdesk_mail.inbound.driver', 'none') !== 'none');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
