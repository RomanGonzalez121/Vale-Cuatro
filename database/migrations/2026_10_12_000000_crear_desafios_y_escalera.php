<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            // Si es una mano armada con un objetivo, cuál: la clave del desafío en el catálogo (App\Juego\Desafios).
            $table->string('desafio', 40)->nullable()->after('torneo_id');
            // Si es de la escalera del club, qué escalón. Un escalón puede ser una mano con objetivo o una partida entera.
            $table->unsignedTinyInteger('escalon')->nullable()->after('desafio');
        });

        // Lo que cada jugador ya pasó: un renglón por desafío suelto o por escalón que haya intentado.
        Schema::create('progresos', function (Blueprint $table) {
            $table->id();
            // Vale también para quien juega sin cuenta: es la misma fila de jugador, y la conserva si se registra.
            $table->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            // "desafio:<clave>" o "escalon:<número>".
            $table->string('clave', 60);
            // Cuántas veces lo jugó hasta el final, lo haya pasado o no.
            $table->unsignedInteger('intentos')->default(0);
            // Cuándo lo pasó por primera vez. Vacío mientras no lo pase.
            $table->timestamp('superado_en')->nullable();
            $table->timestamps();

            $table->unique(['jugador_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progresos');

        Schema::table('partidas', function (Blueprint $table) {
            $table->dropColumn(['desafio', 'escalon']);
        });
    }
};
