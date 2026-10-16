<?php

namespace App\Juego;

use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

/**
 * Los niveles: cuánto jugó cada uno, contado con fósforos.
 *
 * Cada nivel son diez fósforos, dos grupos de cinco como en el tanteador. Ganar una partida anota tres,
 * perderla uno y abandonarla ninguno. Un desafío anota tres la primera vez que sale; repetirlo no anota.
 * Cuentan todas las partidas terminadas: contra la máquina de cualquier nivel, entre personas, de a cuatro
 * (a la persona de la pareja) y de torneo. Las de máquina contra máquina de un torneo no son partidas.
 *
 * El total se guarda en el jugador al cerrar cada partida, así abrir una página no cuenta nada. No agrega
 * nada que no esté en las partidas: recalcular() lo rehace desde ellas.
 *
 * El camino de premios está en fichas-de-premios.php: qué se destraba en cada nivel.
 */
final class Camino
{
    public const POR_NIVEL = 10;

    public const NIVELES = 40;

    public const POR_GANAR = 3;

    public const POR_PERDER = 1;

    public const POR_DESAFIO = 3;

    /** @var list<array{tipo: string, clave: string, nombre: string, nivel?: int, escalon?: int, listo: bool}>|null */
    private static ?array $fichas = null;

    /**
     * El nivel que corresponde a esos fósforos. Pasado el último, se siguen anotando pero el nivel no sube.
     */
    public static function nivelCon(int $fosforos): int
    {
        return min(self::NIVELES, intdiv($fosforos, self::POR_NIVEL) + 1);
    }

    /**
     * Cuántos fósforos lleva de la tira del nivel en que está (de 0 a 9). En el último nivel, la tira llena.
     */
    public static function enLaTira(int $fosforos): int
    {
        return self::nivelCon($fosforos) === self::NIVELES ? self::POR_NIVEL : $fosforos % self::POR_NIVEL;
    }

    public static function nivelDe(?Jugador $jugador): int
    {
        return self::nivelCon((int) ($jugador?->fosforos ?? 0));
    }

    /**
     * Todas las fichas del camino, en orden.
     *
     * @return list<array{tipo: string, clave: string, nombre: string, nivel?: int, escalon?: int, listo: bool}>
     */
    public static function fichas(): array
    {
        return self::$fichas ??= require __DIR__.'/fichas-de-premios.php';
    }

    /**
     * Los premios que se destraban subiendo de un nivel a otro: los de los niveles después de $desde, hasta $hasta.
     *
     * @return list<array{tipo: string, clave: string, nombre: string, nivel: int, listo: bool}>
     */
    public static function entre(int $desde, int $hasta): array
    {
        return array_values(array_filter(
            self::fichas(),
            fn (array $ficha) => isset($ficha['nivel']) && $ficha['nivel'] > $desde && $ficha['nivel'] <= $hasta,
        ));
    }

    /**
     * Quién juega en cada asiento que es de una persona: asiento => id de jugador. El asiento de la
     * máquina no está. De a cuatro, la persona es el asiento 0.
     *
     * @return array<int, int>
     */
    public static function personas(Partida $partida): array
    {
        $personas = [Mesa::JUGADOR => $partida->jugador_id];

        if ($partida->entre_personas && $partida->invitado_id !== null) {
            $personas[Mesa::BOT] = $partida->invitado_id;
        }

        return array_filter($personas, fn (?int $id) => $id !== null);
    }

    /**
     * Cuántos fósforos le anota esa partida a quien jugó en ese asiento.
     *
     * Abandonar es no anotar. Si el que se va es el rival, ganarla así anota solo si ya ibas en las
     * buenas, la misma regla que el ranking: así no sirve abrir una sala desde otra ventana y abandonar.
     */
    public function fosforosEn(Partida $partida, int $asiento, Mesa $mesa): int
    {
        if (! isset(self::personas($partida)[$asiento]) || ! in_array($partida->estado, [Partida::TERMINADA, Partida::ABANDONADA], true)) {
            return 0;
        }

        if ($partida->esDesafio()) {
            return $this->esLaPrimeraVezQueSale($partida) ? self::POR_DESAFIO : 0;
        }

        if ($partida->ganador === null) {
            return 0;
        }

        $pareja = $partida->deACuatro() ? $asiento % 2 : $asiento;
        $gano = $partida->ganador === $pareja;

        if ($partida->estado === Partida::ABANDONADA) {
            [$seFue, $tanteo] = $this->abandono($partida, $mesa);

            if ($seFue === $asiento) {
                return 0;
            }

            if ($gano && $tanteo !== null && $tanteo[$pareja] < intdiv($partida->puntos, 2)) {
                return 0;
            }
        }

        return $gano ? self::POR_GANAR : self::POR_PERDER;
    }

    /**
     * Le suma a cada persona lo que le anota la partida recién cerrada. Lo llama la mesa una sola vez, al cerrarla.
     */
    public function anotar(Partida $partida, Mesa $mesa): void
    {
        foreach (self::personas($partida) as $asiento => $jugador) {
            $fosforos = $this->fosforosEn($partida, $asiento, $mesa);

            if ($fosforos > 0) {
                Jugador::query()->whereKey($jugador)->increment('fosforos', $fosforos);
            }
        }
    }

