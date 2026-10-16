<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Sello;
use Illuminate\Support\Facades\DB;

/**
 * La libreta del club (M23): los logros y sus sellos.
 *
 * Un logro cuenta algo que el jugador hizo (las cuentas las suma App\Juego\Titulos al cerrar cada partida) y
 * tiene escalones. Cada escalón alcanzado es un sello guardado en la tabla "sellos", con su fecha, y anota un
 * fósforo para el nivel (decidido por Román el 15 de octubre de 2026). Los títulos salen de un escalón de su
 * logro: el título pide lo mismo que ese escalón.
 *
 * Lo que ve la página sale de lo guardado: ninguna página recorre partidas al abrirse.
 */
final class Logros
{
    /**
     * Los temas, en el orden de la libreta, con su nombre y la tinta de sus sellos: de día sobre el papel y de noche
     * sobre Tinta. De noche se aclaran como el resto del sitio (Copa, Basto y Espada en sus tonos de noche, el
     * envido en Oro y las partidas en Naipe). El contraste de las dos se mide en PaletaTest.
     */
    public const TEMAS = [
        'envido' => ['nombre' => 'Envido', 'tinta' => '#8a5a0c', 'noche' => '#f0b429'],
        'truco' => ['nombre' => 'Truco', 'tinta' => '#c93327', 'noche' => '#f0685c'],
        'flor' => ['nombre' => 'Flor', 'tinta' => '#2a7d4f', 'noche' => '#57b983'],
        'partidas' => ['nombre' => 'Partidas', 'tinta' => '#161a18', 'noche' => '#fbfaf5'],
        'juegos' => ['nombre' => 'Juegos', 'tinta' => '#2a5caa', 'noche' => '#6fa0e8'],
        'secretos' => ['nombre' => 'Secretos', 'tinta' => '#6b2c22', 'noche' => '#e0937c'],
    ];

    /** Cada escalón ganado anota un fósforo para el nivel. */
    public const FOSFOROS_POR_SELLO = 1;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $fichas = null;

    /**
     * Las fichas de los logros, por clave.
     *
     * @return array<string, array{clave: string, tema: string, nombre: string, pide: string, icono: string, cuenta: string, escalones: list<int>, titulo?: string, titulo_en?: int, secreto?: bool}>
     */
    public static function fichas(): array
    {
        return self::$fichas ??= array_column(require __DIR__.'/fichas-de-logros.php', null, 'clave');
    }

    /**
     * @return array{clave: string, tema: string, nombre: string, pide: string, icono: string, cuenta: string, escalones: list<int>}|null
     */
    public static function ficha(string $clave): ?array
    {
        return self::fichas()[$clave] ?? null;
    }

    /**
     * Cuántos escalones de ese logro alcanza esa cuenta.
     *
     * @param  array{escalones: list<int>}  $ficha
     */
    public static function escalonCon(array $ficha, int $cuenta): int
    {
        return count(array_filter($ficha['escalones'], fn (int $meta) => $cuenta >= $meta));
    }

    /**
     * Cuántos sellos se pueden ganar en total: uno por escalón.
     */
    public static function cuantos(): int
    {
        return array_sum(array_map(fn (array $ficha) => count($ficha['escalones']), self::fichas()));
    }

