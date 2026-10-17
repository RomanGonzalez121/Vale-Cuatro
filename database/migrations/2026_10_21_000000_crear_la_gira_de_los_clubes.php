<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | La gira de los clubes (M26): cada partida dice de qué nivel de la gira es ("san-telmo:3"), y cada progreso
 | guarda los fósforos del mejor intento (de 0 a 3). Los escalones ya pasados de la escalera, que ahora es
 | Boedo, arrancan con uno: el de pasarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $tabla) {
            $tabla->string('gira', 40)->nullable()->after('escalon');
        });

        Schema::table('progresos', function (Blueprint $tabla) {
            $tabla->unsignedTinyInteger('fosforos')->default(0)->after('intentos');
        });

        DB::table('progresos')->whereNotNull('superado_en')->update(['fosforos' => 1]);
    }

    public function down(): void
    {
        Schema::table('progresos', function (Blueprint $tabla) {
            $tabla->dropColumn('fosforos');
        });

        Schema::table('partidas', function (Blueprint $tabla) {
            $tabla->dropColumn('gira');
        });
    }
};
