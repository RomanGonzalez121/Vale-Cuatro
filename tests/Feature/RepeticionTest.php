<?php

namespace Tests\Feature;

use App\Juego\Repeticion;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La repetición sale de los eventos y de nada más: muestra el mismo tanteo que tuvo la partida, y de las
 * cartas del rival muestra lo que corresponde (todas las del bot; de otra persona, solo las que se vieron).
 */
class RepeticionTest extends TestCase
{
    use JugandoPartidas;
    use RefreshDatabase;

    public function test_al_empezar_cada_mano_muestra_el_tanteo_que_tuvo_la_partida(): void
    {
        // Hace falta una partida de varias manos, y una puede cerrarse en la primera (un falta envido
        // querido de entrada): se juega otra si pasa eso.
        for ($jugadas = 0; $jugadas < 20; $jugadas++) {
            [$partida, $alRepartir] = $this->partidaContraElBot(Jugador::factory()->invitado()->create());

            if (count($alRepartir) > 1) {
                break;
            }
        }

        $final = $this->laMesa()->reconstruir($partida)->tanteo();

        ['cuadros' => $cuadros, 'manos' => $manos] = $this->repeticion()->de($partida, 0);

        // Una entrada por mano jugada, en orden, y cada una apunta al cuadro donde se reparte esa mano.
        $this->assertSame(array_keys($alRepartir), array_map(fn (int $cuadro) => $cuadros[$cuadro]['mano'], $manos));
        $this->assertGreaterThan(1, count($manos));

        // Ir a cualquier mano, hacia adelante o hacia atrás, es ir a su cuadro: ahí el tanteo es el que se vio jugando.
        foreach (array_values($alRepartir) as $numero => $tanteo) {
            $this->assertSame(['vos' => $tanteo[0], 'rival' => $tanteo[1]], $cuadros[$manos[$numero]]['tanteo'], 'Mano '.($numero + 1));
        }

        // El tanteo nunca baja de un cuadro al siguiente, y el último es el final de la partida.
        foreach ($cuadros as $i => $cuadro) {
            if ($i > 0) {
                $this->assertGreaterThanOrEqual($cuadros[$i - 1]['tanteo']['vos'], $cuadro['tanteo']['vos']);
                $this->assertGreaterThanOrEqual($cuadros[$i - 1]['tanteo']['rival'], $cuadro['tanteo']['rival']);
            }
        }

        $ultimo = $cuadros[count($cuadros) - 1];
        $this->assertSame(['vos' => $final[0], 'rival' => $final[1]], $ultimo['tanteo']);
        $this->assertSame($partida->ganador === 0 ? 'vos' : 'rival', $ultimo['fin']);
        // Lo último que se lee es el resultado, y recién ahí la partida figura como terminada.
        $this->assertStringContainsString("ganó la partida, {$final[0]} a {$final[1]}.", str_replace('Ganaste', 'ganó', $ultimo['texto']));
        $this->assertNull($cuadros[count($cuadros) - 2]['fin']);
        $this->assertSame(30, max($final));
    }

    public function test_no_guarda_nada_y_da_siempre_lo_mismo(): void
    {
        [$partida] = $this->partidaContraElBot(Jugador::factory()->invitado()->create());
        $antes = [Partida::count(), EventoDePartida::count(), $partida->fresh()->updated_at->toISOString()];
        $escrituras = 0;

        DB::listen(function ($consulta) use (&$escrituras) {
            $escrituras += preg_match('/^\s*(insert|update|delete)/i', $consulta->sql);
        });

        $una = $this->repeticion()->de($partida, 0);
        $otra = $this->repeticion()->de($partida->fresh(), 0);

        $this->assertSame($una, $otra);
        $this->assertSame(0, $escrituras, 'Armar la repetición no escribe en la base.');
        $this->assertSame($antes, [Partida::count(), EventoDePartida::count(), $partida->fresh()->updated_at->toISOString()]);
    }

    public function test_contra_el_bot_se_ven_las_cartas_de_los_dos(): void
    {
        [$partida] = $this->partidaContraElBot(Jugador::factory()->invitado()->create());
        ['cuadros' => $cuadros, 'manos' => $manos] = $this->repeticion()->de($partida, 0);
        $repartos = $partida->eventos()->get()->where('tipo', EventoDePartida::REPARTO)->values();

        foreach ($manos as $numero => $cuadro) {
            $this->assertSame($repartos[$numero]->datos['manos'][0], $cuadros[$cuadro]['propias']);
            $this->assertSame($repartos[$numero]->datos['manos'][1], $cuadros[$cuadro]['rival'], 'Las cartas del bot se ven desde el reparto.');
        }
    }

