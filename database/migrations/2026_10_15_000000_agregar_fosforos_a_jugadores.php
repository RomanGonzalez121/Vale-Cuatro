<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            // Los fósforos que lleva anotados: el nivel sale de acá (diez por nivel). Se suma al cerrar cada
            // partida (App\Juego\Camino) y se rehace desde los eventos con fosforos:recalcular.
            $table->unsignedInteger('fosforos')->default(0)->after('experiencia');
            // Hasta qué nivel vio sus premios en el perfil: si subió más, el menú le marca que hay algo nuevo.
            $table->unsignedSmallInteger('nivel_visto')->default(1)->after('fosforos');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn(['fosforos', 'nivel_visto']);
        });
    }
};
