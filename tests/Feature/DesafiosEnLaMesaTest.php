<?php

namespace Tests\Feature;

use App\Juego\Desafios;
use App\Juego\Estadisticas;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Objetivos;
use App\Juego\Progresos;
use App\Juego\Revanchas;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Models\Resultado;
use App\Motor\Accion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\ResolviendoDesafios;

/**
 * Un desafío jugado por la mesa de verdad: la partida guardada como eventos, el bot contestando desde
 * la cola y el objetivo decidido por el servidor. En estos tests la cola corre en el momento.
 */
class DesafiosEnLaMesaTest extends TestCase
{
    use RefreshDatabase;
    use ResolviendoDesafios;

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Juega las jugadas de la persona, en orden. El bot contesta solo después de cada una.
     *
     * @param  list<Accion>  $jugadas
     */
    private function jugar(Partida $partida, array $jugadas): Partida
    {
        foreach ($jugadas as $accion) {
            $this->mesa()->actuar($partida, $accion);
        }

        return $partida->refresh();
    }

    public function test_abrir_un_desafio_arma_la_mano_de_su_ficha_sin_sortear_nada(): void
    {
        $ficha = Desafios::de('parda-la-primera');
        $vistas = [];

        foreach ([Jugador::factory()->create(), Jugador::factory()->invitado()->create()] as $jugador) {
            $partida = $this->mesa()->abrirDesafio($jugador, $ficha);
            $vistas[] = $vista = $this->mesa()->vista($partida);

            $this->assertSame('parda-la-primera', $partida->desafio);
            $this->assertNull($partida->escalon);
            $this->assertSame(Nivel::Intermedio, $partida->nivel_bot);
            $this->assertSame($ficha['cartas']['vos'], $vista['misCartas']);
            // Lo que la ficha da por jugado ya está en la mesa: el bot salió con el ancho falso.
            $this->assertSame([[Mesa::BOT, '1-oro']], $vista['bazas'][0]['jugadas']);
            $this->assertNull($vista['superado']);

            // El tanteo de arranque quedó guardado con el reparto: la partida se cuenta de nuevo desde sus eventos.
            $reparto = $partida->eventos()->first();
            $this->assertSame(EventoDePartida::REPARTO, $reparto->tipo);
            $this->assertSame([0, 0], $reparto->datos['tanteo']);
            $this->assertSame(Desafios::manos($ficha), $reparto->datos['manos']);
        }

        // Dos personas distintas reciben exactamente la misma mesa (salvo de qué partida es).
        unset($vistas[0]['partida'], $vistas[1]['partida']);
        $this->assertSame($vistas[0], $vistas[1]);
    }

    public function test_un_desafio_arranca_desde_el_tanteo_de_su_ficha(): void
    {
        $partida = $this->mesa()->abrirDesafio(Jugador::factory()->create(), Desafios::de('los-tres-que-faltan'));

        $this->assertSame([27, 22], $this->mesa()->vista($partida)['tanteo']);
        $this->assertSame([27, 22], $this->mesa()->reconstruir($partida)->tanteo());
    }

    public function test_cada_desafio_se_gana_y_se_pierde_por_la_mesa_igual_que_al_buscarle_la_solucion(): void
    {
        foreach (Desafios::todos() as $ficha) {
            $resultado = $this->resolver($ficha);
            $jugador = Jugador::factory()->create();

            // Primero de una manera que no cumple: queda un intento y nada pasado.
            $perdida = $this->jugar($this->mesa()->abrirDesafio($jugador, $ficha), $resultado['falla']);

            $this->assertSame([Partida::TERMINADA, Mesa::BOT], [$perdida->estado, $perdida->ganador], $ficha['clave']);
            $this->assertFalse($this->mesa()->vista($perdida)['superado'], $ficha['clave']);

            // Después de una que cumple: el bot contesta por la mesa lo mismo que contestó al buscar la solución.
            $ganada = $this->jugar($this->mesa()->abrirDesafio($jugador, $ficha), $resultado['cumple']);

            $this->assertSame([Partida::TERMINADA, Mesa::JUGADOR], [$ganada->estado, $ganada->ganador], $ficha['clave']);
            $this->assertTrue($this->mesa()->vista($ganada)['superado'], $ficha['clave']);

            $progreso = Progreso::query()->where('jugador_id', $jugador->id)->sole();

            $this->assertSame(Progresos::claveDeDesafio($ficha['clave']), $progreso->clave);
            $this->assertSame(2, $progreso->intentos);
            $this->assertTrue($progreso->superado());
        }
    }

