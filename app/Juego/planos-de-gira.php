<?php

/*
 | El plano de cada barrio de la gira, en sus dos formas: acostado (escritorio, 640 x 448) y parado (celular,
 | 400 x 580). Cada uno pisa lo que cambia de la grilla de base (App\View\Components\Gira\Plano::GEOMETRIA):
 |
 | - camino: el recorrido de las paradas, punto por punto por las calles. Termina en la puerta del bar del jefe.
 | - bar: dónde está el bar del jefe [x, y, ancho, alto]. La puerta queda abajo, en el medio.
 | - filas: las manzanas de cada fila [y, alto], cuando el barrio termina en el río.
 | - avenida / costado / costadoHasta: la avenida de abajo y la del costado (null si no hay).
 | - abajo: la fila de manzanas de abajo (null cuando abajo está el río).
 | - rio: desde qué altura empieza el agua.
 | - anchas: calles anchas [['v', x] o ['h', y], ancho].
 | - diagonal: una calle en diagonal [x1, y1, x2, y2, ancho].
 | - quitar: las manzanas que no se dibujan [columna, fila], porque ahí va un edificio grande.
 | - plaza / adoquines: la manzana que es plaza y la calle vertical de adoquines.
 | - edificios: lo propio del barrio, dibujado en elevación (components/gira/edificio.blade.php).
 | - nombres: los nombres de las calles [texto, x, y, giro en grados] (y si es del agua, 'agua' al final).
 |
 | Cada barrio tiene un recorrido de otra forma: en Boedo una "S" que sube; en San Telmo una escalera; en La Boca
 | baja por Caminito hasta el río; en Pompeya la diagonal de Sáenz hasta el Puente Alsina; en Mataderos una
 | espiral alrededor de la recova; en el Abasto abraza el mercado. Lugares de referencia: el Riachuelo y el
 | transbordador, la recova en "U" con El Resero, la iglesia de Pompeya, el mercado Art Déco sobre Corrientes.
 | Todo inspirado, nada calcado: es una guía inventada con calles de verdad.
 */

