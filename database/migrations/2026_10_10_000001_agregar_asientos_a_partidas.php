<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            // De cuántos asientos es la mesa: 2 en el mano a mano, 4 en el truco de a cuatro.
            // Las partidas de antes eran todas mano a mano.
            $table->unsignedTinyInteger('asientos')->default(2)->after('puntos');
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropColumn('asientos');
        });
    }
};
