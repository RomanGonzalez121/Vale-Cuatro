<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las cuentas que miden los logros de la libreta (M23). Se suman al cerrar cada partida y se rehacen
     * desde las partidas con fosforos:recalcular, como las de los títulos.
     */
    private const CUENTAS = [
        'treinta_y_tres', 'envido_primero', 'quieros_ganados', 'contraflores_ganadas', 'partidas_jugadas',
        'partidas_ganadas', 'mejor_racha', 'remontadas', 'sin_salir_de_malas', 'dificil_ganadas', 'ultra_ganadas',
        'de_a_cuatro_ganadas', 'senas_hechas', 'revanchas_ganadas', 'tres_pardas', 'treinta_a_cero',
        'ancho_contra_ancho', 'contraflor_al_resto',
    ];

    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            foreach (self::CUENTAS as $cuenta) {
                $table->unsignedInteger($cuenta)->default(0);
            }
        });

        // Los sellos de la libreta: uno por escalón ganado de cada logro, con la partida que lo dio (si salió de
        // una) y desde cuándo. "visto" es si su dueño ya lo vio en su libreta: hasta entonces, el menú lo marca.
        Schema::create('sellos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            $table->string('logro', 40);
            $table->unsignedTinyInteger('escalon');
            $table->foreignId('partida_id')->nullable()->constrained('partidas')->nullOnDelete();
            $table->timestamp('ganado_en');
            $table->boolean('visto')->default(false);

            $table->unique(['jugador_id', 'logro', 'escalon']);
            $table->index(['logro', 'escalon']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellos');

        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn(self::CUENTAS);
        });
    }
};
