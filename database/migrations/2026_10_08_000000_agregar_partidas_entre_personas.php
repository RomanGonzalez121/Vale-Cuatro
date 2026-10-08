<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            // El segundo jugador de una partida entre personas. Ocupa el asiento 1 y quien la creó, el 0.
            // Si se borra su cuenta, la partida queda: el asiento sigue siendo suyo aunque ya no figure.
            $table->foreignId('invitado_id')->nullable()->after('jugador_id')->constrained('jugadores')->nullOnDelete();

            // Con quién se juega. Las partidas de antes y las de contra el bot siguen siendo false.
            $table->boolean('entre_personas')->default(false)->after('puntos');

            // Lo que va en el link de invitación. Largo y sorteado: quien no tiene el link no puede entrar.
            $table->string('codigo', 24)->nullable()->unique()->after('entre_personas');
        });

        // Una partida entre personas arranca "esperando" y todavía no tiene primer mano ni bot:
        // el primer mano se sortea recién cuando se sienta el rival.
        Schema::table('partidas', function (Blueprint $table) {
            $table->unsignedTinyInteger('primer_mano')->nullable()->change();
            $table->unsignedTinyInteger('nivel_bot')->nullable()->default(2)->change();
        });
    }

    public function down(): void
    {
        // Una partida entre personas no se puede representar en el esquema de antes: se van con la columna.
        DB::table('partidas')->where('entre_personas', true)->delete();

        Schema::table('partidas', function (Blueprint $table) {
            $table->unsignedTinyInteger('primer_mano')->nullable(false)->change();
            $table->unsignedTinyInteger('nivel_bot')->nullable(false)->default(2)->change();
        });

        Schema::table('partidas', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropConstrainedForeignId('invitado_id');
            $table->dropColumn(['entre_personas', 'codigo']);
        });
    }
};
