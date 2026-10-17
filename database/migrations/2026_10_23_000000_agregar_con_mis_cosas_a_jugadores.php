<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Jugar con mis cosas": en la gira, cada barrio se juega con la mesa de su bar y lo que traen sus rivales,
 * salvo que la persona prefiera su propia mesa, su mazo y su dorso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->boolean('con_mis_cosas')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('con_mis_cosas');
        });
    }
};
