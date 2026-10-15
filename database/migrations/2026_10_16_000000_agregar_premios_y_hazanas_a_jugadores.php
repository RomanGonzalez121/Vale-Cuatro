<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            // Lo que eligió en la Colección. Null es lo de fábrica (o, para el distintivo y el título, ninguno).
            // Al leerlo se vuelve a comprobar que lo tenga ganado: App\Juego\Premios.
            $table->string('mesa', 30)->nullable()->after('dorso');
            $table->string('anotar', 30)->nullable()->after('mesa');
            $table->string('distintivo', 30)->nullable()->after('anotar');
            $table->string('titulo', 30)->nullable()->after('distintivo');
            // Cómo se lee su título: Cantor o Cantora. Lo elige cada uno; nunca se adivina por el apodo.
            $table->boolean('titulo_femenino')->default(false)->after('titulo');

            // Lo que hizo en la mesa, para los títulos. Se suma al cerrar cada partida (App\Juego\Titulos)
            // y se rehace desde los eventos con fosforos:recalcular.
            $table->unsignedInteger('envidos_cantados')->default(0)->after('nivel_visto');
            $table->unsignedInteger('envidos_ganados')->default(0)->after('envidos_cantados');
            $table->unsignedInteger('trucos_ganados')->default(0)->after('envidos_ganados');
            $table->unsignedInteger('flores_cantadas')->default(0)->after('trucos_ganados');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn([
                'mesa', 'anotar', 'distintivo', 'titulo', 'titulo_femenino',
                'envidos_cantados', 'envidos_ganados', 'trucos_ganados', 'flores_cantadas',
            ]);
        });
    }
};
