<?php

namespace App\Providers;

use App\Juego\Bot;
use App\Juego\BotIntermedio;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El rival de la mesa, hasta que la partida guarde su nivel.
        $this->app->bind(Bot::class, BotIntermedio::class);
    }

    public function boot(): void
    {
        //
    }
}
