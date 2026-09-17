<?php

namespace App\Providers;

use App\Services\ContextoDependencia;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Authentik\Provider as AuthentikProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Una sola instancia por petición: el middleware la llena y
        // controladores, policies y global scopes la leen.
        $this->app->singleton(ContextoDependencia::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->limitarRecepcionExterna();

        // SocialiteProviders no se autodescubre: el driver 'authentik' solo
        // existe si se engancha aquí.
        Event::listen(function (SocialiteWasCalled $evento) {
            $evento->extendSocialite('authentik', AuthentikProvider::class);
        });
    }

    /**
     * Límites de la vía pública. Un enlace legítimo sube unos pocos archivos
     * al día, no cientos.
     */
    protected function limitarRecepcionExterna(): void
    {
        // Abrir el formulario es barato: el límite existe para frenar el
        // barrido de tokens desde una misma IP.
        RateLimiter::for('envio-formulario', fn (Request $peticion) => Limit::perMinute(30)->by($peticion->ip()));

        // Al cargar se limita por IP y, además, por token: una IP compartida
        // —una alcaldía entera detrás de un NAT— no debe agotarle el cupo a
        // los demás remitentes, y un token filtrado no debe poder inundar
        // una bandeja desde muchas IP.
        RateLimiter::for('envio-carga', fn (Request $peticion) => [
            Limit::perMinute(10)->by('ip:'.$peticion->ip()),
            Limit::perMinute(6)->by('token:'.$peticion->route('token')),
        ]);
    }
}