    /**
     * Le pone a ese jugador los sellos que ya alcanzó y no tenía, cada uno con su fósforo. Devuelve los nuevos,
     * en el orden de la libreta. Lo llama la mesa al cerrar cada partida, para cada persona, después de sumar las
     * cuentas; y el torneo al tener campeón.
     *
     * @return list<array{logro: string, escalon: int}>
     */
    public function revisar(?Jugador $jugador, ?Partida $partida = null): array
    {
        if ($jugador === null) {
            return [];
        }

        $jugador = $jugador->fresh();
        $tiene = $this->escalonesDe($jugador);
        $nuevos = [];

        foreach (self::fichas() as $clave => $ficha) {
            $alcanza = self::escalonCon($ficha, (int) $jugador->{$ficha['cuenta']});

            for ($escalon = ($tiene[$clave] ?? 0) + 1; $escalon <= $alcanza; $escalon++) {
                $nuevos[] = ['logro' => $clave, 'escalon' => $escalon];
            }
        }

        if ($nuevos === []) {
            return [];
        }

        DB::transaction(function () use ($jugador, $partida, $nuevos) {
            Sello::query()->insert(array_map(fn (array $nuevo) => [
                'jugador_id' => $jugador->getKey(),
                'logro' => $nuevo['logro'],
                'escalon' => $nuevo['escalon'],
                'partida_id' => $partida?->getKey(),
                'ganado_en' => now(),
                'visto' => false,
            ], $nuevos));

            Jugador::query()->whereKey($jugador->getKey())->increment('fosforos', count($nuevos) * self::FOSFOROS_POR_SELLO);
        });

        return $nuevos;
    }

    /**
     * Rehace los sellos de todos desde sus cuentas, ya recalculadas: saca los que ya no alcanzan y suma los que
     * faltan, sin tocar los que siguen (conservan su fecha y si se vieron). Después le suma a cada uno un fósforo
     * por sello, sobre los de las partidas que acaba de poner fosforos:recalcular.
     */
    public function recalcular(): int
    {
        return DB::transaction(function () {
            $agregados = 0;

            foreach (Jugador::query()->lazyById(200) as $jugador) {
                $tiene = $this->escalonesDe($jugador);
                $nuevos = [];

                foreach (self::fichas() as $clave => $ficha) {
                    $alcanza = self::escalonCon($ficha, (int) $jugador->{$ficha['cuenta']});

                    if (($tiene[$clave] ?? 0) > $alcanza) {
                        Sello::query()->where('jugador_id', $jugador->getKey())->where('logro', $clave)->where('escalon', '>', $alcanza)->delete();
                    }

                    for ($escalon = ($tiene[$clave] ?? 0) + 1; $escalon <= $alcanza; $escalon++) {
                        $nuevos[] = ['jugador_id' => $jugador->getKey(), 'logro' => $clave, 'escalon' => $escalon, 'partida_id' => null, 'ganado_en' => now(), 'visto' => true];
                    }
                }

                if ($nuevos !== []) {
                    Sello::query()->insert($nuevos);
                    $agregados += count($nuevos);
                }
            }

            // Los sellos de logros que ya no existen no cuentan.
            Sello::query()->whereNotIn('logro', array_keys(self::fichas()))->delete();

            foreach (Sello::query()->select('jugador_id', DB::raw('count(*) as cuantos'))->groupBy('jugador_id')->get() as $fila) {
                Jugador::query()->whereKey($fila->jugador_id)->increment('fosforos', $fila->cuantos * self::FOSFOROS_POR_SELLO);
            }

            return $agregados;
        });
    }

