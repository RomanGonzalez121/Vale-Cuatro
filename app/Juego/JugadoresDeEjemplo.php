<?php

namespace App\Juego;

use App\Models\Jugador;
use App\Models\Resultado;
use App\Motor\Azar;
use Illuminate\Support\Facades\DB;

/**
 * Los jugadores de ejemplo del ranking: los que hacen que la tabla no arranque vacía.
 *
 * Son simulados y el sitio lo dice (van marcados como bots), pero sus números no están escritos a
 * mano: cada uno juega con el bot de su nivel contra todos los demás, las partidas quedan guardadas
 * como eventos y sus estadísticas se calculan igual que las de cualquier persona. Con la misma
 * semilla juegan siempre las mismas partidas.
 */
final class JugadoresDeEjemplo
{
    /** De acá sale todo el azar: cambiarla es cambiar todas las partidas. */
    public const SEMILLA = 2026;

    /** Cuántas veces se cruza cada pareja. Con once jugadores, tres vueltas son 30 partidas por cabeza. */
    public const VUELTAS = 3;

    /** En cuántos días hacia atrás quedan repartidas las partidas, para que la tabla tenga historia. */
    private const DIAS = 30;

    /**
     * Cada uno con el nivel de bot que lo juega. Los más fuertes terminan arriba porque ganan más, no
     * porque alguien los haya puesto ahí.
     *
     * @return array<string, Nivel>
     */
    public static function lista(): array
    {
        return [
            'Don Anselmo' => Nivel::UltraDificil,
            'La Tana Rossi' => Nivel::Dificil,
            'El Zurdo Medina' => Nivel::Dificil,
            'Doña Elvira' => Nivel::Dificil,
            'Cacho de Lanús' => Nivel::Intermedio,
            'El Gringo Bauer' => Nivel::Intermedio,
            'Tito Pereyra' => Nivel::Intermedio,
            'La Colorada' => Nivel::Intermedio,
            'Pichón Ibarra' => Nivel::Facil,
            'El Mudo Gómez' => Nivel::Facil,
            'Negrita Luna' => Nivel::Facil,
        ];
    }

    /**
     * Crea los jugadores de ejemplo y les hace jugar sus partidas. Si ya jugaron no hace nada, así
     * que se puede llamar en cada publicación del sitio. Devuelve cuántas partidas jugó.
     *
     * @param  int|null  $vueltas  Para los tests, que no necesitan las tres.
     */
    public function sembrar(Mesa $mesa, ?int $vueltas = null): int
    {
        // Todo o nada: si se corta por la mitad no queda una tabla a medio jugar que después parezca completa.
        return DB::transaction(fn () => $this->jugarTodo($mesa, $vueltas ?? self::VUELTAS));
    }

    private function jugarTodo(Mesa $mesa, int $vueltas): int
    {
        $jugadores = $this->crear();

        if (Resultado::query()->whereIn('jugador_id', array_map(fn (array $par) => $par[0]->getKey(), $jugadores))->exists()) {
            return 0;
        }

        $cruces = $this->cruces(count($jugadores), $vueltas);
        $simulacion = new Simulacion;
        $desde = now()->subDays(self::DIAS)->startOfMinute();
        // Las partidas quedan repartidas parejo entre hace DIAS días y hace una hora.
        $paso = max(1, intdiv(self::DIAS * 86400 - 3600, max(1, count($cruces))));

        foreach ($cruces as $numero => [$uno, $dos]) {
            $simulacion->jugar(
                $jugadores[$uno][0], $jugadores[$uno][1],
                $jugadores[$dos][0], $jugadores[$dos][1],
                self::SEMILLA + $numero,
                $desde->copy()->addSeconds($numero * $paso),
                $mesa,
            );
        }

        return count($cruces);
    }

    /**
     * Borra a los jugadores de ejemplo con todo lo que jugaron. Como cada partida de ejemplo es de uno
     * de ellos, la base se lleva las partidas, sus eventos y sus renglones del ranking.
     */
    public function borrar(): void
    {
        Jugador::query()->where('de_ejemplo', true)->delete();
    }

    /**
     * Los jugadores, creados si faltan, cada uno con su nivel. Si una persona ya tiene uno de esos
     * apodos, ese jugador de ejemplo no se crea: el apodo es de quien lo eligió.
     *
     * @return list<array{0: Jugador, 1: Nivel}>
     */
    private function crear(): array
    {
        return DB::transaction(function () {
            $jugadores = [];

            foreach (self::lista() as $apodo => $nivel) {
                $jugador = Jugador::query()->where('apodo', $apodo)->first();

                if ($jugador === null) {
                    $jugador = new Jugador(['apodo' => $apodo]);
                    // La marca no se asigna en masa: ningún formulario puede crear un jugador de ejemplo.
                    $jugador->de_ejemplo = true;
                    $jugador->save();
                }

                if ($jugador->esDeEjemplo()) {
                    $jugadores[] = [$jugador, $nivel];
                }
            }

            return $jugadores;
        });
    }

    /**
     * Quién juega contra quién, en orden: todos contra todos, tantas vueltas como se pida. En cada vuelta
     * las parejas se mezclan (con la semilla) y se alterna quién ocupa el asiento 0.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function cruces(int $jugadores, int $vueltas): array
    {
        $azar = Azar::deSemilla(self::SEMILLA);
        $cruces = [];

        for ($vuelta = 0; $vuelta < $vueltas; $vuelta++) {
            $parejas = [];

            for ($uno = 0; $uno < $jugadores; $uno++) {
                for ($dos = $uno + 1; $dos < $jugadores; $dos++) {
                    $parejas[] = $vuelta % 2 === 0 ? [$uno, $dos] : [$dos, $uno];
                }
            }

            // Mezcla de Fisher y Yates con el azar de la semilla: el orden también sale siempre igual.
            for ($i = count($parejas) - 1; $i > 0; $i--) {
                $j = $azar->entero(0, $i);
                [$parejas[$i], $parejas[$j]] = [$parejas[$j], $parejas[$i]];
            }

            array_push($cruces, ...$parejas);
        }

        return $cruces;
    }
}
