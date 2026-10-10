<?php

namespace App\Providers;

use App\Administracion\Credenciales;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El email y la contraseña de la cuenta de administración se leen del entorno recién cuando alguien los pide.
        $this->app->bind(Credenciales::class, fn () => Credenciales::delEntorno());
    }

    public function boot(): void
    {
        //
    }
}
