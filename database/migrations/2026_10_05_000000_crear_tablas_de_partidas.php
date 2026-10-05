<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas', function (Blueprint $table) {
            $table->id();
            // El jugador ocupa el asiento 0 y el bot el 1. Si se borra el jugador, se van sus partidas.
            $table->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            // en_curso, terminada o abandonada.
            $table->string('estado', 12)->default('en_curso');
            // Lo único de la partida que no está en los eventos: quién fue mano en la primera mano y a cuántos puntos se juega.
            $table->unsignedTinyInteger('primer_mano');
            $table->unsignedTinyInteger('puntos')->default(30);
            // El asiento que ganó. Se puede calcular desde los eventos; está acá para no reconstruir cada partida al listarlas.
            $table->unsignedTinyInteger('ganador')->nullable();
            $table->timestamp('terminada_en')->nullable();
            $table->timestamps();

            $table->index(['jugador_id', 'estado']);
        });

        // La partida es esta lista: cada reparto con sus cartas y cada acción, en orden.
        Schema::create('eventos_de_partida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            // reparto, accion o abandono.
            $table->string('tipo', 12);
            $table->unsignedTinyInteger('asiento')->nullable();
            $table->json('datos');
            $table->timestamp('creado_en');

            // Dos pedidos a la vez no pueden guardar el mismo número de evento.
            $table->unique(['partida_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_de_partida');
        Schema::dropIfExists('partidas');
    }
};
