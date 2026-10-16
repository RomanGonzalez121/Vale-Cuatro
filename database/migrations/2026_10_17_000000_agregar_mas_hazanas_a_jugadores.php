<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            // Lo que hizo en la mesa, para los títulos de la tanda 4. Se suma al cerrar cada partida
            // (App\Juego\Titulos) y se rehace desde los eventos con fosforos:recalcular.
            $table->unsignedInteger('retrucos_ganados')->default(0)->after('flores_cantadas');
            $table->unsignedInteger('chamuyos')->default(0)->after('retrucos_ganados');
            $table->unsignedInteger('manos_con_parda')->default(0)->after('chamuyos');
            $table->unsignedInteger('faltas_ganadas')->default(0)->after('manos_con_parda');
            $table->unsignedInteger('vale_cuatros_ganados')->default(0)->after('faltas_ganadas');

            // Lo que hizo en los otros juegos: se pone al día desde los torneos y los progresos.
            $table->unsignedInteger('torneos_ganados')->default(0)->after('vale_cuatros_ganados');
            $table->unsignedInteger('escalones_pasados')->default(0)->after('torneos_ganados');
            $table->unsignedInteger('desafios_pasados')->default(0)->after('escalones_pasados');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn([
                'retrucos_ganados', 'chamuyos', 'manos_con_parda', 'faltas_ganadas', 'vale_cuatros_ganados',
                'torneos_ganados', 'escalones_pasados', 'desafios_pasados',
            ]);
        });
    }
};