    public function test_entre_personas_solo_se_ven_las_cartas_del_rival_que_se_vieron_en_la_mesa(): void
    {
        $revisados = 0;

        // Una partida puede terminar en un par de manos (un falta envido querido de entrada): se juegan
        // las que hagan falta para revisar una cantidad de cuadros que valga la pena.
        for ($jugadas = 0; $revisados <= 200 && $jugadas < 20; $jugadas++) {
            $partida = $this->partidaEntrePersonas(Jugador::factory()->invitado()->create(), Jugador::factory()->invitado()->create());
            $revisados += $this->revisarLoQueSeVeDelRival($partida);
        }

        $this->assertGreaterThan(200, $revisados);
    }

    /**
     * Recorre la repetición de una partida entre personas desde los dos asientos y comprueba, cuadro por
     * cuadro, que del rival solo se ve lo que jugó o lo que mostró al cerrar. Devuelve cuántos cuadros miró.
     */
    private function revisarLoQueSeVeDelRival(Partida $partida): int
    {
        $repartos = $partida->eventos()->get()->where('tipo', EventoDePartida::REPARTO)->values();
        $revisados = 0;

        foreach ([0, 1] as $asiento) {
            ['cuadros' => $cuadros] = $this->repeticion()->de($partida, $asiento);
            $deLaMano = [];
            $cerrada = false;

            foreach ($cuadros as $cuadro) {
                // Lo que el rival tenía en esta mano, y si la mano ya se cerró (ahí puede mostrar cartas).
                $reparto = $repartos[$cuadro['mano'] - 1]->datos['manos'];
                $cerrada = ($deLaMano['numero'] ?? null) === $cuadro['mano'] && ($cerrada || str_contains($cuadro['texto'], 'ganó la mano') || str_contains($cuadro['texto'], 'Ganaste la mano'));
                $deLaMano = ['numero' => $cuadro['mano']];

                $jugadasPorElRival = array_filter(array_column($cuadro['bazas'], 'rival'));
                $enSuLugar = array_filter($cuadro['rival'], fn (?string $carta) => $carta !== null && $carta !== 'dorso');

                // Antes del cierre, en el lugar del rival hay dorsos o nada: ninguna carta a la vista.
                if (! $cerrada) {
                    $this->assertSame([], array_values($enSuLugar), "Asiento {$asiento}, mano {$cuadro['mano']}: se ve una carta del rival antes de que la muestre.");
                }

                // En todo el cuadro, del rival solo aparece lo que jugó o lo que mostró al cerrar.
                preg_match_all('/"(\d+-(?:espada|basto|oro|copa))"/', (string) json_encode($cuadro), $visibles);
                $delRival = array_intersect($visibles[1], $reparto[1 - $asiento]);

                $this->assertSame([], array_values(array_diff($delRival, $jugadasPorElRival, $enSuLugar)));
                $revisados++;
            }
        }

        return $revisados;
    }

