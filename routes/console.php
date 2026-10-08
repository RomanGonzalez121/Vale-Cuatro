<?php

use App\Models\Jugador;
use Illuminate\Support\Facades\Schedule;

// Limpieza diaria: borra los invitados que no volvieron a jugar (ver Jugador::prunable).
Schedule::command('model:prune', ['--model' => [Jugador::class]])->daily();

// Las salas de partidas entre personas que esperaron rival demasiado se cierran solas (ver Mesa::cerrarSalasVencidas).
Schedule::command('salas:limpiar')->everyFiveMinutes();
