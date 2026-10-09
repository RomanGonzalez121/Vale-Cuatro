<?php

namespace Tests\Feature;

use App\Juego\Estadisticas;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Simulacion;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\Carta;
use App\Motor\Partida as Motor;

/**
 * Partidas ya jugadas, para los tests que necesitan una partida cerrada de verdad: el historial, la
 * repetición y el ranking.
 *
 * No se juegan por la mesa, jugada por jugada contra la base: se juegan en memoria, con el motor y dos
 * bots, y se guardan de una vez con sus eventos, igual que queda una partida real. Así cada test tarda
 * una fracción de segundo en vez de varios, y como todo sale de una semilla, la partida es siempre la
 * misma: ningún test depende de la suerte del reparto.
 *
 * Que la mesa juega bien una partida entera lo prueban MesaTest y MesaEntrePersonasTest, por el camino real.
 */
trait JugandoPartidas
{
    /** La semilla de la próxima partida que no pida una: cada partida de un mismo test es distinta. */
    private int $proximaSemilla = 100;

    private function laMesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida contra el bot jugada hasta el final. Devuelve la partida y el tanteo que había al
     * repartirse cada mano. En el asiento del jugador juega un bot que tira cualquier cosa válida:
     * así aparecen cartas, cantos, respuestas y mazos.
     *
     * @return array{0: Partida, 1: array<int, array{0: int, 1: int}>}
     */
    private function partidaContraElBot(Jugador $jugador, Nivel $nivel = Nivel::Intermedio, ?int $semilla = null): array
    {
        [$primerMano, $eventos, $ganador] = (new Simulacion)->enElMotor(Nivel::Facil, $nivel, $semilla ?? $this->proximaSemilla++);

        $partida = $this->guardarPartida($jugador, null, $nivel, $primerMano, $eventos, $ganador);

        return [$partida, $this->tanteoAlRepartir($primerMano, $eventos)];
    }

    /**
     * Una partida entre dos personas jugada hasta el final. En el asiento 1 juega un bot que juega de
     * frente y en el 0 uno que tira cualquier cosa: con esa pareja las partidas salen largas y variadas.
     */
    private function partidaEntrePersonas(Jugador $uno, Jugador $dos, ?int $semilla = null): Partida
    {
        [$primerMano, $eventos, $ganador] = (new Simulacion)->enElMotor(Nivel::Facil, Nivel::Intermedio, $semilla ?? $this->proximaSemilla++);

        return $this->guardarPartida($uno, $dos, null, $primerMano, $eventos, $ganador);
    }

    /**
     * Una partida entre dos personas jugada hasta que uno de los dos llega a esos puntos, y todavía en
     * curso: lo que sigue (abandonar, por ejemplo) se hace por la mesa. Devuelve la partida y el asiento
     * que va adelante.
     *
     * @return array{0: Partida, 1: int}
     */
    private function partidaEntrePersonasHasta(Jugador $uno, Jugador $dos, int $puntos, int $semilla = 100): array
    {
        [$primerMano, $eventos] = (new Simulacion)->enElMotor(Nivel::Facil, Nivel::Intermedio, $semilla);
        $motor = Motor::nueva(2, $primerMano, 30);

        foreach ($eventos as $indice => $evento) {
            $motor = $this->aplicar($motor, $evento);
            $tanteo = $motor->tanteo();

            if (max($tanteo) >= $puntos && max($tanteo) < 30) {
                $partida = $this->guardarPartida($uno, $dos, null, $primerMano, array_slice($eventos, 0, $indice + 1), null);

                return [$partida, $tanteo[0] >= $tanteo[1] ? 0 : 1];
            }
        }

        $this->fail("La partida de la semilla {$semilla} no pasa por {$puntos} puntos sin terminar: elegí otra.");
    }

    /**
     * Una partida entre dos personas con las cartas que se indiquen y el asiento 0 de mano, sin jugar todavía.
     *
     * @param  array{0: list<string>, 1: list<string>}  $manos  Las del asiento 0 y las del 1.
     */
    private function partidaArmadaEntrePersonas(Jugador $uno, Jugador $dos, array $manos): Partida
    {
        $partida = Partida::create([
            'jugador_id' => $uno->id,
            'invitado_id' => $dos->id,
            'entre_personas' => true,
            'codigo' => Partida::codigoNuevo(),
            'nivel_bot' => null,
            'primer_mano' => Mesa::JUGADOR,
            'puntos' => 30,
        ]);

        $partida->eventos()->create(['numero' => 1, 'tipo' => EventoDePartida::REPARTO, 'datos' => ['manos' => $manos], 'creado_en' => now()]);

        return $partida;
    }

    /**
     * Guarda una partida con sus eventos. Con ganador queda terminada y anotada para el ranking, como
     * la deja la mesa al cerrarla; sin ganador queda en curso.
     *
     * @param  list<array{0: string, 1: int|null, 2: array<string, mixed>}>  $eventos
     */
    private function guardarPartida(Jugador $uno, ?Jugador $dos, ?Nivel $nivel, int $primerMano, array $eventos, ?int $ganador): Partida
    {
        $partida = new Partida([
            'jugador_id' => $uno->id,
            'invitado_id' => $dos?->id,
            'entre_personas' => $dos !== null,
            'codigo' => $dos === null ? null : Partida::codigoNuevo(),
            'nivel_bot' => $nivel,
            'primer_mano' => $primerMano,
            'puntos' => 30,
        ]);

        if ($ganador !== null) {
            $partida->estado = Partida::TERMINADA;
            $partida->ganador = $ganador;
            $partida->terminada_en = now();
        }

        $partida->save();

        $filas = [];

        foreach ($eventos as $indice => [$tipo, $asiento, $datos]) {
            $filas[] = [
                'partida_id' => $partida->id,
                'numero' => $indice + 1,
                'tipo' => $tipo,
                'asiento' => $asiento,
                'datos' => json_encode($datos, JSON_THROW_ON_ERROR),
                'creado_en' => now(),
            ];
        }

        foreach (array_chunk($filas, 200) as $tanda) {
            EventoDePartida::query()->insert($tanda);
        }

        if ($ganador !== null) {
            (new Estadisticas)->anotar($partida, $this->laMesa());
        }

        return $partida;
    }

    /**
     * El tanteo que había al repartirse cada mano, por número de mano. Se saca volviendo a pasar los
     * eventos por el motor acá mismo, sin usar nada del historial ni de la repetición: sirve para
     * comprobarlos contra algo que no sean ellos mismos.
     *
     * @param  list<array{0: string, 1: int|null, 2: array<string, mixed>}>  $eventos
     * @return array<int, array{0: int, 1: int}>
     */
    private function tanteoAlRepartir(int $primerMano, array $eventos): array
    {
        $motor = Motor::nueva(2, $primerMano, 30);
        $alRepartir = [];

        foreach ($eventos as $evento) {
            if ($evento[0] === EventoDePartida::REPARTO) {
                $alRepartir[count($alRepartir) + 1] = $motor->tanteo();
            }

            $motor = $this->aplicar($motor, $evento);
        }

        return $alRepartir;
    }

    /**
     * @param  array{0: string, 1: int|null, 2: array<string, mixed>}  $evento
     */
    private function aplicar(Motor $motor, array $evento): Motor
    {
        [$tipo, $asiento, $datos] = $evento;

        return $tipo === EventoDePartida::REPARTO
            ? $motor->conManos(array_map(fn (array $mano) => array_map(Carta::de(...), $mano), $datos['manos']))
            : $motor->aplicar($asiento, Accion::desdeArray($datos));
    }
}
