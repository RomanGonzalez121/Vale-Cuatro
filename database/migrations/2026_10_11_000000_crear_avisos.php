<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El par de claves con que el sitio firma sus avisos. Es uno solo: se genera la primera vez que
        // hace falta y queda acá, con la privada cifrada. No va en el repositorio ni en la configuración.
        Schema::create('claves_de_aviso', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->string('publica', 200);
            $tabla->text('privada');
            $tabla->timestamps();
        });

        // Un navegador que pidió avisos. "destino" es la dirección que le dio su servicio de avisos (el de
        // Chrome, el de Firefox, el de Apple): ahí manda el sitio cada aviso. Las otras dos son las claves
        // con que se cifra el texto para que solo ese navegador lo lea.
        Schema::create('suscripciones_de_aviso', function (Blueprint $tabla) {
            $tabla->id();
            // Si el jugador se borra (un invitado que limpia la tarea programada), se van sus suscripciones.
            $tabla->foreignId('jugador_id')->constrained('jugadores')->cascadeOnDelete();
            // La dirección puede pasar de 500 letras y no entra en un índice: se busca por su huella.
            $tabla->char('huella', 64)->unique();
            $tabla->text('destino');
            $tabla->string('p256dh', 200);
            $tabla->string('auth', 100);
            $tabla->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones_de_aviso');
        Schema::dropIfExists('claves_de_aviso');
    }
};
