<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\Camino;
use App\Juego\Gira;
use App\Juego\Logros;
use App\Juego\Mesa;
use App\Juego\Premios;
use App\Juego\Titulos;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Models\Sello;
use App\Motor\Accion;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los premios de la gira de los clubes: cada jefe deja su título, su sello en la libreta y lo suyo (dorsos, la
 * Constelación, la mesa La guía); los fósforos de la gira suman al nivel una sola vez cada uno.
 */
class PremiosDeLaGiraTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le deja pasados todos los niveles de los primeros barrios, con un intento cada uno.
     */
    private function completar(Jugador $jugador, int $barrios, int $fosforos = 2): void
    {
        foreach (array_slice(Gira::barrios(), 0, $barrios) as $barrio) {
            foreach ($barrio['niveles'] as $nivel) {
                Progreso::query()->forceCreate([
                    'jugador_id' => $jugador->id, 'clave' => Gira::clave($barrio['clave'], $nivel['numero']),
                    'intentos' => 1, 'superado_en' => now(), 'fosforos' => $fosforos,
                ]);
            }
        }

        (new Titulos)->ponerAlDia($jugador);
        (new Logros)->revisar($jugador);
    }

    public function test_cada_jefe_deja_su_titulo_su_sello_y_sus_cosas(): void
    {
        $jugador = Jugador::factory()->create();
        $premios = new Premios;

        // Con Boedo y San Telmo: Del club y Anticuario, y el dorso Marmolado de Don Ceferino.
        $this->completar($jugador, 2);
        $jugador->refresh();

        $this->assertSame(2, $jugador->barrios_completos);
        $this->assertTrue(Titulos::gano($jugador, 'del-club'));
        $this->assertTrue(Titulos::gano($jugador, 'anticuario'));
        $this->assertFalse(Titulos::gano($jugador, 'estibador'));
        $this->assertTrue($premios->tieneDorso($jugador, 'marmolado'));
        $this->assertFalse($premios->tieneDorso($jugador, 'senales'));
        $this->assertSame(1, Sello::query()->where('jugador_id', $jugador->id)->where('logro', 'anticuario')->count());
        $this->assertSame(0, Sello::query()->where('jugador_id', $jugador->id)->where('logro', 'estibador')->count());
    }

    public function test_madama_ferrante_deja_el_tarot_y_la_constelacion_y_la_guia_es_para_quien_termina(): void
    {
        $jugador = Jugador::factory()->create();
        $premios = new Premios;

        $this->completar($jugador, 4);
        $jugador->refresh();

        $this->assertTrue($premios->tieneDorso($jugador, 'tarot'));
        $this->assertTrue($premios->tiene('anotar', $jugador, 'constelacion'));
        $this->assertFalse($premios->tiene('mesa', $jugador, 'guia'));
        $this->assertFalse(Titulos::gano($jugador, 'porteno'));

        // La gira entera, con los tres fósforos en cada parada: La guía, Patrón del Mercado y Porteño.
        Progreso::query()->delete();
        $this->completar($jugador, 6, fosforos: 3);
        $jugador->refresh();

        $this->assertTrue($premios->tiene('mesa', $jugador, 'guia'));
        $this->assertTrue(Titulos::gano($jugador, 'patron'));
        $this->assertTrue(Titulos::gano($jugador, 'porteno'));
        $this->assertSame([Gira::cuantos(), Gira::cuantos(), 6], [$jugador->paradas_pasadas, $jugador->paradas_perfectas, $jugador->jefes_a_la_primera]);
    }

    public function test_los_fosforos_de_la_gira_suman_al_nivel_una_sola_vez(): void
    {
        $jugador = Jugador::factory()->create();
        // La máquina se va al mazo apenas le toca: se gana por mucho y sin irse nunca, los tres fósforos.
        $mesa = new Mesa(new class implements Bot
        {
            public function decidir(array $vista): Accion
            {
                return Accion::de(TipoDeAccion::Mazo);
            }
        });
        $this->app->instance(Mesa::class, $mesa);

        $jugar = function () use ($mesa, $jugador): Partida {
            $partida = $mesa->abrirEnGira($jugador, 'san-telmo', 1);

            while ($partida->refresh()->enCurso()) {
                $vista = $mesa->vista($partida);

                if ($vista['fase'] === 'por_repartir') {
                    $mesa->repartir($partida);

                    continue;
                }

                $mesa->actuar($partida, Accion::desdeArray(collect($vista['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value)));
            }

            return $partida;
        };

        // La primera vez: los 3 de ganar la partida y los 3 fósforos nuevos de la parada.
        // (Los sellos de la libreta que caigan suman su propio fósforo: se descuentan.)
        $sinSellos = fn () => $jugador->fresh()->fosforos - Sello::query()->where('jugador_id', $jugador->id)->count() * Logros::FOSFOROS_POR_SELLO;
        $primera = $jugar();
        $this->assertSame([3, 6], [$primera->fosforos_de_gira, $sinSellos()]);

        // Volver a pasarla con los mismos tres no suma fósforos de la gira: solo los de ganar.
        $segunda = $jugar();
        $this->assertSame([0, 9], [$segunda->fosforos_de_gira, $sinSellos()]);

        // Y el final de la partida los cuenta con los demás.
        $this->assertSame(6, (new Camino)->resumen($primera, Mesa::JUGADOR, $mesa)['ganados'] - count((new Logros)->deLaPartida($primera, $jugador->id)));
    }

    public function test_la_administracion_tiene_todo_lo_de_la_gira(): void
    {
        $administracion = Jugador::factory()->administrador()->create();
        $premios = new Premios;

        $this->assertTrue($premios->tieneDorso($administracion, 'tarot'));
        $this->assertTrue($premios->tiene('mesa', $administracion, 'guia'));
        $this->assertTrue($premios->tiene('anotar', $administracion, 'constelacion'));
        $this->assertTrue($premios->tieneTitulo($administracion, 'porteno'));
    }
}
