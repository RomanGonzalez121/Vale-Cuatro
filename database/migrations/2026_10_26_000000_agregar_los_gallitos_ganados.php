<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El gallito (M25): la cuenta de gallitos ganados, para su sello de la libreta. Como las demás cuentas de la
 * libreta, se suma al cerrar cada partida y se rehace desde las partidas con fosforos:recalcular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->unsignedInteger('gallitos_ganados')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('gallitos_ganados');
        });
    }
};
