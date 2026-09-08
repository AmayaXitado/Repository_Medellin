<?php

use App\Enums\AccionAuditoria;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Services\Auditor;
use App\Services\ContextoDependencia;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Enlaces de carga
|--------------------------------------------------------------------------
| Atajo de consola. La forma normal de generarlos es el botón «Enlace de
| carga» que hay dentro de cada carpeta del explorador.
*/

Artisan::command(
    'enlace:crear {carpeta : Nombre de la carpeta de destino}
                  {--dependencia= : Slug de la dependencia (por defecto, la primera activa)}
                  {--proposito= : Para qué es el enlace}
                  {--dias= : Días hasta el vencimiento}
                  {--usos= : Número máximo de envíos}',
    function (Auditor $auditor, ContextoDependencia $contexto) {
        $dependencia = $this->option('dependencia')
            ? Dependencia::where('slug', $this->option('dependencia'))->first()
            : Dependencia::where('activa', true)->orderBy('id')->first();

        if ($dependencia === null) {
            $this->error('No encontré esa dependencia. Revisa el slug con --dependencia.');

            return 1;
        }

        // El contexto hace falta para la auditoría y para el global scope:
        // en consola no hay middleware que lo llene.
        $contexto->establecer($dependencia);

        $carpeta = Carpeta::where('dependencia_id', $dependencia->id)
            ->where('nombre', $this->argument('carpeta'))
            ->first();

        if ($carpeta === null) {
            $this->error('No encontré esa carpeta en '.$dependencia->nombre.'. Carpetas disponibles:');

            foreach (Carpeta::where('dependencia_id', $dependencia->id)->orderBy('nombre')->pluck('nombre') as $nombre) {
                $this->line('  · '.$nombre);
            }

            return 1;
        }

        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::create([
            'token_hash' => EnlaceCarga::hashDe($token),
            'token_cifrado' => $token,
            'dependencia_id' => $dependencia->id,
            'carpeta_id' => $carpeta->id,
            'proposito' => $this->option('proposito'),
            'activo' => true,
            'expira_at' => $this->option('dias') ? now()->addDays((int) $this->option('dias'))->endOfDay() : null,
            'max_usos' => $this->option('usos') ? (int) $this->option('usos') : null,
        ]);

        $auditor->registrar(
            AccionAuditoria::EnlaceCreado,
            $enlace,
            "Creó un enlace de carga hacia «{$carpeta->nombre}» (por consola)",
        );

        $this->newLine();
        $this->line('  Enlace creado.');
        $this->newLine();

        $this->table(['Campo', 'Valor'], [
            ['Dependencia', $dependencia->nombre],
            ['Entrega en', $carpeta->nombre],
            ['Propósito', $enlace->proposito ?: '—'],
            ['Vence', $enlace->expira_at?->format('d/m/Y H:i') ?? 'nunca'],
            ['Usos máximos', $enlace->max_usos ?? 'sin límite'],
        ]);

        $this->newLine();
        $this->line('  '.$enlace->url());
        $this->newLine();
        $this->warn('  Quien tenga esa URL puede subir a esa carpeta: el enlace no lleva identidad.');
        $this->newLine();

        return 0;
    },
)->purpose('Crea un enlace de carga hacia una carpeta y muestra su URL');