    /**
     * Pone todos en cero y vuelve a sumar desde las partidas cerradas. Devuelve cuántas anotaron algo.
     */
    public function recalcular(Mesa $mesa): int
    {
        return DB::transaction(function () use ($mesa) {
            $total = [];
            $anotaron = 0;

            $cerradas = Partida::query()
                ->whereIn('estado', [Partida::TERMINADA, Partida::ABANDONADA])
                ->orderBy('terminada_en')
                ->orderBy('id');

            foreach ($cerradas->lazy(200) as $partida) {
                $anoto = false;

                foreach (self::personas($partida) as $asiento => $jugador) {
                    $fosforos = $this->fosforosEn($partida, $asiento, $mesa);
                    $total[$jugador] = ($total[$jugador] ?? 0) + $fosforos;
                    $anoto = $anoto || $fosforos > 0;
                }

                $anotaron += (int) $anoto;
            }

            Jugador::query()->update(['fosforos' => 0]);

            foreach ($total as $jugador => $fosforos) {
                if ($fosforos > 0) {
                    Jugador::query()->whereKey($jugador)->update(['fosforos' => $fosforos]);
                }
            }

            return $anotaron;
        });
    }

    /**
     * Lo que la mesa muestra al final de la partida: los fósforos que anotó, desde dónde y hasta dónde
     * llegó, y lo que destrabó si subió de nivel. Null si en ese asiento no juega una persona.
     *
     * Vale justo al cerrar: el total del jugador ya incluye esta partida y nada más.
     *
     * @return array{ganados: int, sellos: list<array{logro: string, escalon: int, nombre: string, dibujo: string}>, antes: int, ahora: int, nivelAntes: int, nivelAhora: int, premios: list<array{tipo: string, nombre: string, listo: bool}>}|null
     */
    public function resumen(Partida $partida, int $asiento, Mesa $mesa): ?array
    {
        $jugador = self::personas($partida)[$asiento] ?? null;

        if ($jugador === null) {
            return null;
        }

        // Los sellos de la libreta que dio esta partida también anotan: un fósforo cada uno.
        $sellos = (new Logros)->deLaPartida($partida, $jugador);
        $ganados = $this->fosforosEn($partida, $asiento, $mesa) + count($sellos) * Logros::FOSFOROS_POR_SELLO;
        $ahora = (int) Jugador::query()->whereKey($jugador)->value('fosforos');
        $antes = max(0, $ahora - $ganados);

        return [
            'ganados' => $ganados,
            // Si la partida dio dos escalones del mismo logro, se ve el más alto: un sello por logro.
            'sellos' => array_map(fn (array $sello) => [
                ...$sello,
                'nombre' => Logros::ficha($sello['logro'])['nombre'].(count(Logros::ficha($sello['logro'])['escalones']) > 1 ? ", escalón {$sello['escalon']}" : ''),
                // El sello ya dibujado: el final de la partida lo deja caer tal cual se ve en la libreta.
                'dibujo' => Blade::render('<x-sello :logro="$logro" :escalon="$escalon" :fecha="$fecha" class="size-full" />', [
                    'logro' => $sello['logro'], 'escalon' => $sello['escalon'], 'fecha' => $sello['ganado_en'],
                ]),
            ], array_values(array_column($sellos, null, 'logro'))),
            'antes' => $antes,
            'ahora' => $ahora,
            'nivelAntes' => self::nivelCon($antes),
            'nivelAhora' => self::nivelCon($ahora),
            'premios' => array_map(
                fn (array $ficha) => ['tipo' => $ficha['tipo'], 'nombre' => $ficha['nombre'], 'listo' => $ficha['listo']],
                self::entre(self::nivelCon($antes), self::nivelCon($ahora)),
            ),
        ];
    }

    /**
     * Si esta es la primera vez que ese jugador supera ese desafío: las anteriores que salieron no cuentan dos veces.
     */
    private function esLaPrimeraVezQueSale(Partida $partida): bool
    {
        if ($partida->estado !== Partida::TERMINADA || $partida->ganador !== Mesa::JUGADOR) {
            return false;
        }

        return ! Partida::query()
            ->whereKeyNot($partida->getKey())
            ->where('jugador_id', $partida->jugador_id)
            ->where('desafio', $partida->desafio)
            ->where('estado', Partida::TERMINADA)
            ->where('ganador', Mesa::JUGADOR)
            ->where(fn ($antes) => $antes
                ->where('terminada_en', '<', $partida->terminada_en)
                ->orWhere(fn ($igual) => $igual->where('terminada_en', $partida->terminada_en)->where('id', '<', $partida->getKey())))
            ->exists();
    }

    /**
     * Quién se fue y cómo iba el tanteo en ese momento. El tanteo sale de pasar la partida por el motor
     * hasta el abandono; null si no hay abandono guardado (una sala que se cerró sola).
     *
     * @return array{0: int|null, 1: array{0: int, 1: int}|null}
     */
    private function abandono(Partida $partida, Mesa $mesa): array
    {
        foreach ($mesa->pasoAPaso($partida) as [$evento, $motor]) {
            if ($evento->tipo === EventoDePartida::ABANDONO) {
                return [$evento->asiento, $motor->tanteo()];
            }
        }

        return [null, null];
    }
}
