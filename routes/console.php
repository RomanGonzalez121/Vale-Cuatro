<?php

use App\Models\Jugador;
use Illuminate\Support\Facades\Schedule;

// Limpieza diaria: borra los invitados que no volvieron a jugar (ver Jugador::prunable).
Schedule::command('model:prune', ['--model' => [Jugador::class]])->daily();
