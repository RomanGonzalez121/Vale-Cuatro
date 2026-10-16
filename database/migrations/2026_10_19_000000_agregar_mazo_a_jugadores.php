<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            // El mazo que eligió en la Colección (App\Juego\Mazos). Null es el de fábrica. Al leerlo se vuelve a
            // comprobar que lo tenga ganado: App\Juego\Premios.
            $table->string('mazo', 30)->nullable()->after('mesa');
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('mazo');
        });
    }
};
