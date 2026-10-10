<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un torneo relámpago contra bots: una persona y, en los demás lugares, bots.
        Schema::create('torneos', function (Blueprint $table) {
            $table->id();
            // La persona que lo juega. Si su fila se borra, el torneo se va con ella.
            $table->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            // Cuántos lo juegan: 4 u 8.
            $table->unsignedTinyInteger('lugares');
            // A cuántos puntos va cada partida.
            $table->unsignedTinyInteger('puntos');
            // De acá salen el sorteo de las llaves y las partidas entre bots: la misma semilla da el mismo torneo.
            $table->unsignedInteger('semilla');
            // Quién ocupa cada lugar, en orden: la persona y los bots, cada bot con su apodo y su nivel.
            // Se decide al crearlo y no cambia más.
            $table->json('inscriptos');
            // en_curso o terminado.
            $table->string('estado', 12)->default('en_curso');
            // El lugar que salió campeón.
            $table->unsignedTinyInteger('campeon')->nullable();
            $table->timestamp('terminado_en')->nullable();
            $table->timestamps();

            $table->index(['jugador_id', 'estado']);
        });

        // Cada cruce de las llaves. Se crean todos al armar el torneo; los de las rondas que vienen
        // esperan vacíos a los ganadores de la anterior.
        Schema::create('cruces_de_torneo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('torneo_id')->constrained('torneos')->cascadeOnDelete();
            // La ronda (1 es la primera) y el puesto del cruce dentro de ella, de arriba hacia abajo.
            $table->unsignedTinyInteger('ronda');
            $table->unsignedTinyInteger('orden');
            // Los dos lugares que se cruzan y el que ganó. Vacíos hasta que se sepa.
            $table->unsignedTinyInteger('uno')->nullable();
            $table->unsignedTinyInteger('dos')->nullable();
            $table->unsignedTinyInteger('ganador')->nullable();
            // Cómo terminó: el tanteo de cada uno.
            $table->unsignedTinyInteger('puntos_uno')->nullable();
            $table->unsignedTinyInteger('puntos_dos')->nullable();
            // Si la persona dejó su partida, el cruce se cerró sin llegar a los puntos.
            $table->boolean('por_abandono')->default(false);
            // La partida que jugó la persona. Las partidas entre bots no se guardan: con la semilla salen iguales.
            $table->foreignId('partida_id')->nullable()->constrained('partidas')->nullOnDelete();

            $table->unique(['torneo_id', 'ronda', 'orden']);
        });

        Schema::table('partidas', function (Blueprint $table) {
            // El torneo del que es parte, si lo es.
            $table->foreignId('torneo_id')->nullable()->after('anterior_id')->constrained('torneos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('torneo_id');
        });

        Schema::dropIfExists('cruces_de_torneo');
        Schema::dropIfExists('torneos');
    }
};
