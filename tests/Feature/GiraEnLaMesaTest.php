<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\Gira;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Progresos;
use App\Juego\Torneos;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Models\Resultado;
use App\Motor\Accion;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La gira de los clubes en la mesa de verdad: el candado de los niveles, las partidas que se abren y los
 * fósforos que deja cada una, contados desde sus eventos.
 */
class GiraEnLaMesaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La mesa juega contra un bot de prueba: si $seVa, se va al mazo apenas le toca; si no, tira una carta.
     */
    private function mesaContraUnBotDePrueba(bool $seVa): Mesa
    {
        $mesa = new Mesa(new class($seVa) implements Bot
        {
            public function __construct(private readonly bool $seVa) {}

            public function decidir(array $vista): Accion
            {
                return $this->seVa
                    ? Accion::de(TipoDeAccion::Mazo)
                    : Accion::desdeArray(collect($vista['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value));
            }
        });
        $this->app->instance(Mesa::class, $mesa);

        return $mesa;
    }

    /**
     * Juega la partida hasta que se cierra: si $gana, tira una carta cada vez (y el bot se va); si no, se va al mazo.
     */
    private function jugar(Mesa $mesa, Partida $partida, bool $gana): Partida
    {
        while ($partida->refresh()->enCurso()) {
            $vista = $mesa->vista($partida);

            if ($vista['fase'] === 'por_repartir') {
                $mesa->repartir($partida);

                continue;
            }

            $carta = collect($vista['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value);
            $mesa->actuar($partida, $gana ? Accion::desdeArray($carta) : Accion::de(TipoDeAccion::Mazo));
        }

        return $partida;
    }

    public function test_los_niveles_se_abren_en_orden_y_un_barrio_se_abre_ganandole_al_jefe_del_anterior(): void
    {
        $jugador = Jugador::factory()->create();

        $this->assertTrue(Gira::abierto($jugador, 'boedo', 1));
        $this->assertFalse(Gira::abierto($jugador, 'boedo', 2));
        $this->assertFalse(Gira::abierto($jugador, 'san-telmo', 1));
        $this->assertFalse(Gira::abierto($jugador, 'boedo', 9), 'Boedo tiene ocho niveles.');
        $this->assertFalse(Gira::abierto(null, 'boedo', 2));

        // Boedo es la escalera: lo pasado de la escalera vale tal cual.
        Progreso::query()->create(['jugador_id' => $jugador->id, 'clave' => 'escalon:1'])->forceFill(['superado_en' => now(), 'fosforos' => 1])->save();
        $this->assertTrue(Gira::abierto($jugador, 'boedo', 2));

        // Ganarle a Don Anselmo (el escalón 8) abre San Telmo, solo su primer nivel.
        Progreso::query()->create(['jugador_id' => $jugador->id, 'clave' => 'escalon:8'])->forceFill(['superado_en' => now(), 'fosforos' => 2])->save();
        $this->assertTrue(Gira::abierto($jugador, 'san-telmo', 1));
        $this->assertFalse(Gira::abierto($jugador, 'san-telmo', 2));
        $this->assertFalse(Gira::abierto($jugador, 'la-boca', 1));
    }

    public function test_una_partida_de_la_gira_se_abre_con_su_rival_sus_puntos_y_no_cuenta_para_el_ranking(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->mesaContraUnBotDePrueba(seVa: true);

        $partida = $mesa->abrirEnGira($jugador, 'san-telmo', 1);

        $this->assertSame('san-telmo:1', $partida->gira);
        $this->assertSame(15, $partida->puntos);
        $this->assertSame(Nivel::Intermedio, $partida->nivel_bot, 'Tito Pereyra es de la máquina intermedia.');
        $this->assertTrue($partida->esDePractica());

        // Se gana sin irse nunca al mazo y por mucho: los tres fósforos.
        $this->jugar($mesa, $partida, gana: true);

        $this->assertSame(Mesa::JUGADOR, $partida->ganador);
        $this->assertSame(3, Gira::fosforos($jugador)['gira:san-telmo:1'] ?? null);
        $this->assertTrue(Gira::pasado($jugador, 'san-telmo', 1));
        $this->assertSame(0, Resultado::query()->count(), 'La gira no cuenta para el ranking.');
    }

    public function test_perder_no_da_fosforos_y_queda_el_mejor_intento(): void
    {
        $jugador = Jugador::factory()->create();

        $perdedora = $this->mesaContraUnBotDePrueba(seVa: false);
        $this->jugar($perdedora, $perdedora->abrirEnGira($jugador, 'san-telmo', 1), gana: false);

        $progreso = Progreso::query()->where('clave', 'gira:san-telmo:1')->sole();
        $this->assertSame([1, 0], [$progreso->intentos, $progreso->fosforos]);
        $this->assertFalse(Gira::abierto($jugador, 'san-telmo', 2));

        $ganadora = $this->mesaContraUnBotDePrueba(seVa: true);
        $this->jugar($ganadora, $ganadora->abrirEnGira($jugador, 'san-telmo', 1), gana: true);

        // Un intento peor después no le quita los fósforos que ya tenía.
        $perdedora = $this->mesaContraUnBotDePrueba(seVa: false);
        $this->jugar($perdedora, $perdedora->abrirEnGira($jugador, 'san-telmo', 1), gana: false);

        $progreso->refresh();
        $this->assertSame([3, 3], [$progreso->intentos, $progreso->fosforos]);
        $this->assertTrue(Gira::abierto($jugador, 'san-telmo', 2));
    }

    public function test_un_nivel_que_arranca_abajo_guarda_su_tanteo_y_se_reconstruye_igual(): void
    {
        $jugador = Jugador::factory()->create();
        // Un bot que tira una carta: así, sea quien sea mano, nadie suma antes de mirar el tanteo.
        $mesa = $this->mesaContraUnBotDePrueba(seVa: false);

        // Pompeya, nivel 3: contra El Zurdo Medina, arrancando 0 a 8.
        $partida = $mesa->abrirEnGira($jugador, 'pompeya', 3);

        $this->assertSame([0, 8], $mesa->vista($partida)['tanteo']);
        $this->assertSame([0, 8], $mesa->reconstruir($partida->fresh())->tanteo());
    }

    public function test_los_niveles_de_boedo_son_los_escalones_y_anotan_sus_fosforos(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->mesaContraUnBotDePrueba(seVa: true);

        $partida = $mesa->abrirEnGira($jugador, 'boedo', 1);
        $this->assertSame(1, $partida->escalon);
        $this->assertNull($partida->gira);

        $this->jugar($mesa, $partida, gana: true);

        $this->assertSame(3, Gira::fosforos($jugador)['escalon:1'] ?? null);
        $this->assertSame(2, (new Progresos)->escalonActual($jugador), 'La escalera sigue andando como siempre.');
    }

    public function test_un_nivel_de_a_cuatro_se_juega_con_companero_y_anota_a_la_pareja(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->mesaContraUnBotDePrueba(seVa: true);

        // San Telmo, nivel 3: de a cuatro, a 15, contra la máquina intermedia.
        $partida = $mesa->abrirEnGira($jugador, 'san-telmo', 3);

        $this->assertSame(4, $partida->asientos);
        $this->assertSame('san-telmo:3', $partida->gira);
        $this->assertSame(15, $partida->puntos);

        $this->jugar($mesa, $partida, gana: true);

        // Ganó la pareja por mucho, sin los dos envidos: dos fósforos.
        $this->assertSame(Mesa::JUGADOR, $partida->ganador);
        $this->assertSame(2, Gira::fosforos($jugador)['gira:san-telmo:3'] ?? null);
        $this->assertSame(0, Resultado::query()->count(), 'La gira no cuenta para el ranking.');
    }

    public function test_un_nivel_al_mejor_de_tres_se_anota_cuando_termina_la_serie(): void
    {
        $jugador = Jugador::factory()->create();

        // San Telmo, nivel 5: al mejor de tres contra La Colorada. Se pierde la primera.
        $perdedora = $this->mesaContraUnBotDePrueba(seVa: false);
        $primera = $perdedora->abrirEnGira($jugador, 'san-telmo', 5);
        $this->assertNotNull($primera->serie_id);
        $this->jugar($perdedora, $primera, gana: false);

        // Con la serie abierta no se anota nada: ni un intento.
        $this->assertSame(0, Progreso::query()->where('clave', 'gira:san-telmo:5')->count());
        $segunda = Partida::query()->where('anterior_id', $primera->id)->sole();
        $this->assertSame([$primera->serie_id, 'san-telmo:5'], [$segunda->serie_id, $segunda->gira]);

        // Se ganan las dos que siguen: se pasa, pero perdiendo una partida no da el tercer fósforo.
        $ganadora = $this->mesaContraUnBotDePrueba(seVa: true);
        $this->jugar($ganadora, $segunda, gana: true);
        $this->jugar($ganadora, Partida::query()->where('anterior_id', $segunda->id)->sole(), gana: true);

        $progreso = Progreso::query()->where('clave', 'gira:san-telmo:5')->sole();
        $this->assertSame([1, 2], [$progreso->intentos, $progreso->fosforos]);
        $this->assertNotNull($progreso->superado_en);
        $this->assertTrue(Gira::abierto($jugador, 'san-telmo', 6));

        // Dos a cero: los tres fósforos, y la serie no tiene una tercera.
        $otra = $ganadora->abrirEnGira($jugador, 'san-telmo', 5);
        $this->jugar($ganadora, $otra, gana: true);
        $this->jugar($ganadora, Partida::query()->where('anterior_id', $otra->id)->sole(), gana: true);

        $this->assertSame(3, Gira::fosforos($jugador)['gira:san-telmo:5']);
        $this->assertSame(2, Partida::query()->where('serie_id', $otra->serie_id)->count());
    }

    public function test_la_copa_del_barrio_se_pasa_saliendo_campeon_y_no_cuenta_para_el_ranking(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->mesaContraUnBotDePrueba(seVa: true);
        $torneos = new Torneos($mesa);

        // La Boca, nivel 5: la copa, un torneo de cuatro a 15 contra la máquina difícil.
        $torneo = $torneos->crear($jugador, 4, 7, 'la-boca:5', Nivel::Dificil, 15);
        $this->assertSame('la-boca:5', $torneo->gira);
        $this->assertSame([3, 3, 3], array_values(array_filter(array_column($torneo->inscriptos, 'nivel'))));

        while ($torneo->refresh()->enCurso()) {
            $this->jugar($mesa, $torneos->jugar($torneo), gana: true);
            $torneos->avanzar($torneo->id);
        }

        $this->assertSame($torneo->lugarDeLaPersona(), $torneo->campeon);
        $this->assertSame(3, Gira::fosforos($jugador)['gira:la-boca:5'] ?? null);
        $this->assertSame(0, Resultado::query()->count(), 'La copa es de la gira: no cuenta para el ranking.');

        // Quedar afuera es un intento más y no le quita los fósforos.
        $otro = $torneos->crear($jugador, 4, 8, 'la-boca:5', Nivel::Dificil, 15);
        $this->jugar($this->mesaContraUnBotDePrueba(seVa: false), $torneos->jugar($otro), gana: false);
        $torneos->avanzar($otro->id);

        $progreso = Progreso::query()->where('clave', 'gira:la-boca:5')->sole();
        $this->assertSame([2, 3], [$progreso->intentos, $progreso->fosforos]);
    }

    public function test_la_copa_no_se_abre_en_la_mesa(): void
    {
        $this->expectException(\LogicException::class);

        $this->mesaContraUnBotDePrueba(seVa: true)->abrirEnGira(Jugador::factory()->create(), 'la-boca', 5);
    }

    public function test_el_jefe_juega_con_su_mania_y_los_demas_no(): void
    {
        $jugador = Jugador::factory()->create();
        $mesa = $this->mesaContraUnBotDePrueba(seVa: true);

        $this->assertSame('nunca_se_va', Gira::maniaDe($mesa->abrirEnGira($jugador, 'san-telmo', 6)));
        $mesa->abandonar(Partida::query()->latest('id')->first());
        $this->assertNull(Gira::maniaDe($mesa->abrirEnGira($jugador, 'san-telmo', 1)), 'Un rival común no tiene manía.');
        $mesa->abandonar(Partida::query()->latest('id')->first());
        $this->assertNull(Gira::maniaDe($mesa->abrirEnGira($jugador, 'boedo', 8)), 'Don Anselmo no tiene manía.');
        $mesa->abandonar(Partida::query()->latest('id')->first());
        $this->assertNull(Gira::maniaDe($mesa->abrirEnGira($jugador, 'abasto', 6)), 'Arrancar con ventaja es un dato de la partida.');
    }
}
