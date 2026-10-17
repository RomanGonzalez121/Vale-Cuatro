<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los premios a la vista (M20c). Cada partida guarda qué piezas nuevas le dio a cada persona, para mostrarlas en el
 * final (venga del nivel, de la gira o de un logro). Y cada jugador guarda las piezas que ganó y todavía no vio en la
 * Colección: llevan "Nuevo" y encienden la marca del menú. Null quiere decir que todavía no se anotó nada: se arma
 * la primera vez con lo que estaba por ver del camino (App\Juego\Premios::nuevas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->json('premios_nuevos')->nullable();
        });

        Schema::table('jugadores', function (Blueprint $table) {
            $table->json('piezas_nuevas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropColumn('premios_nuevos');
        });

        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('piezas_nuevas');
        });
    }
};