    public function test_lo_que_el_rival_mostro_por_el_envido_se_ve_al_cerrar_la_mano_y_lo_demas_no(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'Roman']), Jugador::factory()->create(['apodo' => 'La Tana'])];
        // El asiento 0 es mano y tiene 33 de envido; el 1 tiene 29.
        $partida = $this->partidaArmadaEntrePersonas($uno, $dos, [['7-oro', '6-oro', '1-espada'], ['5-copa', '4-copa', '3-basto']]);
        $mesa = $this->laMesa();

        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'envido']), 0);
        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'quiero']), 1);
        $mesa->actuar($partida, Accion::jugar(Carta::de('6-oro')), 0);
        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'truco']), 1);
        $mesa->actuar($partida, Accion::desdeArray(['tipo' => 'no_quiero']), 0);
        $mesa->abandonar($partida->fresh(), 1);

        // Desde el asiento 0: lo que pasó, dicho en orden y con el apodo del rival.
        $cuadros = $this->repeticion()->de($partida->fresh(), 0)['cuadros'];

        $this->assertSame([
            'Mano 1. Sos mano.',
            'Cantaste envido.',
            'La Tana quiso.',
            'Cantaste 33. La Tana dijo: son buenas.',
            'Sumaste 2: envido.',
            'Jugaste el 6 de oro.',
            'La Tana cantó truco.',
            'No quisiste.',
            'La Tana sumó 1: truco no querido.',
            'La Tana ganó la mano.',
            'La Tana abandonó la partida.',
        ], array_column($cuadros, 'texto'));

        $this->assertSame(['quien' => 'vos', 'texto' => 'Envido', 'tono' => 'oro'], $cuadros[1]['canto']);
        $this->assertSame(['quien' => 'rival', 'texto' => 'Quiero', 'tono' => 'basto'], $cuadros[2]['canto']);
        $this->assertSame(['quien' => 'rival', 'texto' => 'Truco', 'tono' => 'copa'], $cuadros[6]['canto']);
        $this->assertSame(['quien' => 'vos', 'texto' => 'No quiero', 'tono' => 'copa'], $cuadros[7]['canto']);
        $this->assertNull($cuadros[8]['canto'], 'El canto se va con el cuadro siguiente.');

        $this->assertSame(['vos' => 2, 'rival' => 0], $cuadros[4]['tanteo']);
        $this->assertSame(['7-oro', null, '1-espada'], $cuadros[5]['propias']);
        $this->assertSame('6-oro', $cuadros[5]['bazas'][0]['vos']);
        $this->assertSame(['vos' => 2, 'rival' => 1], $cuadros[10]['tanteo']);
        $this->assertSame('vos', $cuadros[10]['fin'], 'Quien se queda gana la partida que el otro abandona.');

        // La Tana no jugó ni mostró ninguna carta: sus tres nunca aparecen.
        $this->assertDoesNotMatchRegularExpression('/5-copa|4-copa|3-basto/', (string) json_encode($cuadros));

        // Desde el asiento 1: quien ganó el envido muestra las cartas de su tanto. La que ya había jugado
        // está en la baza; la otra se da vuelta al cerrar. Su ancho de espada nunca se vio, y no aparece.
        $delOtro = $this->repeticion()->de($partida->fresh(), 1)['cuadros'];

        $this->assertSame('Mano 1. Es mano Roman.', $delOtro[0]['texto']);
        $this->assertSame(['dorso', 'dorso', 'dorso'], $delOtro[0]['rival']);
        $this->assertSame(['dorso', 'dorso', null], $delOtro[5]['rival']);
        $this->assertSame(['7-oro', null, null], $delOtro[9]['rival']);
        $this->assertSame('Abandonaste la partida.', $delOtro[10]['texto']);
        $this->assertStringNotContainsString('1-espada', (string) json_encode($delOtro));
    }

    public function test_un_turno_vencido_y_la_llegada_a_la_mesa_se_cuentan_como_lo_que_fueron(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'Roman']), Jugador::factory()->create(['apodo' => 'La Tana'])];
        $mesa = $this->laMesa();
        $partida = $mesa->sentarse($mesa->crearSala($uno)->codigo, $dos);

        // Quien abrió la sala llega a la mesa (eso no es una jugada) y después a alguien se le vence el turno.
        $mesa->llegar($partida, 0);
        $conTurno = $mesa->vista($partida->fresh(), 0)['acciones'] !== [] ? 0 : 1;
        $this->travelTo($partida->fresh()->plazo_vence_en);
        $mesa->resolverPlazo($partida->id);
        $mesa->abandonar($partida->fresh(), 1);

        $textos = array_column($this->repeticion()->de($partida->fresh(), 0)['cuadros'], 'texto');

        // La llegada no aparece: de los cuatro eventos guardados salen el reparto, el vencimiento con sus puntos y el abandono.
        $this->assertSame('Mano 1. '.($partida->fresh()->primer_mano === 0 ? 'Sos mano.' : 'Es mano La Tana.'), $textos[0]);
        $this->assertSame($conTurno === 0 ? 'Se te venció el turno.' : 'A La Tana se le venció el turno.', $textos[1]);
        $this->assertSame('La Tana abandonó la partida.', $textos[count($textos) - 1]);
        $this->assertNotContains('', $textos);
    }

    private function repeticion(): Repeticion
    {
        return $this->app->make(Repeticion::class);
    }
}
