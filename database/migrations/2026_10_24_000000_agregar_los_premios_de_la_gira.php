<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los premios de la gira se cuentan como los demás títulos y logros: en columnas del jugador que se ponen al día
 * al cerrar cada partida (Titulos::ponerAlDia) y se rehacen con fosforos:recalcular. Y cada partida de la gira
 * guarda cuántos fósforos nuevos sumó al nivel, para que el final de la partida los cuente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->unsignedSmallInteger('barrios_completos')->default(0);
            $table->unsignedSmallInteger('paradas_pasadas')->default(0);
            $table->unsignedSmallInteger('paradas_perfectas')->default(0);
            $table->unsignedSmallInteger('jefes_a_la_primera')->default(0);
        });

        Schema::table('partidas', function (Blueprint $table) {
            $table->unsignedTinyInteger('fosforos_de_gira')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn(['barrios_completos', 'paradas_pasadas', 'paradas_perfectas', 'jefes_a_la_primera']);
        });

        Schema::table('partidas', function (Blueprint $table) {
            $table->dropColumn('fosforos_de_gira');
        });
    }
};
