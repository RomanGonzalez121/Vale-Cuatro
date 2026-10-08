<?php

namespace App\Maqueta;

/**
 * Datos fijos para la maqueta visual de M0.
 *
 * Nada de esto sale de la base de datos. Cada pantalla pasa a leer datos reales
 * cuando llega su módulo y entonces este archivo se borra. El historial ya lo hizo en M7:
 * queda el ranking, que es de M8. La mesa tampoco lo usa: desde M3 juega con el motor de reglas.
 */
class DatosDeEjemplo
{
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
}
