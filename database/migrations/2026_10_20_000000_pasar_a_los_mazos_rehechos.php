<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tres mazos se rehicieron como barajas propias en los mismos niveles: quien tenía elegido uno de ellos pasa al
     * nuevo. De noche y A una tinta no cambian (quedan como variantes del de fábrica).
     */
    private const REEMPLAZOS = [
        'palos-de-fosforo' => 'caja-de-fosforos',
        'fosforo-de-noche' => 'pizarron',
        'imprenta' => 'tipografico',
    ];

    public function up(): void
    {
        foreach (self::REEMPLAZOS as $viejo => $nuevo) {
            DB::table('jugadores')->where('mazo', $viejo)->update(['mazo' => $nuevo]);
        }
    }

    public function down(): void
    {
        foreach (self::REEMPLAZOS as $viejo => $nuevo) {
            DB::table('jugadores')->where('mazo', $nuevo)->update(['mazo' => $viejo]);
        }
    }
};
