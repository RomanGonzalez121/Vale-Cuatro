<?php

/*
 | El camino de premios, como datos: cada ficha es un premio y el nivel en que se destraba (o el escalón de
 | la escalera, para los tres dorsos que se ganan ahí). Lo de nivel 1 es de entrada: lo tiene todo el mundo.
 |
 | "listo" dice si ya está dibujado. Lo que no está figura en el camino como "Próximamente" y, cuando llega,
 | queda abierto solo para quien ya pasó ese nivel: lo destrabado se calcula por nivel, no se guarda.
 |
 | Elegido por Román el 15 de octubre de 2026: cuarenta niveles, las cartas cada tres niveles en el orden
 | del truco y lo más fino arriba. Los nombres de los mazos se eligen en la tanda 5.
 */

return [
    // De entrada.
    ['tipo' => 'carta', 'clave' => '4-copa', 'nombre' => 'El 4 de copa', 'nivel' => 1, 'listo' => true],
    ['tipo' => 'dorso', 'clave' => 'fabrica', 'nombre' => 'Isotipos', 'nivel' => 1, 'listo' => true],

    // Los dorsos de la escalera del club.
    ['tipo' => 'dorso', 'clave' => 'fosforos', 'nombre' => 'Fósforos', 'escalon' => 3, 'listo' => true],
    ['tipo' => 'dorso', 'clave' => 'tinta', 'nombre' => 'Tinta', 'escalon' => 6, 'listo' => true],
    ['tipo' => 'dorso', 'clave' => 'ronda', 'nombre' => 'Ronda', 'escalon' => 8, 'listo' => true],

    // El camino.
    ['tipo' => 'distintivo', 'clave' => 'un-fosforo', 'nombre' => 'Un fósforo', 'nivel' => 2, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'rombos', 'nombre' => 'Rombos', 'nivel' => 3, 'listo' => true],
    ['tipo' => 'carta', 'clave' => '5-espada', 'nombre' => 'El 5 de espada', 'nivel' => 4, 'listo' => true],
    ['tipo' => 'mesa', 'clave' => 'pano-petroleo', 'nombre' => 'Paño petróleo', 'nivel' => 5, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'cuadrado', 'nombre' => 'El cuadrado', 'nivel' => 6, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '6-oro', 'nombre' => 'El 6 de oro', 'nivel' => 7, 'listo' => true],
    ['tipo' => 'anotar', 'clave' => 'palotes', 'nombre' => 'Palotes con lápiz', 'nivel' => 7, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'damero', 'nombre' => 'Damero', 'nivel' => 8, 'listo' => false],
    ['tipo' => 'mazo', 'clave' => 'noche', 'nombre' => 'Mazo de noche', 'nivel' => 9, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '7-copa', 'nombre' => 'El 7 de copa', 'nivel' => 10, 'listo' => true],
    ['tipo' => 'mesa', 'clave' => 'marmol', 'nombre' => 'Mármol de café', 'nivel' => 10, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'grupo-de-cinco', 'nombre' => 'Grupo de cinco', 'nivel' => 11, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'lunares', 'nombre' => 'Lunares', 'nivel' => 12, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '10-basto', 'nombre' => 'El 10 de basto', 'nivel' => 13, 'listo' => true],
    ['tipo' => 'anotar', 'clave' => 'tiza', 'nombre' => 'Tiza', 'nivel' => 13, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'pizarron', 'nombre' => 'Pizarrón', 'nivel' => 14, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'quince', 'nombre' => 'El 15', 'nivel' => 15, 'listo' => false],
    ['tipo' => 'mazo', 'clave' => 'una-tinta', 'nombre' => 'Mazo a una tinta', 'nivel' => 15, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '11-espada', 'nombre' => 'El 11 de espada', 'nivel' => 16, 'listo' => true],
    ['tipo' => 'dorso', 'clave' => 'zigzag', 'nombre' => 'Zigzag', 'nivel' => 16, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'madera', 'nombre' => 'Madera del club', 'nivel' => 17, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'cruzados', 'nombre' => 'Dos cruzados', 'nivel' => 18, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '12-oro', 'nombre' => 'El 12 de oro', 'nivel' => 19, 'listo' => true],
    ['tipo' => 'mazo', 'clave' => 'palos-de-fosforo', 'nombre' => 'Mazo de palos de fósforo', 'nivel' => 19, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'baldosa', 'nombre' => 'Baldosa calcárea', 'nivel' => 20, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'ramitas', 'nombre' => 'Ramitas', 'nivel' => 20, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'siete', 'nombre' => 'El 7', 'nivel' => 21, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '1-copa', 'nombre' => 'El 1 de copa', 'nivel' => 22, 'listo' => true],
    ['tipo' => 'mazo', 'clave' => 'letras-grandes', 'nombre' => 'Mazo de letras grandes', 'nivel' => 22, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'mantel', 'nombre' => 'Mantel de bodegón', 'nivel' => 23, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'ondas', 'nombre' => 'Ondas', 'nivel' => 24, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'cuatro', 'nombre' => 'El 4', 'nivel' => 24, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '2-basto', 'nombre' => 'El 2 de basto', 'nivel' => 25, 'listo' => true],
    ['tipo' => 'mesa', 'clave' => 'milonga', 'nombre' => 'Milonga', 'nivel' => 25, 'listo' => false],
    ['tipo' => 'mazo', 'clave' => 'mazo-6', 'nombre' => 'Sexto mazo', 'nivel' => 26, 'listo' => false],
    ['tipo' => 'anotar', 'clave' => 'porotos', 'nombre' => 'Porotos y garbanzos', 'nivel' => 27, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'tanteador', 'nombre' => 'Tanteador', 'nivel' => 27, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '3-copa', 'nombre' => 'El 3 de copa', 'nivel' => 28, 'listo' => true],
    ['tipo' => 'mesa', 'clave' => 'diario', 'nombre' => 'Diario de café', 'nivel' => 28, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'treinta', 'nombre' => 'El 30', 'nivel' => 29, 'listo' => false],
    ['tipo' => 'mazo', 'clave' => 'mazo-7', 'nombre' => 'Séptimo mazo', 'nivel' => 29, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'pana', 'nombre' => 'Pana del club inglés', 'nivel' => 30, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'estrella', 'nombre' => 'Estrella', 'nivel' => 30, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '7-oro', 'nombre' => 'El 7 de oro', 'nivel' => 31, 'listo' => true],
    ['tipo' => 'mazo', 'clave' => 'mazo-8', 'nombre' => 'Octavo mazo', 'nivel' => 31, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'cuero', 'nombre' => 'Cuero de estancia', 'nivel' => 32, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'treinta-y-tres', 'nombre' => 'El 33', 'nivel' => 33, 'listo' => false],
    ['tipo' => 'mazo', 'clave' => 'mazo-9', 'nombre' => 'Noveno mazo', 'nivel' => 33, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '7-espada', 'nombre' => 'El 7 de espada', 'nivel' => 34, 'listo' => true],
    ['tipo' => 'mesa', 'clave' => 'pulperia', 'nombre' => 'Pulpería', 'nivel' => 34, 'listo' => false],
    ['tipo' => 'dorso', 'clave' => 'monograma', 'nombre' => 'Monograma', 'nivel' => 35, 'listo' => false],
    ['tipo' => 'mazo', 'clave' => 'mazo-10', 'nombre' => 'Décimo mazo', 'nivel' => 35, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'cobre', 'nombre' => 'Cobre de la pava', 'nivel' => 36, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '1-basto', 'nombre' => 'El ancho de basto', 'nivel' => 37, 'listo' => true],
    ['tipo' => 'mazo', 'clave' => 'mazo-11', 'nombre' => 'Mazo once', 'nivel' => 37, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'art-deco', 'nombre' => 'Art Déco', 'nivel' => 38, 'listo' => false],
    ['tipo' => 'distintivo', 'clave' => 'cuatro-palos', 'nombre' => 'Los cuatro palos', 'nivel' => 38, 'listo' => false],
    ['tipo' => 'mazo', 'clave' => 'mazo-12', 'nombre' => 'Mazo doce', 'nivel' => 39, 'listo' => false],
    ['tipo' => 'mesa', 'clave' => 'grafito', 'nombre' => 'Grafito y latón', 'nivel' => 39, 'listo' => false],
    ['tipo' => 'carta', 'clave' => '1-espada', 'nombre' => 'El ancho de espada', 'nivel' => 40, 'listo' => true],
    ['tipo' => 'mesa', 'clave' => 'fileteado', 'nombre' => 'Fileteado porteño', 'nivel' => 40, 'listo' => false],
];
