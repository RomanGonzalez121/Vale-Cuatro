<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jugadores', function (Blueprint $table) {
            // Quién puede entrar al panel de administración. Lo otorga solo un comando de consola.
            $table->boolean('es_administrador')->default(false)->after('de_ejemplo');
        });

        // El cuaderno del panel: cada cosa que hace un administrador queda anotada, y no se edita ni se borra.
        Schema::create('acciones_de_administracion', function (Blueprint $table) {
            $table->id();
            // Quién la hizo. Si su cuenta se borra, la anotación queda.
            $table->foreignId('administrador_id')->nullable()->constrained('jugadores')->nullOnDelete();
            // Qué hizo, en una palabra (cerrar_partida, ocultar_apodo...) y contado en una frase.
            $table->string('accion', 40);
            $table->string('detalle');
            $table->timestamp('creada_en');

            $table->index('creada_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acciones_de_administracion');

        Schema::table('jugadores', function (Blueprint $table) {
            $table->dropColumn('es_administrador');
        });
    }
};
