<?php

/*
 | La gira de los clubes (M26), como datos: seis barrios, cada uno con sus niveles y un jefe al final.
 |
 | Boedo es la escalera del club de siempre: sus ocho niveles son los escalones (App\Juego\Escalera) y se
 | anotan como antes, con su progreso y sus tres dorsos donde estaban. Los otros cinco tienen seis niveles:
 | dos partidas mano a mano, una de a cuatro, una mano con objetivo (de los desafíos que ya existen), un
 | nivel especial y el jefe (decidido por Román el 16 de octubre de 2026).
 |
 | Cada nivel da hasta tres fósforos: uno por pasarlo, el segundo por lo que dice "segundo" y el tercero
 | por lo que dice "tercero". Las condiciones las cuenta App\Juego\FosforosDeGira desde los hechos de la
 | partida; acá solo se nombran:
 |
 |   ['por', N]            ganar por N o más
 |   ['sin_mazo']          sin irte al mazo
 |   ['envidos', N]        ganando N envidos (o más)
 |   ['trucos', N]         ganando N trucos queridos
 |   ['retrucos', N]       ganando N retrucos queridos
 |   ['vale_cuatros', N]   ganando N vale cuatros queridos
 |   ['faltas', N]         ganando N faltas envido queridos
 |   ['sin_envido_perdido'] sin que te ganen un envido
 |   ['sin_truco_perdido'] sin que te ganen un truco querido
 |   ['envido_y_truco']    ganando un envido y un truco querido
 |   ['sumar', N]          sumando N puntos o más en esa partida
 |   ['que_no_sume']       que la máquina no sume nada
 |   ['sin_cantar', canto] sin cantar ese canto
 |   ['en_dos_bazas']      ganando la mano en dos bazas
 |   ['gana_el_truco']     ganando un truco querido en la mano
 |   ['contraflor_al_resto'] ganando una contraflor al resto querida
 |   ['serie_sin_perder']  ganando la serie sin perder ninguna partida
 |   ['final_por', N]      ganando la final del torneo por N o más
 |   ['final_sin_envido_perdido'] sin que te ganen un envido en la final
 |
 | Los rivales son los del ranking, con su nivel de máquina. Los jefes son inventados, como sus bares.
 */