    public function test_si_se_cumplio_el_objetivo_sale_de_los_eventos_guardados(): void
    {
        $ficha = Desafios::de('irse-a-tiempo');
        $partida = $this->jugar($this->mesa()->abrirDesafio(Jugador::factory()->create(), $ficha), $this->resolver($ficha)['cumple']);

        // Con la partida ya cerrada, volver a pasar sus eventos por el motor da lo mismo que quedó anotado.
        [$hechos, $final] = Objetivos::loQuePaso($this->mesa()->pasoAPaso($partida));

        $this->assertTrue(Objetivos::cumple($ficha['objetivo'], $hechos, $final));
        $this->assertSame(Mesa::JUGADOR, $partida->ganador);
        // El bot sumó un punto y el jugador ninguno: el objetivo no era ganar la mano.
        $this->assertSame([0, 1], $final->tanteo());
        $this->assertSame(Mesa::BOT, $final->cierre()['ganador']);
    }

    public function test_un_desafio_es_una_sola_mano_y_despues_no_se_reparte(): void
    {
        $ficha = Desafios::de('con-la-justa');
        $partida = $this->jugar($this->mesa()->abrirDesafio(Jugador::factory()->create(), $ficha), $this->resolver($ficha)['cumple']);

        $this->assertFalse($partida->enCurso());
        $this->assertSame(1, $this->mesa()->reconstruir($partida)->aArray()['numeroDeMano']);

        $this->expectExceptionMessage('La partida ya terminó.');
        $this->mesa()->repartir($partida);
    }

    public function test_dejar_un_desafio_cuenta_como_un_intento_y_no_como_pasado(): void
    {
        $jugador = Jugador::factory()->create();
        $partida = $this->mesa()->abrirDesafio($jugador, Desafios::de('contraflor'));

        $this->mesa()->abandonar($partida);

        $progreso = Progreso::query()->where('jugador_id', $jugador->id)->sole();

        $this->assertSame(Partida::ABANDONADA, $partida->refresh()->estado);
        $this->assertSame(1, $progreso->intentos);
        $this->assertFalse($progreso->superado());
        $this->assertSame([], (new Progresos)->superados($jugador));
    }

    public function test_un_desafio_no_cuenta_para_el_ranking_ni_tiene_revancha(): void
    {
        // Contra el difícil, que en una partida común sí cuenta para el ranking.
        $ficha = Desafios::de('los-tres-que-faltan');
        $jugador = Jugador::factory()->create();
        $partida = $this->jugar($this->mesa()->abrirDesafio($jugador, $ficha), $this->resolver($ficha)['cumple']);

        $this->assertSame(Mesa::JUGADOR, $partida->ganador);
        $this->assertFalse((new Estadisticas)->cuenta($partida));
        $this->assertSame(0, Resultado::query()->count());
        $this->assertSame('no_disponible', $this->app->make(Revanchas::class)->estado($partida, Mesa::JUGADOR)['estado']);
    }

    public function test_con_una_partida_sin_terminar_no_se_abre_otra(): void
    {
        $jugador = Jugador::factory()->create();
        $comun = $this->mesa()->abrir($jugador);

        // Se devuelve la que ya tenía, sea la que sea: una persona juega una sola a la vez.
        $this->assertTrue($this->mesa()->abrirDesafio($jugador, Desafios::de('contraflor'))->is($comun));
        $this->assertTrue($this->mesa()->abrirEnEscalera($jugador, Nivel::Facil, 1)->is($comun));
        $this->assertSame(1, Partida::query()->count());
    }
}
