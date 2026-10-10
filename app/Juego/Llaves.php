<?php

namespace App\Juego;

use App\Models\CruceDeTorneo;
use App\Models\Torneo;

/**
 * Las llaves de un torneo, contadas para la pantalla: ronda por ronda, quién juega cada cruce, cómo
 * salió y en qué está la persona. No decide nada: lee lo que Torneos dejó guardado.
 */
final class Llaves
{
    /** El cruce todavía no tiene a sus dos jugadores: espera a los ganadores de la ronda anterior. */
    public const ESPERA = 'espera';

    /** Es de la persona y todavía no lo empezó. */
    public const POR_JUGAR = 'por_jugar';

    /** Es de la persona y lo está jugando. */
    public const JUGANDO = 'jugando';

    /** Es entre bots, de la ronda que la persona todavía no terminó: se conoce cuando ella termina la suya. */
    public const A_LA_PAR = 'a_la_par';

    public const RESUELTO = 'resuelto';

    /**
     * @return array{
     *     rondas: list<array{numero: int, nombre: string, cruces: list<array<string, mixed>>}>,
     *     campeon: array<string, mixed>|null,
     *     vos: array{estado: string, partido: string|null, rival: array<string, mixed>|null, ronda: string|null, carta: array{0: string, 1: int}}
     * }
     */
    public function de(Torneo $torneo): array
    {
        $persona = $torneo->lugarDeLaPersona();
        $rondas = [];
        $suyo = null;
        $ultimoSuyo = null;

        foreach ($torneo->cruces()->with('partida')->get()->groupBy('ronda') as $numero => $cruces) {
            $rondas[] = [
                'numero' => (int) $numero,
                'nombre' => $torneo->nombreDeRonda((int) $numero),
                'cruces' => $cruces->map(fn (CruceDeTorneo $cruce) => $this->cruce($torneo, $cruce, $persona))->values()->all(),
            ];

            foreach ($cruces as $cruce) {
                if ($cruce->completo() && $cruce->loJuega($persona)) {
                    $ultimoSuyo = $cruce;
                    $suyo = $cruce->resuelto() ? $suyo : $cruce;
                }
            }
        }

        return [
            'rondas' => $rondas,
            'campeon' => $torneo->campeon === null ? null : $this->lado($torneo, $torneo->campeon, $persona),
            'vos' => [...$this->vos($torneo, $suyo, $ultimoSuyo, $persona), 'carta' => $torneo->cartaDe($persona)],
        ];
    }

    /**
     * En qué está la persona: con una partida por jugar o jugándose, afuera, o campeona.
     *
     * @return array{estado: string, partido: string|null, rival: array<string, mixed>|null, ronda: string|null}
     */
    private function vos(Torneo $torneo, ?CruceDeTorneo $suyo, ?CruceDeTorneo $ultimoSuyo, int $persona): array
    {
        if ($torneo->enCurso() && $suyo !== null) {
            return [
                'estado' => $suyo->partida?->enCurso() ? self::JUGANDO : self::POR_JUGAR,
                'partido' => $this->conArticulo($torneo->nombreDePartido($suyo->ronda)),
                'rival' => $this->lado($torneo, $suyo->rivalDe($persona), $persona),
                'ronda' => $torneo->nombreDeRonda($suyo->ronda),
            ];
        }

        $campeon = $torneo->campeon === $persona;

        return [
            'estado' => $campeon ? 'campeon' : 'afuera',
            // Dónde quedó: la partida que perdió y contra quién. Si salió campeona, contra quién jugó la final.
            'partido' => $campeon || $ultimoSuyo === null ? null : $this->conArticulo($torneo->nombreDePartido($ultimoSuyo->ronda)),
            'rival' => $ultimoSuyo === null ? null : $this->lado($torneo, $ultimoSuyo->rivalDe($persona), $persona),
            'ronda' => null,
        ];
    }

    /**
     * El nombre de un partido o de una ronda como va en medio de una frase: "la semifinal", "los cuartos de final".
     */
    public static function conArticulo(string $nombre): string
    {
        $nombre = mb_strtolower($nombre);

        return match (true) {
            str_starts_with($nombre, 'cuartos') => "los {$nombre}",
            str_ends_with($nombre, 's') => "las {$nombre}",
            default => "la {$nombre}",
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function cruce(Torneo $torneo, CruceDeTorneo $cruce, int $persona): array
    {
        $suyo = $cruce->completo() && $cruce->loJuega($persona);

        $estado = match (true) {
            $cruce->resuelto() => self::RESUELTO,
            ! $cruce->completo() => self::ESPERA,
            $suyo => $cruce->partida?->enCurso() ? self::JUGANDO : self::POR_JUGAR,
            default => self::A_LA_PAR,
        };

        return [
            'orden' => $cruce->orden,
            'estado' => $estado,
            'tuyo' => $suyo,
            'porAbandono' => $cruce->por_abandono,
            // La persona dejó el torneo antes de sentarse a jugar este cruce: su rival pasó sin jugar.
            'sinJugar' => $cruce->por_abandono && $cruce->partida_id === null,
            'lados' => [
                $this->ladoDelCruce($torneo, $cruce, $cruce->uno, $cruce->puntos_uno, $persona),
                $this->ladoDelCruce($torneo, $cruce, $cruce->dos, $cruce->puntos_dos, $persona),
            ],
        ];
    }

    /**
     * Un lado de un cruce: quién es, cuánto hizo y si pasó. Null si todavía no se sabe quién va ahí.
     *
     * @return array<string, mixed>|null
     */
    private function ladoDelCruce(Torneo $torneo, CruceDeTorneo $cruce, ?int $lugar, ?int $puntos, int $persona): ?array
    {
        if ($lugar === null) {
            return null;
        }

        return [
            ...$this->lado($torneo, $lugar, $persona),
            'puntos' => $puntos,
            'gano' => $cruce->resuelto() ? $cruce->ganador === $lugar : null,
        ];
    }

    /**
     * Quién ocupa un lugar: la persona, o un bot con su apodo y su nivel.
     *
     * @return array{lugar: int, apodo: string, vos: bool, nivel: Nivel|null, carta: array{0: string, 1: int}}
     */
    public function lado(Torneo $torneo, int $lugar, ?int $persona = null): array
    {
        return [
            'lugar' => $lugar,
            'apodo' => $torneo->apodoDe($lugar),
            'vos' => $lugar === ($persona ?? $torneo->lugarDeLaPersona()),
            'nivel' => $torneo->nivelDe($lugar),
            // La carta con la que se lo ve: la de un bot dice su nivel; la de la persona es el 4 de copas.
            'carta' => $torneo->cartaDe($lugar),
        ];
    }
}
