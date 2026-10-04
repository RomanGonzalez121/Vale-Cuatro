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
            ['vos' => ['1-basto', '2-copa', '12-espada'], 'rival' => ['1-oro', '6-basto', '4-espada'], 'rivalCantaTruco' => false],
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
     * @return list<array{cuando: string, rival: string, bot: bool, vos: int, ellos: int, manos: int, minutos: int}>
     */
    public static function partidas(): array
    {
        return [
            ['cuando' => 'Hoy, 21:40', 'rival' => 'Bot, nivel 2', 'bot' => true, 'vos' => 30, 'ellos' => 21, 'manos' => 14, 'minutos' => 11],
            ['cuando' => 'Hoy, 21:15', 'rival' => 'Bot, nivel 3', 'bot' => true, 'vos' => 24, 'ellos' => 30, 'manos' => 17, 'minutos' => 14],
            ['cuando' => 'Ayer, 23:02', 'rival' => 'Maru', 'bot' => false, 'vos' => 30, 'ellos' => 28, 'manos' => 21, 'minutos' => 19],
            ['cuando' => 'Ayer, 22:30', 'rival' => 'Bot, nivel 1', 'bot' => true, 'vos' => 30, 'ellos' => 9, 'manos' => 10, 'minutos' => 7],
            ['cuando' => '2 de octubre, 19:48', 'rival' => 'Maru', 'bot' => false, 'vos' => 17, 'ellos' => 30, 'manos' => 16, 'minutos' => 15],
        ];
    }

    /**
     * Tres manos de una partida, tal como se verían en la repetición.
     * Cada baza es [carta tuya, carta del rival, quién la ganó].
     *
     * @return list<array<string, mixed>>
     */
    public static function repeticion(): array
    {
        return [
            [
                'numero' => 12,
                'tanteo' => [24, 20],
                'cantos' => [['Vos', 'Envido', 'oro'], ['Bot', 'Quiero', 'basto'], ['Vos', 'Truco', 'copa'], ['Bot', 'Quiero', 'basto']],
                'bazas' => [['7-oro', '3-basto', 'rival'], ['1-espada', '12-copa', 'vos'], ['6-oro', '5-copa', 'vos']],
                'resumen' => 'Ganaste el envido 33 a 25 y el truco. Sumaste 4.',
            ],
            [
                'numero' => 13,
                'tanteo' => [28, 20],
                'cantos' => [['Bot', 'Truco', 'copa'], ['Vos', 'No quiero', 'copa']],
                'bazas' => [['4-copa', '7-espada', 'rival']],
                'resumen' => 'No quisiste el truco. El bot sumó 1.',
            ],
            [
                'numero' => 14,
                'tanteo' => [28, 21],
                'cantos' => [['Vos', 'Truco', 'copa'], ['Bot', 'Quiero', 'basto']],
                'bazas' => [['1-basto', '1-oro', 'vos'], ['12-espada', '6-basto', 'vos']],
                'resumen' => 'Ganaste las dos primeras bazas. Sumaste 2 y cerraste la partida 30 a 21.',
            ],
        ];
    }
}
