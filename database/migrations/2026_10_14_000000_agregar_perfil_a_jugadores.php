<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            // "Tu carta": la que lo representa en el perfil, el ranking, la mesa y el torneo. Vacío es la de fábrica.
            // Solo vale si está destrabada: lo decide App\Juego\Premios cada vez que se lee.
            $table->string('carta', 12)->nullable()->after('apodo');
            // El dorso de sus cartas, que es lo que ve el rival. Vacío es el de fábrica.
            $table->string('dorso', 20)->nullable()->after('carta');
            // Cuánto dice que sabe de truco (App\Juego\Experiencia). Ajusta el bot de entrada y las ayudas; no es la categoría.
            $table->unsignedTinyInteger('experiencia')->nullable()->after('dorso');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn(['carta', 'dorso', 'experiencia']);
        });
    }
};
