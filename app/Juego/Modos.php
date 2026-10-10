<?php

namespace App\Juego;

/**
 * Los modos de juego del sitio, ordenados por juego. Dentro de cada juego se
 * elige el rival: contra bots o con personas.
 *
 * Un rival se juega cuando tiene "boton" (el texto del botón que entra a la
 * mesa). Un juego está en la mano cuando se juega con al menos un rival; si
 * no, todavía está en el mazo. Cuando se termina un módulo alcanza con ponerle
 * el botón a su rival: la pantalla reparte la carta sola.
 *
 * Un rival con "niveles" deja elegir contra qué bot se juega antes de entrar. Uno con "lugares" deja
 * elegir de cuántos jugadores es el torneo.
 * "ruta" es la ruta a la que va su botón y "icono" el que lleva; si faltan, van a jugar contra el bot.
 */
final class Modos
{
    /**
     * @return list<array{clave: string, nombre: string, renglones: list<string>, icono: string, resumen: string, rivales: list<array{clave: string, nombre: string, detalle: string, boton: string|null, ruta?: string, icono?: string, niveles?: list<Nivel>, lugares?: list<int>}>}>
     */
    public static function juegos(): array
    {
        return [
            [
                'clave' => 'mano-a-mano',
                'nombre' => 'Mano a mano',
                'renglones' => ['Mano', 'a mano'],
                'icono' => 'mano-a-mano',
                'resumen' => 'Uno contra uno, a 30 puntos y con flor. El truco de siempre.',
                'rivales' => [
                    ['clave' => 'bots', 'nombre' => 'Contra el bot', 'detalle' => 'Entrás sin registrarte y jugás ya.', 'boton' => 'Jugar contra el bot', 'ruta' => 'jugar', 'icono' => 'bot', 'niveles' => Nivel::cases()],
                    ['clave' => 'personas', 'nombre' => 'Con otra persona', 'detalle' => 'Le mandás un link a alguien y juegan en vivo. Entrás sin registrarte.', 'boton' => 'Invitar a jugar', 'ruta' => 'invitar', 'icono' => 'invitar'],
                ],
            ],
            [
                'clave' => 'de-a-cuatro',
                'nombre' => 'De a cuatro',
                'renglones' => ['De a', 'cuatro'],
                'icono' => 'de-a-cuatro',
                'resumen' => 'Dos contra dos, con señas entre compañeros.',
                'rivales' => [
                    ['clave' => 'bots', 'nombre' => 'Con bots', 'detalle' => 'Un bot de compañero y dos de rivales.', 'boton' => null],
                    ['clave' => 'personas', 'nombre' => 'Con otras personas', 'detalle' => 'Armás la pareja por link y juegan en vivo.', 'boton' => null],
                ],
            ],
            [
                'clave' => 'torneo',
                'nombre' => 'Torneo relámpago',
                'renglones' => ['Torneo'],
                'icono' => 'torneo',
                'resumen' => 'Cuatro u ocho jugadores, eliminación directa y las llaves a la vista.',
                'rivales' => [
                    ['clave' => 'bots', 'nombre' => 'Contra bots', 'detalle' => 'Jugás tus partidas y las demás se resuelven solas.', 'boton' => 'Armar el torneo', 'ruta' => 'torneo.crear', 'icono' => 'torneo', 'lugares' => Torneos::LUGARES],
                    ['clave' => 'personas', 'nombre' => 'Con otras personas', 'detalle' => 'Se inscriben por link y se juega en vivo.', 'boton' => null],
                ],
            ],
            [
                'clave' => 'desafios',
                'nombre' => 'Desafíos',
                'renglones' => ['Desafíos'],
                'icono' => 'desafio',
                'resumen' => 'Manos armadas con un objetivo: ganar esa mano, o lograr que el bot no quiera.',
                'rivales' => [
                    ['clave' => 'bots', 'nombre' => 'Contra el bot', 'detalle' => 'Cada desafío reparte siempre las mismas cartas.', 'boton' => null],
                ],
            ],
            [
                'clave' => 'escalera',
                'nombre' => 'Escalera del club',
                'renglones' => ['Escalera'],
                'icono' => 'escalera',
                'resumen' => 'Una serie de rivales cada vez más difíciles. Pasás uno y se abre el siguiente.',
                'rivales' => [
                    ['clave' => 'bots', 'nombre' => 'Contra el bot', 'detalle' => 'Cada nivel es un rival nuevo o una mano con objetivo.', 'boton' => null],
                ],
            ],
        ];
    }

    /**
     * Un juego se puede jugar cuando al menos uno de sus rivales tiene botón.
     *
     * @param  array{rivales: list<array{boton: string|null}>}  $juego
     */
    public static function seJuega(array $juego): bool
    {
        return array_filter(array_column($juego['rivales'], 'boton')) !== [];
    }
}
