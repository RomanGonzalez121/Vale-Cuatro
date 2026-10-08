<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            // Cuándo se resuelve solo lo que está esperando una partida entre personas: el turno de quien tiene que
            // jugar (se va al mazo) o el reparto de la mano siguiente. Nulo si no hay nada que esperar.
            $table->timestamp('plazo_vence_en')->nullable()->after('terminada_en');
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropColumn('plazo_vence_en');
        });
    }
};