return [
    [
        'clave' => 'boedo',
        'nombre' => 'Boedo',
        'dice' => 'El club del barrio. Es la escalera de siempre, con tu progreso.',
        'mesa' => 'madera',
        'escalera' => true,
        'niveles' => [
            ['escalon' => 1, 'segundo' => ['por', 8], 'tercero' => ['sin_mazo']],
            ['escalon' => 2, 'segundo' => ['sumar', 2], 'tercero' => ['en_dos_bazas']],
            ['escalon' => 3, 'segundo' => ['por', 8], 'tercero' => ['envidos', 1]],
            ['escalon' => 4, 'segundo' => ['sin_cantar', 'truco'], 'tercero' => ['sin_cantar', 'falta_envido']],
            ['escalon' => 5, 'segundo' => ['por', 8], 'tercero' => ['trucos', 1]],
            ['escalon' => 6, 'segundo' => ['sumar', 4], 'tercero' => ['contraflor_al_resto']],
            ['escalon' => 7, 'segundo' => ['sumar', 4], 'tercero' => ['sin_cantar', 'envido']],
            ['escalon' => 8, 'segundo' => ['por', 8], 'tercero' => ['envido_y_truco']],
        ],
        'jefe' => [
            'nombre' => 'Don Anselmo', 'quien' => 'el dueño de la mesa', 'bar' => 'Club Social Boedo', 'carta' => '1-espada',
            'mania' => null, 'dorso' => 'rombos', 'mazo' => 'clasico', 'titulo' => 'del-club',
        ],
    ],
    [
        'clave' => 'san-telmo',
        'nombre' => 'San Telmo',
        'dice' => 'Adoquines, anticuarios y la feria de los domingos.',
        'mesa' => 'adoquines',
        'niveles' => [
            ['tipo' => 'partida', 'rival' => 'Tito Pereyra', 'segundo' => ['por', 8], 'tercero' => ['sin_mazo']],
            ['tipo' => 'mano', 'desafio' => 'el-envido-esta-primero', 'segundo' => ['sumar', 3], 'tercero' => ['gana_el_truco']],
            ['tipo' => 'cuatro', 'nivel' => 2, 'segundo' => ['por', 8], 'tercero' => ['envidos', 2]],
            ['tipo' => 'partida', 'rival' => 'El Gringo Bauer', 'segundo' => ['por', 8], 'tercero' => ['sin_envido_perdido']],
            ['tipo' => 'serie', 'rival' => 'La Colorada', 'segundo' => ['por', 8], 'tercero' => ['serie_sin_perder']],
            ['tipo' => 'jefe', 'segundo' => ['por', 8], 'tercero' => ['trucos', 2]],
        ],
        'jefe' => [
            'nombre' => 'Don Ceferino', 'quien' => 'el anticuario', 'bar' => 'Bar El Gramófono', 'carta' => '12-oro', 'nivel' => 2,
            'mania' => 'nunca_se_va', 'dorso' => 'grabado', 'mazo' => 'catalan', 'titulo' => 'anticuario',
            'premios' => [['dorso', 'marmolado']],
        ],
    ],
    [
        'clave' => 'la-boca',
        'nombre' => 'La Boca',
        'dice' => 'Conventillos de chapa, el puerto y el Riachuelo.',
        'mesa' => 'chapa',
        'niveles' => [
            ['tipo' => 'partida', 'rival' => 'La Colorada', 'segundo' => ['por', 8], 'tercero' => ['trucos', 1]],
            ['tipo' => 'cuatro', 'nivel' => 3, 'segundo' => ['por', 8], 'tercero' => ['sin_mazo']],
            ['tipo' => 'mano', 'desafio' => 'irse-a-tiempo', 'segundo' => ['sumar', 1], 'tercero' => ['sin_mazo']],
            ['tipo' => 'partida', 'rival' => 'El Zurdo Medina', 'segundo' => ['por', 8], 'tercero' => ['retrucos', 1]],
            ['tipo' => 'copa', 'nivel' => 3, 'segundo' => ['final_por', 8], 'tercero' => ['final_sin_envido_perdido']],
            ['tipo' => 'jefe', 'segundo' => ['por', 8], 'tercero' => ['sin_envido_perdido']],
        ],
        'jefe' => [
            'nombre' => 'El Grúa Bianchi', 'quien' => 'estibador del puerto', 'bar' => 'Cantina La Grúa', 'carta' => '11-copa', 'nivel' => 3,
            'mania' => 'canta_todo', 'dorso' => 'ondas', 'mazo' => 'fileteado', 'titulo' => 'estibador',
            'premios' => [['dorso', 'senales']],
        ],
    ],
    [
        'clave' => 'pompeya',
        'nombre' => 'Pompeya',
        'dice' => 'Niebla, paredón y la vuelta del tango.',
        'mesa' => 'tarot',
        'niveles' => [
            ['tipo' => 'partida', 'rival' => 'Doña Elvira', 'mesa' => 'bordado', 'mazo' => 'bordado', 'segundo' => ['por', 8], 'tercero' => ['envidos', 2]],
            ['tipo' => 'mano', 'desafio' => 'quiero-y-retruco', 'segundo' => ['sumar', 3], 'tercero' => ['sin_cantar', 'falta_envido']],
            ['tipo' => 'abajo', 'rival' => 'El Zurdo Medina', 'tanteo' => [0, 8], 'segundo' => ['por', 4], 'tercero' => ['sin_mazo']],
            ['tipo' => 'cuatro', 'nivel' => 3, 'segundo' => ['por', 8], 'tercero' => ['trucos', 1]],
            ['tipo' => 'partida', 'rival' => 'La Tana Rossi', 'segundo' => ['por', 8], 'tercero' => ['faltas', 1]],
            ['tipo' => 'jefe', 'segundo' => ['por', 8], 'tercero' => ['sin_truco_perdido']],
        ],
        'jefe' => [
            'nombre' => 'Madama Ferrante', 'quien' => 'la tarotista', 'bar' => 'Café del Farol Roto', 'carta' => '10-espada', 'nivel' => 3,
            'mania' => 'miente_siempre', 'dorso' => 'tarot', 'mazo' => 'tarot', 'titulo' => 'adivino',
            'premios' => [['dorso', 'tarot'], ['anotar', 'constelacion']],
        ],
    ],
    [
        'clave' => 'mataderos',
        'nombre' => 'Mataderos',
        'dice' => 'La feria, los corrales y los reseros de a caballo.',
        'mesa' => 'pulperia',
        'niveles' => [
            ['tipo' => 'partida', 'rival' => 'La Tana Rossi', 'segundo' => ['por', 8], 'tercero' => ['sin_mazo']],
            ['tipo' => 'cuatro', 'nivel' => 3, 'segundo' => ['por', 8], 'tercero' => ['vale_cuatros', 1]],
            ['tipo' => 'mano', 'desafio' => 'contraflor', 'segundo' => ['sumar', 7], 'tercero' => ['en_dos_bazas']],
            ['tipo' => 'serie', 'rival' => 'El Zurdo Medina', 'segundo' => ['por', 8], 'tercero' => ['serie_sin_perder']],
            ['tipo' => 'partida', 'rival' => 'Don Anselmo', 'segundo' => ['por', 8], 'tercero' => ['trucos', 2]],
            ['tipo' => 'jefe', 'segundo' => ['por', 8], 'tercero' => ['sin_mazo']],
        ],
        'jefe' => [
            'nombre' => 'El Resero', 'quien' => 'el de la tropa', 'bar' => 'Almacén La Tranquera', 'carta' => '11-basto', 'nivel' => 4,
            'mania' => 'quiere_todo', 'dorso' => 'guarda-pampa', 'mazo' => 'criollo', 'titulo' => 'resero',
        ],
    ],
    [
        'clave' => 'abasto',
        'nombre' => 'Abasto',
        'dice' => 'El mercado, los cajones de fruta y las noches largas.',
        'mesa' => 'cajon',
        'niveles' => [
            ['tipo' => 'partida', 'rival' => 'Don Anselmo', 'segundo' => ['por', 8], 'tercero' => ['por', 10]],
            ['tipo' => 'mano', 'desafio' => 'la-flor-tapa-el-envido', 'segundo' => ['sin_cantar', 'envido'], 'tercero' => ['sin_mazo']],
            ['tipo' => 'cuatro', 'nivel' => 4, 'segundo' => ['por', 8], 'tercero' => ['sin_mazo']],
            ['tipo' => 'copa', 'nivel' => 4, 'segundo' => ['final_por', 8], 'tercero' => ['final_sin_envido_perdido']],
            ['tipo' => 'partida', 'rival' => 'Don Anselmo', 'puntos' => 30, 'segundo' => ['por', 8], 'tercero' => ['envido_y_truco']],
            ['tipo' => 'jefe', 'puntos' => 30, 'tanteo' => [0, 8], 'segundo' => ['por', 4], 'tercero' => ['sin_mazo']],
        ],
        'jefe' => [
            'nombre' => 'El Patrón del Mercado', 'quien' => 'el que fía', 'bar' => 'Bar La Balanza', 'carta' => '12-espada', 'nivel' => 4,
            'mania' => 'arranca_con_ventaja', 'dorso' => 'monograma', 'mazo' => 'billete', 'titulo' => 'patron',
        ],
    ],
];
