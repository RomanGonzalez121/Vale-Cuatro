<?php

namespace App\Motor;

use InvalidArgumentException;

/**
 * Una partida de truco: el estado completo y las reglas para pasar al siguiente.
 *
 * Una partida no se modifica nunca. Repartir o aplicar una acción devuelve una
 * partida nueva y deja intacta la anterior. Por eso reconstruir una partida es
 * volver a aplicar sus acciones en orden, y retroceder es aplicar menos.
 */
final class Partida
{
    /** @var array{0: int, 1: int} Los puntos de cada equipo. */
    private array $tanteo = [0, 0];

    private Fase $fase = Fase::PorRepartir;

    /** El equipo que ganó la partida. */
    private ?int $ganador = null;

    private int $numeroDeMano = 0;

    /** @var array<int, list<Carta>> Las tres cartas que recibió cada asiento. */
    private array $repartidas = [];

    /** @var array<int, list<Carta>> Las que cada asiento todavía tiene en la mano. */
    private array $cartas = [];

    /** @var list<array{jugadas: list<array{0: int, 1: Carta}>, ganador: int|null, cerrada: bool}> */
    private array $bazas = [];

    /** El asiento al que le toca jugar una carta. */
    private ?int $turno = null;

    /** @var list<int> Los asientos que se fueron al mazo en esta mano. */
    private array $enMazo = [];

    /** @var list<array{equipo: int, puntos: int, concepto: string}> Lo que se anotó en esta mano, en orden. */
    private array $anotado = [];

    /** @var array<string, mixed>|null El resumen de la última mano cerrada, hasta que se reparte de nuevo. */
    private ?array $cierre = null;

    /** @var list<array<string, mixed>> Lo que pasó con la última acción. Nunca incluye cartas ocultas. */
    private array $hechos = [];

    private function __construct(private Mesa $mesa, private int $mano, private int $puntosParaGanar) {}

    /**
     * Una partida sin cartas repartidas. Quién es mano llega como dato: el motor no sortea.
     */
    public static function nueva(int $asientos = 2, int $mano = 0, int $puntos = 30): self
    {
        $mesa = new Mesa($asientos);

        if (! $mesa->existe($mano)) {
            throw new InvalidArgumentException('El mano tiene que ser un asiento de la mesa.');
        }

        if ($puntos < 1) {
            throw new InvalidArgumentException('La partida se juega al menos a un punto.');
        }

        return new self($mesa, $mano, $puntos);
    }

    /**
     * Una situación armada: arranca desde un tanteo, un mano y unas cartas dadas.
     *
     * @param  list<list<Carta>>  $manos  Las tres cartas de cada asiento.
     * @param  array{0: int, 1: int}  $tanteo
     */
    public static function armada(array $manos, int $mano = 0, array $tanteo = [0, 0], int $puntos = 30): self
    {
        $partida = self::nueva(count($manos), $mano, $puntos);

        if (count($tanteo) !== 2 || min($tanteo) < 0 || max($tanteo) >= $puntos) {
            throw new InvalidArgumentException('El tanteo son dos números, de 0 a menos de los puntos de la partida.');
        }

        $partida->tanteo = array_values($tanteo);

        return $partida->conManos($manos);
    }

    /**
     * Reparte una mano con un mazo ya mezclado: de a una carta, empezando por el mano.
     *
     * @param  list<Carta>  $mazo
     */
    public function repartir(array $mazo): self
    {
        $orden = $this->mesa->rondaDesde($this->mano);
        $necesarias = 3 * $this->mesa->asientos;

        if (count($mazo) < $necesarias) {
            throw new InvalidArgumentException('El mazo no alcanza para repartir.');
        }

        $manos = array_fill(0, $this->mesa->asientos, []);

        foreach (array_slice(array_values($mazo), 0, $necesarias) as $i => $carta) {
            $manos[$orden[$i % $this->mesa->asientos]][] = $carta;
        }

        return $this->conManos($manos);
    }