    /**
     * La libreta de ese jugador, por tema: cada logro con su escalón, lo que lleva, la próxima meta, la fecha de
     * su último sello y cuántos socios lo tienen. Un secreto que no ganó no dice qué es.
     *
     * @return array<string, array{nombre: string, tinta: string, logros: list<array<string, mixed>>, tiene: int, total: int}>
     */
    public function libreta(Jugador $jugador): array
    {
        $sellos = Sello::query()->where('jugador_id', $jugador->getKey())->get()->groupBy('logro');
        $rareza = $this->rareza();
        $libreta = [];

        foreach (self::TEMAS as $tema => $datos) {
            $libreta[$tema] = [...$datos, 'logros' => [], 'tiene' => 0, 'total' => 0];
        }

        foreach (self::fichas() as $clave => $ficha) {
            $suyos = $sellos->get($clave, collect());
            $escalon = (int) $suyos->max('escalon');
            $lleva = (int) $jugador->{$ficha['cuenta']};
            $oculto = ($ficha['secreto'] ?? false) && $escalon === 0;
            $total = count($ficha['escalones']);

            $libreta[$ficha['tema']]['logros'][] = [
                'clave' => $clave,
                'nombre' => $oculto ? 'Secreto' : $ficha['nombre'],
                'pide' => $oculto ? 'Se descubre jugando.' : $ficha['pide'],
                'icono' => $ficha['icono'],
                'tinta' => self::TEMAS[$ficha['tema']]['tinta'],
                'secreto' => $oculto,
                'escalon' => $escalon,
                'escalones' => $total,
                'lleva' => $lleva,
                'meta' => $ficha['escalones'][min($escalon, $total - 1)],
                'completo' => $escalon === $total,
                'fecha' => $suyos->firstWhere('escalon', $escalon)?->ganado_en,
                // Si tiene un sello de este logro que todavía no vio: la libreta lo marca como nuevo.
                'nuevo' => $suyos->contains('visto', false),
                'rareza' => $rareza[$clave] ?? 0,
                'titulo' => isset($ficha['titulo']) ? Titulos::nombre($ficha['titulo']) : null,
                'tituloEn' => $ficha['titulo_en'] ?? 2,
            ];

            $libreta[$ficha['tema']]['tiene'] += $escalon;
            $libreta[$ficha['tema']]['total'] += $total;
        }

        return $libreta;
    }

    /**
     * Qué porcentaje de los socios tiene cada logro (por lo menos su primer sello). Socios son las cuentas que
     * jugaron alguna partida; los jugadores de ejemplo no cuentan.
     *
     * @return array<string, int>
     */
    public function rareza(): array
    {
        $socios = Jugador::query()->whereNotNull('email')->where('de_ejemplo', false)->where('fosforos', '>', 0);
        $cuantos = (clone $socios)->count();

        if ($cuantos === 0) {
            return [];
        }

        return Sello::query()
            ->where('escalon', 1)
            ->whereIn('jugador_id', $socios->select('id'))
            ->select('logro', DB::raw('count(*) as cuantos'))
            ->groupBy('logro')
            ->pluck('cuantos', 'logro')
            ->map(fn ($tienen) => max(1, (int) round(100 * $tienen / $cuantos)))
            ->all();
    }

    /**
     * Si tiene sellos que todavía no vio en su libreta: el menú lo marca con el puntito.
     */
    public function hayNuevos(?Jugador $jugador): bool
    {
        return $jugador !== null && Sello::query()->where('jugador_id', $jugador->getKey())->where('visto', false)->exists();
    }

    public function marcarVistos(Jugador $jugador): void
    {
        Sello::query()->where('jugador_id', $jugador->getKey())->where('visto', false)->update(['visto' => true]);
    }

    /**
     * Los sellos que dio una partida a ese jugador, para el final de la partida.
     *
     * @return list<array{logro: string, escalon: int}>
     */
    public function deLaPartida(Partida $partida, int $jugador): array
    {
        $orden = array_flip(array_keys(self::fichas()));

        return Sello::query()
            ->where('partida_id', $partida->getKey())
            ->where('jugador_id', $jugador)
            ->get(['logro', 'escalon'])
            ->sortBy(fn (Sello $sello) => [$orden[$sello->logro] ?? 999, $sello->escalon])
            ->map(fn (Sello $sello) => ['logro' => $sello->logro, 'escalon' => $sello->escalon])
            ->values()
            ->all();
    }

    /**
     * Hasta qué escalón tiene cada logro.
     *
     * @return array<string, int>
     */
    private function escalonesDe(Jugador $jugador): array
    {
        return Sello::query()
            ->where('jugador_id', $jugador->getKey())
            ->select('logro', DB::raw('max(escalon) as escalon'))
            ->groupBy('logro')
            ->pluck('escalon', 'logro')
            ->map(fn ($escalon) => (int) $escalon)
            ->all();
    }
}
