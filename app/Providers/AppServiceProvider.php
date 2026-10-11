<?php

namespace App\Providers;

use App\Administracion\Credenciales;
use App\Avisos\Cartero;
use App\Avisos\CarteroWebPush;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El email y la contraseña de la cuenta de administración se leen del entorno recién cuando alguien los pide.
        $this->app->bind(Credenciales::class, fn () => Credenciales::delEntorno());

        // Los avisos al celular salen por Web Push. Los tests ponen acá un cartero de mentira.
        $this->app->bind(Cartero::class, CarteroWebPush::class);
    }

    public function boot(): void
    {
        //
    }
}