return [
    'boedo' => [
        'acostado' => [
            'camino' => [[60, 379], [293, 379], [293, 253], [161, 253], [161, 141], [488, 141]],
            'bar' => [440, 64, 96, 60],
            'plaza' => [0, 1],
            'quitar' => [[3, 1], [3, 2]],
            'edificios' => [['cafe', 306, 304], ['cancha', 436, 152, 110, 202]],
            'nombres' => [
                ['Castro Barros', 164.5, 118, -90], ['Av. Boedo', 296.5, 345, -90], ['Maza', 428.5, 118, -90],
                ['Independencia', 44, 144.5, 0], ['Carlos Calvo', 330, 256.5, 0],
                ['Av. San Juan', 330, 382.5, 0], ['Av. La Plata', 575, 250, -90],
            ],
        ],
        'parado' => [
            'camino' => [[60, 424], [245, 424], [245, 319], [135, 319], [135, 123], [300, 123]],
            'bar' => [256, 54, 88, 56],
            'plaza' => [0, 1],
            'quitar' => [[2, 1], [2, 2]],
            'edificios' => [['cafe', 258, 358], ['cancha', 254, 132, 92, 178]],
            'nombres' => [
                ['Castro Barros', 138.5, 404, -90], ['Av. Boedo', 248.5, 104, -90],
                ['Independencia', 36, 126.5, 0], ['Carlos Calvo', 36, 224.5, 0],
                ['Av. San Juan', 258, 427.5, 0], ['Av. La Plata', 372, 330, -90],
            ],
        ],
    ],

    'san-telmo' => [
        'acostado' => [
            'camino' => [[60, 379], [161, 379], [161, 253], [293, 253], [293, 141], [425, 141], [492, 141]],
            'bar' => [440, 64, 96, 60],
            'plaza' => [1, 1],
            'adoquines' => 1,
            'edificios' => [['mercado', 58, 70], ['iglesia', 332, 70]],
            'nombres' => [
                ['Bolívar', 164.5, 118, -90], ['Defensa', 296.5, 340, -90], ['Balcarce', 428.5, 118, -90],
                ['Humberto 1º', 186, 144.5, 0], ['Carlos Calvo', 330, 256.5, 0],
                ['Av. San Juan', 196, 382.5, 0], ['Paseo Colón', 575, 250, -90],
            ],
        ],
        'parado' => [
            'camino' => [[60, 424], [135, 424], [135, 319], [245, 319], [245, 221], [245, 123], [298, 123]],
            'bar' => [256, 54, 88, 56],
            'plaza' => [1, 1],
            'adoquines' => 1,
            'edificios' => [['mercado', 43, 52], ['iglesia', 162, 54]],
            'nombres' => [
                ['Bolívar', 138.5, 104, -90], ['Defensa', 248.5, 404, -90],
                ['Humberto 1º', 36, 126.5, 0], ['Carlos Calvo', 36, 224.5, 0],
                ['Av. San Juan', 190, 427.5, 0], ['Paseo Colón', 372, 330, -90],
            ],
        ],
    ],

    'la-boca' => [
        'acostado' => [
            'camino' => [[60, 141], [161, 141], [161, 253], [293, 253], [380, 343], [496, 343]],
            'bar' => [452, 268, 88, 58],
            'filas' => [[40, 90], [152, 90], [264, 66]],
            'avenida' => 343, 'abajo' => null, 'rio' => 358, 'costadoHasta' => 330,
            'plaza' => [0, 2],
            'diagonal' => [293, 253, 380, 343, 14],
            'edificios' => [
                ['conventillos', 306, 304, 2], ['conventillos', 362, 268, 3],
                ['transbordador', 552, 300], ['barco', 110, 378],
            ],
            'nombres' => [
                ['Necochea', 164.5, 118, -90], ['Magallanes', 428.5, 240, -90],
                ['Lamadrid', 186, 144.5, 0], ['Olavarría', 440, 256.5, 0],
                ['Av. Pedro de Mendoza', 52, 346.5, 0], ['Caminito', 322, 285, 46],
                ['Almirante Brown', 575, 250, -90], ['Riachuelo', 230, 398, 0, 'agua'],
            ],
        ],
        'parado' => [
            'camino' => [[60, 123], [245, 123], [245, 221], [135, 221], [135, 319], [215, 430], [300, 430]],
            'bar' => [258, 352, 84, 56],
            'filas' => [[34, 80], [132, 80], [230, 80], [328, 86]],
            'avenida' => 430, 'abajo' => null, 'rio' => 446, 'costadoHasta' => 414,
            'plaza' => [0, 0],
            'diagonal' => [135, 319, 215, 430, 12],
            'edificios' => [
                ['conventillos', 146, 380, 2], ['conventillos', 188, 334, 3],
                ['transbordador', 300, 452], ['barco', 64, 482],
            ],
            'nombres' => [
                ['Necochea', 138.5, 210, -90], ['Magallanes', 248.5, 404, -90],
                ['Lamadrid', 258, 126.5, 0], ['Olavarría', 36, 224.5, 0],
                ['Av. Pedro de Mendoza', 30, 433.5, 0], ['Caminito', 158, 356, 54],
                ['Almirante Brown', 372, 330, -90], ['Riachuelo', 150, 528, 0, 'agua'],
            ],
        ],
    ],

    'pompeya' => [
        'acostado' => [
            'camino' => [[40, 63], [161, 146], [293, 236.6], [448, 343], [502, 343]],
            'bar' => [462, 268, 80, 58],
            'filas' => [[40, 90], [152, 90], [264, 66]],
            'avenida' => 343, 'abajo' => null, 'rio' => 358, 'costadoHasta' => 330,
            'diagonal' => [24, 52, 470, 358, 20],
            'edificios' => [
                ['iglesia-gotica', 326, 40], ['paredon', 24, 356, 410], ['puente', 470, 358, 556, 417], ['farol', 548, 296],
            ],
            'nombres' => [
                ['Centenera', 164.5, 118, -90], ['Esquiú', 428.5, 118, -90],
                ['Traful', 186, 144.5, 0], ['Tabaré', 340, 256.5, 0], ['Av. Sáenz', 196, 178, 34.5],
                ['Av. Amancio Alcorta', 52, 346.5, 0], ['Av. Perito Moreno', 575, 250, -90],
                ['Riachuelo', 200, 398, 0, 'agua'], ['Puente Alsina', 380, 404, 0, 'agua'],
            ],
        ],
        'parado' => [
            'camino' => [[52, 51.1], [135, 166.3], [245, 319], [325, 430], [82, 430]],
            'bar' => [40, 350, 84, 56],
            'filas' => [[34, 80], [132, 80], [230, 80], [328, 86]],
            'avenida' => 430, 'abajo' => null, 'rio' => 446, 'costadoHasta' => 414,
            'diagonal' => [36, 29, 400, 534, 18],
            'edificios' => [
                ['iglesia-gotica', 266, 30], ['paredon', 20, 444, 300], ['puente', 336, 446, 400, 535], ['farol', 132, 376],
            ],
            'nombres' => [
                ['Centenera', 138.5, 300, -90], ['Esquiú', 248.5, 104, -90],
                ['Traful', 150, 126.5, 0], ['Tabaré', 36, 224.5, 0], ['Av. Sáenz', 166, 210, 54.2],
                ['Alcorta', 334, 433.5, 0], ['Perito Moreno', 372, 330, -90],
                ['Riachuelo', 60, 500, 0, 'agua'], ['Puente Alsina', 236, 512, 0, 'agua'],
            ],
        ],
    ],

    'mataderos' => [
        'acostado' => [
            'camino' => [[60, 379], [425, 379], [425, 141], [161, 141], [161, 253], [228, 253]],
            'bar' => [186, 176, 84, 58],
            'anchas' => [['v', 293, 32], ['h', 253, 32]],
            'quitar' => [[2, 1], [3, 1], [3, 2]],
            'edificios' => [['recova', 312, 158, 98, 76], ['corrales', 436, 152, 110, 202]],
            'nombres' => [
                ['Murguiondo', 164.5, 118, -90], ['Lisandro de la Torre', 297, 132, -90],
                ['Directorio', 44, 144.5, 0], ['Av. de los Corrales', 320, 256.5, 0],
                ['Av. Alberdi', 440, 382.5, 0], ['Av. Eva Perón', 575, 250, -90],
            ],
        ],
        'parado' => [
            'camino' => [[60, 424], [245, 424], [245, 123], [135, 123], [135, 319], [190, 319]],
            'bar' => [148, 244, 84, 56],
            'anchas' => [['v', 245, 28], ['h', 319, 28]],
            'quitar' => [[2, 0], [2, 1], [2, 2]],
            'edificios' => [['recova', 262, 236, 82, 64], ['corrales', 254, 34, 92, 178]],
            'nombres' => [
                ['Murguiondo', 138.5, 404, -90],
                ['Directorio', 36, 126.5, 0], ['Av. de los Corrales', 262, 322.5, 0],
                ['Av. Alberdi', 258, 427.5, 0], ['Av. Eva Perón', 372, 330, -90],
            ],
        ],
    ],

    'abasto' => [
        'acostado' => [
            'camino' => [[46, 253], [425, 253], [425, 141], [92, 141]],
            'bar' => [48, 64, 88, 58],
            'anchas' => [['h', 141, 30]],
            'quitar' => [[1, 1], [2, 1]],
            'edificios' => [['mercado-abasto', 172, 158, 242, 84], ['cajones', 452, 290]],
            'nombres' => [
                ['Lavalle', 164.5, 350, -90], ['Sarmiento', 428.5, 350, -90],
                ['Av. Corrientes', 440, 144.5, 0], ['Agüero', 440, 256.5, 0],
                ['Av. Pueyrredón', 196, 382.5, 0], ['Gallo', 575, 250, -90],
            ],
        ],
        'parado' => [
            'camino' => [[46, 424], [135, 424], [135, 319], [245, 319], [245, 221], [82, 221]],
            'bar' => [40, 146, 84, 56],
            'anchas' => [['h', 221, 28]],
            'quitar' => [[0, 2], [1, 2]],
            'edificios' => [['mercado-abasto', 34, 237, 202, 73], ['cajones', 270, 346]],
            'nombres' => [
                ['Lavalle', 138.5, 104, -90], ['Sarmiento', 248.5, 404, -90],
                ['Av. Corrientes', 258, 224.5, 0], ['Agüero', 258, 322.5, 0],
                ['Av. Pueyrredón', 150, 427.5, 0], ['Gallo', 372, 330, -90],
            ],
        ],
    ],
];
