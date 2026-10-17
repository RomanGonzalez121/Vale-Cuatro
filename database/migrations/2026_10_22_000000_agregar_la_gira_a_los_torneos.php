<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La copa de un barrio de la gira es un torneo de cuatro: el torneo guarda de qué nivel es ("barrio:nivel"),
 * para anotarlo al tener campeón y para que sus partidas no cuenten en el ranking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->string('gira', 40)->nullable()->after('semilla');
        });
    }

    public function down(): void
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->dropColumn('gira');
        });
    }
};