    /**
     * Empieza una mano con las cartas que se indican para cada asiento.
     *
     * @param  list<list<Carta>>  $manos
     */
    public function conManos(array $manos): self
    {
        if ($this->fase === Fase::Terminada) {
            throw new AccionInvalida('La partida ya terminó: no se reparte más.');
        }

        if ($this->fase === Fase::Jugando) {
            throw new AccionInvalida('La mano todavía se está jugando: no se puede repartir.');
        }

        $this->validarManos($manos);

        $nueva = clone $this;
        $nueva->numeroDeMano++;
        $nueva->fase = Fase::Jugando;
        $nueva->repartidas = $manos;
        $nueva->cartas = $manos;
        $nueva->bazas = [self::bazaVacia()];
        $nueva->turno = $this->mano;
        $nueva->enMazo = [];
        $nueva->anotado = [];
        $nueva->cierre = null;
        $nueva->hechos = [['tipo' => 'reparto', 'numero' => $nueva->numeroDeMano, 'mano' => $this->mano]];

        return $nueva;
    }

    /**
     * Las acciones que el reglamento le permite a un asiento en este momento.
     *
     * @return list<Accion>
     */
    public function accionesPara(int $asiento): array
    {
        $posibles = array_map(Accion::jugar(...), $this->cartas[$asiento] ?? []);

        foreach (TipoDeAccion::cases() as $tipo) {
            if ($tipo !== TipoDeAccion::Jugar) {
                $posibles[] = Accion::de($tipo);
            }
        }

        return array_values(array_filter($posibles, fn (Accion $accion) => $this->motivoDeRechazo($asiento, $accion) === null));
    }

    /**
     * Por qué no se puede hacer esa acción, o null si se puede.
     */
    public function motivoDeRechazo(int $asiento, Accion $accion): ?string
    {
        if (! $this->mesa->existe($asiento)) {
            return "El asiento {$asiento} no existe en esta mesa.";
        }

        if ($this->fase === Fase::Terminada) {
            return 'La partida ya terminó.';
        }

        if ($this->fase === Fase::PorRepartir) {
            return 'La mano está cerrada: falta repartir.';
        }

        if (! $this->estaActivo($asiento)) {
            return 'Ya te fuiste al mazo.';
        }

        if ($asiento !== $this->turno) {
            return 'No es tu turno.';
        }

        return match ($accion->tipo) {
            TipoDeAccion::Jugar => $this->tieneEnLaMano($asiento, $accion->carta) ? null : 'Esa carta no está en tu mano.',
            TipoDeAccion::Mazo => null,
        };
    }

    /**
     * Aplica una acción y devuelve la partida que resulta. Si no es válida, avisa por qué y no cambia nada.
     */
    public function aplicar(int $asiento, Accion $accion): self
    {
        $motivo = $this->motivoDeRechazo($asiento, $accion);

        if ($motivo !== null) {
            throw new AccionInvalida($motivo);
        }

        $nueva = clone $this;
        $nueva->hechos = [];

        match ($accion->tipo) {
            TipoDeAccion::Jugar => $nueva->jugarCarta($asiento, $accion->carta),
            TipoDeAccion::Mazo => $nueva->irseAlMazo($asiento),
        };

        // Si la partida se terminó en la mitad de una mano, la mano queda cerrada ahí.
        if ($nueva->fase === Fase::Terminada && $nueva->cierre === null) {
            $nueva->terminarMano($nueva->ganador, 'partida');
        }

        return $nueva;
    }

