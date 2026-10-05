<?php

namespace App\Providers;

use App\Juego\Bot;
use App\Juego\BotProvisional;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El rival de la mesa. M4 cambia esta línea por el bot con niveles.
        $this->app->bind(Bot::class, BotProvisional::class);
    }

    public function boot(): void
    {
        //
    }
}
