<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una serie agrupa las partidas que dos jugadores acordaron jugar seguidas (al mejor de tres).
        // No guarda nada de lo que se jugó: cada partida sigue siendo su propia lista de eventos.
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            // Cuántas partidas tiene como mucho: gana quien se lleva más de la mitad.
            $table->unsignedTinyInteger('al_mejor_de')->default(3);
            // El asiento que ganó la serie. Los asientos no cambian de una partida a la otra.
            $table->unsignedTinyInteger('ganador')->nullable();
            $table->timestamp('terminada_en')->nullable();
            $table->timestamps();
        });

        Schema::table('partidas', function (Blueprint $table) {
            // La serie a la que pertenece, si se juega al mejor de tres.
            $table->foreignId('serie_id')->nullable()->after('codigo')->constrained('series')->nullOnDelete();
            // La partida anterior entre los mismos dos: la que sigue en una serie, o la revancha de esa.
            // De ella sale quién es mano en esta: se alterna.
            $table->foreignId('anterior_id')->nullable()->after('serie_id')->constrained('partidas')->nullOnDelete();
        });

        // Un pedido de revancha, entre dos personas. No es parte de ninguna partida: si no se acepta, no queda nada jugado.
        Schema::create('revanchas', function (Blueprint $table) {
            $table->id();
            // La partida que terminó, de la que se pide la revancha.
            $table->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            // El asiento que la pidió.
            $table->unsignedTinyInteger('asiento');
            // pendiente, aceptada, rechazada, cancelada o caida. Una pendiente con la hora cumplida está vencida.
            $table->string('estado', 12)->default('pendiente');
            $table->timestamp('vence_en');
            $table->timestamps();

            // Cada jugador la pide una sola vez por partida: no se puede insistir.
            $table->unique(['partida_id', 'asiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revanchas');

        Schema::table('partidas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anterior_id');
            $table->dropConstrainedForeignId('serie_id');
        });

        Schema::dropIfExists('series');
    }
};
