<?php

namespace App\Maqueta;

/**
 * Datos fijos para la maqueta visual de M0.
 *
 * Nada de esto sale de la base de datos. Cada pantalla pasa a leer datos reales
 * cuando llega su módulo (M3 la mesa, M7 el historial, M8 el ranking) y entonces
 * este archivo se borra.
 */
class DatosDeEjemplo
{
    /**
     * Las manos que recorre la mesa de la maqueta, en orden.
     *
     * @return list<array{vos: list<string>, rival: list<string>, rivalCantaTruco: bool}>
     */
    public static function manos(): array
    {
        return [
            ['vos' => ['7-oro', '6-oro', '1-espada'], 'rival' => ['3-basto', '12-copa', '5-copa'], 'rivalCantaTruco' => false],
            ['vos' => ['3-espada', '11-basto', '4-copa'], 'rival' => ['7-espada', '2-oro', '10-oro'], 'rivalCantaTruco' => true],
            // En esta el bot tiene mejor envido: sirve para ver el "son mejores" y sus cartas al cerrar la mano.
            ['vos' => ['1-basto', '6-copa', '5-copa'], 'rival' => ['7-oro', '6-oro', '4-espada'], 'rivalCantaTruco' => false],
        ];
    }

    /**
     * @return list<array{puesto: int, apodo: string, bot: bool, ganadas: int, jugadas: int, racha: int, envidos: int}>
     */
    public static function ranking(): array
    {
        $jugadores = [
            ['Don Anselmo', true, 212, 301, 9, 64],
            ['La Tana Rossi', true, 198, 290, 4, 58],
            ['El Zurdo Medina', true, 187, 284, 6, 61],
            ['Doña Elvira', true, 171, 266, 2, 55],
            ['Cacho de Lanús', true, 160, 259, 3, 49],
            ['El Gringo Bauer', true, 149, 251, 1, 52],
            ['Tito Pereyra', true, 131, 240, 0, 47],
            ['La Colorada', true, 122, 233, 5, 51],
            ['Pichón Ibarra', true, 104, 221, 1, 44],
            ['El Mudo Gómez', true, 97, 214, 0, 46],
            ['Negrita Luna', true, 88, 205, 2, 41],
            ['Vos', false, 3, 5, 2, 60],
        ];

        return array_map(
            fn (array $jugador, int $indice) => [
                'puesto' => $indice + 1,
                'apodo' => $jugador[0],
                'bot' => $jugador[1],
                'ganadas' => $jugador[2],
                'jugadas' => $jugador[3],
                'racha' => $jugador[4],
                'envidos' => $jugador[5],
            ],
            $jugadores,
            array_keys($jugadores),
        );
    }

    /**
     * Partidas jugadas, de la más nueva a la más vieja, agrupables por día.
     *
     * @return list<array{dia: string, hora: string, rival: string, bot: bool, vos: int, ellos: int, manos: int, minutos: int}>
     */
    public static function partidas(): array
    {
        return [
            ['dia' => 'Hoy', 'hora' => '21:40', 'rival' => 'Bot, nivel 2', 'bot' => true, 'vos' => 30, 'ellos' => 21, 'manos' => 14, 'minutos' => 11],
            ['dia' => 'Hoy', 'hora' => '21:15', 'rival' => 'Bot, nivel 3', 'bot' => true, 'vos' => 24, 'ellos' => 30, 'manos' => 17, 'minutos' => 14],
            ['dia' => 'Ayer', 'hora' => '23:02', 'rival' => 'Maru', 'bot' => false, 'vos' => 30, 'ellos' => 28, 'manos' => 21, 'minutos' => 19],
            ['dia' => 'Ayer', 'hora' => '22:30', 'rival' => 'Bot, nivel 1', 'bot' => true, 'vos' => 30, 'ellos' => 9, 'manos' => 10, 'minutos' => 7],
            ['dia' => '2 de octubre', 'hora' => '19:48', 'rival' => 'Maru', 'bot' => false, 'vos' => 17, 'ellos' => 30, 'manos' => 16, 'minutos' => 15],
        ];
    }

    /**
     * Las tres últimas manos de una partida, jugada por jugada, para la repetición.
     * Es la forma que van a tener los eventos guardados de M3 y M7: una lista en
     * orden, de la que se puede reconstruir la mesa en cualquier punto.
     *
     * @return list<array<string, mixed>>
     */
    public static function pasosDeRepeticion(): array
    {
        $canto = fn (string $quien, string $texto, string $tono) => ['tipo' => 'canto', 'quien' => $quien, 'texto' => $texto, 'tono' => $tono];
        $carta = fn (string $quien, string $carta, int $baza) => ['tipo' => 'carta', 'quien' => $quien, 'carta' => $carta, 'baza' => $baza];
        $puntos = fn (string $quien, int $cantidad, string $texto) => ['tipo' => 'puntos', 'quien' => $quien, 'cantidad' => $cantidad, 'texto' => $texto];

        return [
            ['tipo' => 'reparto', 'mano' => 12, 'tanteo' => [24, 20], 'vos' => ['7-oro', '6-oro', '1-espada']],
            $canto('vos', 'Envido', 'oro'),
            $canto('rival', 'Quiero', 'basto'),
            $puntos('vos', 2, 'Envido: 33 a 25. Sumaste 2.'),
            $carta('vos', '6-oro', 0),
            $carta('rival', '3-basto', 0),
            $carta('rival', '12-copa', 1),
            $canto('vos', 'Truco', 'copa'),
            $canto('rival', 'Quiero', 'basto'),
            $carta('vos', '7-oro', 1),
            $carta('vos', '1-espada', 2),
            $carta('rival', '5-copa', 2),
            $puntos('vos', 2, 'Ganaste dos bazas con el truco querido. Sumaste 2.'),

            ['tipo' => 'reparto', 'mano' => 13, 'tanteo' => [28, 20], 'vos' => ['4-copa', '11-basto', '3-espada']],
            $carta('vos', '4-copa', 0),
            $canto('rival', 'Truco', 'copa'),
            $canto('vos', 'No quiero', 'copa'),
            $puntos('rival', 1, 'No quisiste el truco. El bot sumó 1.'),

            ['tipo' => 'reparto', 'mano' => 14, 'tanteo' => [28, 21], 'vos' => ['1-basto', '12-espada', '2-copa']],
            $canto('vos', 'Truco', 'copa'),
            $canto('rival', 'Quiero', 'basto'),
            $carta('vos', '1-basto', 0),
            $carta('rival', '1-oro', 0),
            $carta('vos', '12-espada', 1),
            $carta('rival', '6-basto', 1),
            $puntos('vos', 2, 'Ganaste las dos primeras bazas. Sumaste 2 y cerraste la partida 30 a 21.'),
        ];
    }
}
