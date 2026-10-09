<?php

namespace Tests\Feature;

use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;

/**
 * Partidas enteras jugadas por la mesa, para los tests que necesitan una partida cerrada de verdad:
 * la repetición y el historial. Las jugadas se eligen siempre igual (sin azar propio), variando entre
 * lo que el motor permite, para que aparezcan cartas, cantos, respuestas y mazos.
 */
trait JugandoPartidas
{
    private function laMesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida contra el bot jugada hasta el final. Devuelve la partida y el tanteo que tenía al
     * repartirse cada mano, tal como lo vio el jugador mientras jugaba.
     *
     * @return array{0: Partida, 1: array<int, array{0: int, 1: int}>}
     */
    private function partidaContraElBot(Jugador $jugador, Nivel $nivel = Nivel::Facil): array
    {
        $mesa = $this->laMesa();
        $partida = $mesa->abrir($jugador, $nivel);
        $alRepartir = [];

        for ($paso = 0; $paso < 6000; $paso++) {
            $partida->refresh();

            if (! $partida->enCurso()) {
                return [$partida, $alRepartir];
            }

            $vista = $mesa->vista($partida);
            $alRepartir[$vista['numeroDeMano']] ??= $vista['tanteo'];

            match (true) {
                $vista['fase'] === 'por_repartir' => $mesa->repartir($partida),
                // Con la cola de los tests el bot juega solo; si igual quedó su turno pendiente, se lo despierta.
                $vista['acciones'] === [] => $mesa->despertarAlBot($partida),
                default => $mesa->actuar($partida, Accion::desdeArray($this->elegir($vista['acciones'], $paso))),
            };
        }

        $this->fail('La partida contra el bot no terminó.');
    }

    /**
     * Una partida entre dos personas jugada hasta el final, con los dos en la mesa.
     */
    private function partidaEntrePersonas(Jugador $uno, Jugador $dos): Partida
    {
        $mesa = $this->laMesa();
        $partida = $mesa->sentarse($mesa->crearSala($uno)->codigo, $dos);
        $mesa->llegar($partida, 0);

        for ($paso = 0; $paso < 6000; $paso++) {
            $partida->refresh();

            if (! $partida->enCurso()) {
                return $partida;
            }

            foreach ([0, 1] as $asiento) {
                $vista = $mesa->vista($partida, $asiento);

                if ($vista['fase'] === 'por_repartir') {
                    $mesa->repartir($partida, $asiento);

                    break;
                }

                if ($vista['acciones'] !== []) {
                    $mesa->actuar($partida, Accion::desdeArray($this->elegir($vista['acciones'], $paso)), $asiento);

                    break;
                }
            }
        }

        $this->fail('La partida entre personas no terminó.');
    }

    /**
     * Una partida entre dos personas jugada hasta que uno de los dos llega a esos puntos, todavía sin terminar.
     * Devuelve la partida y el asiento que va adelante. Si una partida se termina antes (un falta envido
     * querido la puede cerrar de un salto), se juega otra.
     *
     * @return array{0: Partida, 1: int}
     */
    private function partidaEntrePersonasHasta(Jugador $uno, Jugador $dos, int $puntos): array
    {
        $mesa = $this->laMesa();

        for ($intento = 0; $intento < 20; $intento++) {
            $partida = $mesa->sentarse($mesa->crearSala($uno)->codigo, $dos);
            $mesa->llegar($partida, 0);

            for ($paso = 0; $paso < 6000; $paso++) {
                $partida->refresh();

                if (! $partida->enCurso()) {
                    break;
                }

                $tanteo = $mesa->reconstruir($partida)->tanteo();

                if (max($tanteo) >= $puntos) {
                    return [$partida, $tanteo[0] >= $tanteo[1] ? 0 : 1];
                }

                foreach ([0, 1] as $asiento) {
                    $vista = $mesa->vista($partida, $asiento);

                    if ($vista['fase'] === 'por_repartir') {
                        $mesa->repartir($partida, $asiento);

                        break;
                    }

                    if ($vista['acciones'] !== []) {
                        $mesa->actuar($partida, Accion::desdeArray($this->elegir($vista['acciones'], $paso)), $asiento);

                        break;
                    }
                }
            }
        }

        $this->fail('Ninguna partida llegó a esos puntos sin terminar.');
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
     * Qué jugar: casi siempre una carta, y cada tanto otra de las cosas que el motor permite (cantar,
     * querer, no querer, irse), para que la partida tenga de todo.
     *
     * @param  list<array{tipo: string, carta?: string}>  $acciones
     * @return array{tipo: string, carta?: string}
     */
    private function elegir(array $acciones, int $paso): array
    {
        $cartas = array_values(array_filter($acciones, fn (array $accion) => $accion['tipo'] === 'jugar'));

        if ($cartas !== [] && $paso % 5 !== 0) {
            return $cartas[$paso % count($cartas)];
        }

        return $acciones[$paso % count($acciones)];
    }
}
