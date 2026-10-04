<?php

namespace Tests\Feature;

use App\Models\Jugador;
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

    public function test_la_mesa_lleva_las_cuarenta_plantillas_y_el_dorso(): void
    {
        // A la mesa se llega con un jugador: acá, un invitado.
        $html = $this->actingAs(Jugador::factory()->invitado()->make(['id' => 1]))
            ->get('/mesa')->assertOk()->assertSee('Vale Cuatro')->getContent();

        $this->assertSame(41, substr_count($html, '<template data-plantilla='));
    }

    public function test_el_ranking_marca_a_los_jugadores_de_ejemplo_como_bots(): void
    {
        $html = $this->get('/ranking')->assertOk()->getContent();

        // Once jugadores de ejemplo y una sola fila sin marca: la del visitante.
        $this->assertSame(11, preg_match_all('/<\/svg>\s*bot\s*<\/span>/', $html));
        $this->assertSame(1, substr_count($html, 'invitado'));
    }
}