    public function fase(): Fase
    {
        return $this->fase;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function tanteo(): array
    {
        return $this->tanteo;
    }

    public function ganador(): ?int
    {
        return $this->ganador;
    }

    /**
     * El asiento que es mano en la mano en juego o, entre dos manos, en la que viene.
     */
    public function mano(): int
    {
        return $this->mano;
    }

    public function turno(): ?int
    {
        return $this->turno;
    }

    public function mesa(): Mesa
    {
        return $this->mesa;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cierre(): ?array
    {
        return $this->cierre;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function hechos(): array
    {
        return $this->hechos;
    }

    /**
     * El estado entero, con las cartas de todos. Es para el servidor y para los tests: nunca se manda a un jugador.
     *
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'asientos' => $this->mesa->asientos,
            'puntosParaGanar' => $this->puntosParaGanar,
            'fase' => $this->fase->value,
            'tanteo' => $this->tanteo,
            'ganador' => $this->ganador,
            'numeroDeMano' => $this->numeroDeMano,
            'mano' => $this->mano,
            'turno' => $this->turno,
            'repartidas' => array_map(self::ids(...), $this->repartidas),
            'cartas' => array_map(self::ids(...), $this->cartas),
            'bazas' => array_map(self::bazaComoArray(...), $this->bazas),
            'enMazo' => $this->enMazo,
            'anotado' => $this->anotado,
            'cierre' => $this->cierre,
            'hechos' => $this->hechos,
        ];
    }

    private function jugarCarta(int $asiento, Carta $carta): void
    {
        $this->cartas[$asiento] = array_values(array_filter($this->cartas[$asiento], fn (Carta $otra) => ! $otra->es($carta)));
        $this->bazas[array_key_last($this->bazas)]['jugadas'][] = [$asiento, $carta];
        $this->hechos[] = ['tipo' => 'carta', 'asiento' => $asiento, 'carta' => $carta->id()];

        $this->pasarElTurno($asiento);
    }

    /**
     * Le da el turno al siguiente que falta jugar en la baza. Si no falta nadie, la cierra.
     */
    private function pasarElTurno(int $desde): void
    {
        $siguiente = $this->siguienteEnLaBaza($desde);

        if ($siguiente === null) {
            $this->cerrarBaza();

            return;
        }

        $this->turno = $siguiente;
    }

    private function siguienteEnLaBaza(int $desde): ?int
    {
        $yaJugaron = array_column($this->bazas[array_key_last($this->bazas)]['jugadas'], 0);

        foreach (array_slice($this->mesa->rondaDesde($desde), 1) as $asiento) {
            if ($this->estaActivo($asiento) && ! in_array($asiento, $yaJugaron, true)) {
                return $asiento;
            }
        }

        return null;
    }

    private function cerrarBaza(): void
    {
        $numero = array_key_last($this->bazas);
        $ganador = Bazas::ganadorDeBaza($this->bazas[$numero]['jugadas'], $this->mesa);

        $this->bazas[$numero]['ganador'] = $ganador;
        $this->bazas[$numero]['cerrada'] = true;
        $this->hechos[] = ['tipo' => 'baza', 'numero' => $numero + 1, 'ganador' => $ganador];

        $resultados = array_map(
            fn (array $baza) => $baza['ganador'] === null ? null : $this->mesa->equipoDe($baza['ganador']),
            $this->bazas,
        );
        $ganaLaMano = Bazas::ganadorDeMano($resultados, $this->mesa->equipoDe($this->mano));

        if ($ganaLaMano !== null) {
            $this->cerrarMano($ganaLaMano, 'bazas');

            return;
        }

        // Abre quien ganó la baza; después de una parda, el mano. Si ese asiento se fue al mazo, el que le sigue.
        $this->bazas[] = self::bazaVacia();
        $this->turno = $this->primerActivoDesde($ganador ?? $this->mano);
    }

    private function irseAlMazo(int $asiento): void
    {
        $enPrimera = ! $this->bazas[0]['cerrada'];
        $equipo = $this->mesa->equipoDe($asiento);

        $this->enMazo[] = $asiento;
        $this->cartas[$asiento] = [];
        $this->hechos[] = ['tipo' => 'mazo', 'asiento' => $asiento];

        // De a cuatro, el compañero sigue solo: el equipo pierde la mano recién cuando se van los dos.
        if ($this->quedaAlguienDe($equipo)) {
            if ($this->turno === $asiento) {
                $this->pasarElTurno($asiento);
            }

            return;
        }

        $this->cerrarMano($this->mesa->rivalDe($equipo), 'mazo', sumaElEnvidoNoJugado: $enPrimera);
    }

    /**
     * Anota lo que vale la mano y la cierra. El punto del envido que no se jugó va primero,
     * como todo lo del envido.
     */
    private function cerrarMano(int $equipo, string $motivo, bool $sumaElEnvidoNoJugado = false): void
    {
        if ($sumaElEnvidoNoJugado) {
            $this->anotar($equipo, 1, 'envido_no_jugado');
        }

        $this->anotar($equipo, $this->valorDeLaMano(), 'mano');
        $this->terminarMano($equipo, $motivo);
    }

    private function valorDeLaMano(): int
    {
        return 1;
    }

    private function terminarMano(int $equipo, string $motivo): void
    {
        $this->cierre = ['ganador' => $equipo, 'motivo' => $motivo, 'anotado' => $this->anotado];
        $this->turno = null;
        $this->hechos[] = ['tipo' => 'mano_cerrada', 'ganador' => $equipo, 'motivo' => $motivo];

        if ($this->fase !== Fase::Terminada) {
            $this->fase = Fase::PorRepartir;
            $this->mano = $this->mesa->rondaDesde($this->mano)[1];
        }
    }

    /**
     * Suma puntos. La partida termina en cuanto alguien llega: lo que venga después ya no se anota.
     */
    private function anotar(int $equipo, int $puntos, string $concepto): void
    {
        if ($this->fase === Fase::Terminada) {
            return;
        }

        $this->tanteo[$equipo] = min($this->puntosParaGanar, $this->tanteo[$equipo] + $puntos);
        $this->anotado[] = ['equipo' => $equipo, 'puntos' => $puntos, 'concepto' => $concepto];
        $this->hechos[] = ['tipo' => 'puntos', 'equipo' => $equipo, 'puntos' => $puntos, 'concepto' => $concepto, 'tanteo' => $this->tanteo];

        if ($this->tanteo[$equipo] >= $this->puntosParaGanar) {
            $this->fase = Fase::Terminada;
            $this->ganador = $equipo;
            $this->hechos[] = ['tipo' => 'partida_terminada', 'ganador' => $equipo];
        }
    }

    private function estaActivo(int $asiento): bool
    {
        return ! in_array($asiento, $this->enMazo, true);
    }

    private function quedaAlguienDe(int $equipo): bool
    {
        return array_filter($this->mesa->asientosDe($equipo), $this->estaActivo(...)) !== [];
    }

    private function primerActivoDesde(int $asiento): int
    {
        foreach ($this->mesa->rondaDesde($asiento) as $candidato) {
            if ($this->estaActivo($candidato)) {
                return $candidato;
            }
        }

        return $asiento;
    }

    private function tieneEnLaMano(int $asiento, ?Carta $carta): bool
    {
        foreach ($this->cartas[$asiento] as $otra) {
            if ($carta !== null && $otra->es($carta)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $manos
     */
    private function validarManos(array $manos): void
    {
        if (! array_is_list($manos) || count($manos) !== $this->mesa->asientos) {
            throw new InvalidArgumentException('Hace falta una mano de cartas por asiento.');
        }

        $vistas = [];

        foreach ($manos as $mano) {
            if (! is_array($mano) || ! array_is_list($mano) || count($mano) !== 3) {
                throw new InvalidArgumentException('Cada asiento recibe tres cartas.');
            }

            foreach ($mano as $carta) {
                if (! $carta instanceof Carta) {
                    throw new InvalidArgumentException('Solo se reparten cartas.');
                }

                if (isset($vistas[$carta->id()])) {
                    throw new InvalidArgumentException("El {$carta->nombre()} está repetido: en el mazo hay una sola de cada una.");
                }

                $vistas[$carta->id()] = true;
            }
        }
    }

    /**
     * @return array{jugadas: list<array{0: int, 1: Carta}>, ganador: int|null, cerrada: bool}
     */
    private static function bazaVacia(): array
    {
        return ['jugadas' => [], 'ganador' => null, 'cerrada' => false];
    }

    /**
     * @param  array{jugadas: list<array{0: int, 1: Carta}>, ganador: int|null, cerrada: bool}  $baza
     * @return array{jugadas: list<array{0: int, 1: string}>, ganador: int|null, cerrada: bool}
     */
    private static function bazaComoArray(array $baza): array
    {
        return [
            'jugadas' => array_map(fn (array $jugada) => [$jugada[0], $jugada[1]->id()], $baza['jugadas']),
            'ganador' => $baza['ganador'],
            'cerrada' => $baza['cerrada'],
        ];
    }

    /**
     * @param  list<Carta>  $cartas
     * @return list<string>
     */
    private static function ids(array $cartas): array
    {
        return array_map(fn (Carta $carta) => $carta->id(), $cartas);
    }
}
