<?php

use App\Enums\AccionAuditoria;
use App\Models\Dependencia;
use App\Models\EnlaceCarga;
use App\Models\User;
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
| Ayuda temporal para probar la vía pública mientras no exista la pantalla
| de administración de enlaces. Bórrala cuando llegue ese punto.
*/

Artisan::command(
    'enlace:crear {remitente : Nombre de la persona externa}
                  {--dependencia= : Slug de la dependencia (por defecto, la primera activa)}
                  {--destinatario= : Correo de quien recibe (por defecto, el primer administrador)}
                  {--proposito= : Para qué es el enlace}
                  {--email= : Correo del remitente}
                  {--entidad= : Empresa o entidad del remitente}
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

        $destinatario = $this->option('destinatario')
            ? User::where('email', $this->option('destinatario'))->first()
            : $dependencia->usuarios()->wherePivot('rol', 'administracion')->orderBy('users.id')->first();

        if ($destinatario === null) {
            $this->error('No encontré al destinatario. Indícalo con --destinatario=correo@ejemplo.com');

            return 1;
        }

        // El contexto hace falta para que la auditoría quede en la dependencia
        // correcta: en consola no hay middleware que lo llene.
        $contexto->establecer($dependencia);

        $token = EnlaceCarga::generarToken();

        $enlace = EnlaceCarga::create([
            'token_hash' => EnlaceCarga::hashDe($token),
            'token_cifrado' => $token,
            'dependencia_id' => $dependencia->id,
            'destinatario_id' => $destinatario->id,
            'remitente_nombre' => $this->argument('remitente'),
            'remitente_email' => $this->option('email'),
            'remitente_entidad' => $this->option('entidad'),
            'proposito' => $this->option('proposito'),
            'activo' => true,
            'expira_at' => $this->option('dias') ? now()->addDays((int) $this->option('dias')) : null,
            'max_usos' => $this->option('usos') ? (int) $this->option('usos') : null,
        ]);

        $auditor->registrar(
            AccionAuditoria::EnlaceCreado,
            $enlace,
            "Creó un enlace de carga para {$enlace->remitente_nombre} (por consola)",
        );

        $this->newLine();
        $this->line('  Enlace creado.');
        $this->newLine();

        $this->table(['Campo', 'Valor'], [
            ['Remitente', $enlace->remitente_nombre],
            ['Dependencia', $dependencia->nombre],
            ['Llega a', $destinatario->name.' <'.$destinatario->email.'>'],
            ['Propósito', $enlace->proposito ?: '—'],
            ['Vence', $enlace->expira_at?->format('d/m/Y H:i') ?? 'nunca'],
            ['Usos máximos', $enlace->max_usos ?? 'sin límite'],
        ]);

        $this->newLine();
        $this->line('  '.$enlace->url());
        $this->newLine();
        $this->warn('  El token es un secreto: quien tenga esa URL puede enviar en nombre del remitente.');
        $this->newLine();

        return 0;
    },
)->purpose('Crea un enlace de carga y muestra su URL (ayuda temporal hasta el punto 5)');
