<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            // Contra qué bot se juega: 1 Fácil, 2 Intermedio, 3 Difícil. Las partidas de antes eran contra el Intermedio.
            $table->unsignedTinyInteger('nivel_bot')->default(2)->after('puntos');
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropColumn('nivel_bot');
        });
    }
};
