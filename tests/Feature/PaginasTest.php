<?php

namespace Tests\Feature;

use App\View\Components\Carta;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginasTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function rutas(): array
    {
        return [
            'portada' => ['/'],
            'ranking' => ['/ranking'],
            'historial' => ['/historial'],
            'cómo se juega' => ['/como-se-juega'],
            'identidad' => ['/identidad'],
            'modos de juego' => ['/modos'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_cada_pantalla_responde(string $ruta): void
    {
        $this->get($ruta)->assertOk()->assertSee('Vale Cuatro');
    }

    public function test_la_identidad_muestra_las_cuarenta_cartas(): void
    {
        $html = $this->get('/identidad')->assertOk()->getContent();

        foreach (Carta::mazo() as [$palo, $numero]) {
            $this->assertStringContainsString("data-carta=\"{$numero}-{$palo}\"", $html, "Falta el {$numero} de {$palo}.");
        }
    }

    public function test_la_identidad_muestra_los_iconos_propios(): void
    {
        $respuesta = $this->get('/identidad')->assertOk();

        foreach (['Espada', 'Basto', 'Oro', 'Copa', 'Envido', 'Truco', 'Irse al mazo', 'Quién es mano', 'Repartir', 'Quiero', 'No quiero', 'Tiempo', 'Bot', 'Invitar', 'Ranking', 'Repetir partida', 'Sonido'] as $icono) {
            $respuesta->assertSee($icono);
        }
    }

    public function test_el_ranking_marca_a_los_jugadores_de_ejemplo_como_bots(): void
    {
        $html = $this->get('/ranking')->assertOk()->getContent();

        // Once jugadores de ejemplo y una sola fila sin marca: la del visitante.
        $this->assertSame(11, preg_match_all('/<\/svg>\s*bot\s*<\/span>/', $html));
        $this->assertSame(1, substr_count($html, 'invitado'));
    }

    public function test_en_la_portada_un_solo_boton_entra_a_la_mesa_y_los_demas_modos_van_a_su_pantalla(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // El botón que decía "Invitar a alguien" entraba a la mesa contra el bot: ahora es un link a los modos.
        $this->assertSame(1, preg_match_all('/<button type="submit"/', $html));
        $this->assertStringNotContainsString('Invitar a alguien', $html);
        $this->assertStringContainsString('href="'.route('modos').'"', $html);
    }

    public function test_ninguna_pagina_de_lectura_deja_algo_pegado_al_scroll(): void
    {
        foreach (['/', '/ranking', '/historial', '/como-se-juega', '/modos'] as $ruta) {
            $html = $this->get($ruta)->assertOk()->getContent();

            $this->assertSame(0, preg_match('/class="[^"]*\b(sticky|fixed)\b/', $html), "En {$ruta} hay algo pegado o fijo.");
        }
    }
}
