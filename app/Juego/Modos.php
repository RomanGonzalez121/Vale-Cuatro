<?php

namespace App\Juego;

/**
 * Los modos de juego del sitio, en el orden en que se muestran.
 *
 * Cada modo lleva una carta del mazo. Los que todavía no se juegan se muestran
 * boca abajo, y cuando se termina su módulo alcanza con pasar "disponible" a
 * true: la pantalla le da vuelta la carta y le pone su botón.
 */
final class Modos
{
    /**
     * @return list<array{clave: string, nombre: string, icono: string, carta: array{0: string, 1: int}, resumen: string, disponible: bool, boton: string|null}>
     */
    public static function todos(): array
    {
        return [
            [
                'clave' => 'bot',
                'nombre' => 'Contra el bot',
                'icono' => 'bot',
                'carta' => ['espada', 1],
                'resumen' => 'Una partida a 30 puntos contra el bot. Entrás sin registrarte y jugás ya.',
                'disponible' => true,
                'boton' => 'Jugar contra el bot',
            ],
            [
                'clave' => 'invitar',
                'nombre' => 'Invitar a alguien',
                'icono' => 'invitar',
                'carta' => ['oro', 2],
                'resumen' => 'Le mandás un link a otra persona y juegan mano a mano, en vivo.',
                'disponible' => false,
                'boton' => null,
            ],
            [
                'clave' => 'de-a-cuatro',
                'nombre' => 'De a cuatro',
                'icono' => 'de-a-cuatro',
                'carta' => ['copa', 4],
                'resumen' => 'Dos contra dos, con señas entre compañeros.',
                'disponible' => false,
                'boton' => null,
            ],
            [
                'clave' => 'torneo',
                'nombre' => 'Torneo relámpago',
                'icono' => 'torneo',
                'carta' => ['oro', 12],
                'resumen' => 'Cuatro u ocho jugadores, eliminación directa y llaves en vivo.',
                'disponible' => false,
                'boton' => null,
            ],
            [
                'clave' => 'desafios',
                'nombre' => 'Desafíos',
                'icono' => 'desafio',
                'carta' => ['espada', 7],
                'resumen' => 'Manos armadas con un objetivo: ganar esa mano, o lograr que el bot no quiera.',
                'disponible' => false,
                'boton' => null,
            ],
            [
                'clave' => 'escalera',
                'nombre' => 'Escalera del club',
                'icono' => 'escalera',
                'carta' => ['basto', 11],
                'resumen' => 'Una serie de rivales cada vez más difíciles. Pasás uno y se abre el siguiente.',
                'disponible' => false,
                'boton' => null,
            ],
        ];
    }
}
