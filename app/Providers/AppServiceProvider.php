<?php

namespace App\Providers;

use App\Services\ContextoDependencia;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
    }
}
