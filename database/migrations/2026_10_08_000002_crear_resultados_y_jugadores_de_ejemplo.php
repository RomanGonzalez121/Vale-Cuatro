<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            // Los jugadores de ejemplo del ranking: no son de nadie, juegan solos y el sitio los marca como bots.
            $table->boolean('de_ejemplo')->default(false)->after('password');
        });

        Schema::table('partidas', function (Blueprint $table) {
            // Una partida que jugaron dos bots solos, sin nadie mirando: las de los jugadores de ejemplo.
            // Todo lo simulado va marcado, también en la base.
            $table->boolean('simulada')->default(false)->after('entre_personas');
        });

        // Lo que una partida cerrada le cuenta al ranking: un renglón por cada persona que la jugó.
        // No es una segunda verdad: sale de los eventos de la partida y se puede rehacer entero desde ellos
        // (ranking:recalcular). Está acá para no volver a jugar todas las partidas cada vez que se abre la tabla.
        Schema::create('resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            $table->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            $table->boolean('gano');
            // Envidos que se quisieron (se compararon los tantos) y cuántos de esos ganó.
            $table->unsignedSmallInteger('envidos_jugados')->default(0);
            $table->unsignedSmallInteger('envidos_ganados')->default(0);
            $table->timestamp('terminada_en');

            $table->unique(['partida_id', 'jugador_id']);
            // La racha de cada jugador se lee siguiendo sus renglones por fecha, desde su última perdida.
            $table->index(['jugador_id', 'gano', 'terminada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados');

        Schema::table('partidas', function (Blueprint $table) {
            $table->dropColumn('simulada');
        });

        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('de_ejemplo');
        });
    }
};
