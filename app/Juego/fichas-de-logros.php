<?php

/*
 | Los logros de la libreta del club, como datos (M23). Cada uno cuenta algo que el jugador hizo y tiene sus
 | escalones: cada escalón ganado es un sello y anota un fósforo para el nivel (decidido por Román el 15 de
 | octubre de 2026).
 |
 | "cuenta" es la columna del jugador que lo mide; se suma al cerrar cada partida (App\Juego\Titulos) y se
 | rehace desde las partidas con fosforos:recalcular. "titulo" es el título que destraba, en el escalón
 | "titulo_en" (el segundo, salvo que diga otra cosa): el título pide lo mismo que ese escalón.
 |
 | Los secretos no dicen qué piden hasta que se ganan.
 */

return [
    // El envido.
    ['clave' => 'cantor', 'tema' => 'envido', 'nombre' => 'Cantor', 'pide' => 'Envidos cantados', 'icono' => 'envido', 'cuenta' => 'envidos_cantados', 'escalones' => [10, 25, 100], 'titulo' => 'cantor'],
    ['clave' => 'tantero', 'tema' => 'envido', 'nombre' => 'Tantero', 'pide' => 'Envidos ganados', 'icono' => 'envido', 'cuenta' => 'envidos_ganados', 'escalones' => [10, 25, 100], 'titulo' => 'tantero'],
    ['clave' => 'treinta-y-tres', 'tema' => 'envido', 'nombre' => 'Treinta y tres', 'pide' => 'Envidos ganados con 33', 'icono' => 'envido', 'cuenta' => 'treinta_y_tres', 'escalones' => [1, 5, 20]],
    ['clave' => 'envido-primero', 'tema' => 'envido', 'nombre' => 'El envido está primero', 'pide' => 'Envidos ganados contestando un truco', 'icono' => 'envido', 'cuenta' => 'envido_primero', 'escalones' => [1, 10, 30]],
    ['clave' => 'falta-envido', 'tema' => 'envido', 'nombre' => 'Falta envido', 'pide' => 'Faltas envido queridos ganados', 'icono' => 'envido', 'cuenta' => 'faltas_ganadas', 'escalones' => [1, 5, 15], 'titulo' => 'falta-envido', 'titulo_en' => 1],

    // El truco.
    ['clave' => 'truquero', 'tema' => 'truco', 'nombre' => 'Truquero', 'pide' => 'Trucos queridos ganados', 'icono' => 'truco', 'cuenta' => 'trucos_ganados', 'escalones' => [10, 25, 100], 'titulo' => 'truquero'],
    ['clave' => 'quiero', 'tema' => 'truco', 'nombre' => 'Quiero', 'pide' => 'Trucos que quisiste y ganaste', 'icono' => 'quiero', 'cuenta' => 'quieros_ganados', 'escalones' => [10, 50, 150]],
    ['clave' => 'retruquero', 'tema' => 'truco', 'nombre' => 'Retruquero', 'pide' => 'Retrucos queridos ganados', 'icono' => 'truco', 'cuenta' => 'retrucos_ganados', 'escalones' => [3, 10, 30], 'titulo' => 'retruquero'],
    ['clave' => 'vale-cuatro', 'tema' => 'truco', 'nombre' => 'Vale cuatro', 'pide' => 'Vale cuatros queridos ganados', 'icono' => 'truco', 'cuenta' => 'vale_cuatros_ganados', 'escalones' => [1, 5, 15], 'titulo' => 'vale-cuatro', 'titulo_en' => 1],
    ['clave' => 'chamuyero', 'tema' => 'truco', 'nombre' => 'Chamuyero', 'pide' => 'Trucos ganados sin que te quieran y sin cartas bravas', 'icono' => 'no-quiero', 'cuenta' => 'chamuyos', 'escalones' => [3, 10, 30], 'titulo' => 'chamuyero'],

    // La flor.
    ['clave' => 'florido', 'tema' => 'flor', 'nombre' => 'Florido', 'pide' => 'Flores cantadas', 'icono' => 'flor', 'cuenta' => 'flores_cantadas', 'escalones' => [3, 10, 30], 'titulo' => 'florido'],
    ['clave' => 'contraflor', 'tema' => 'flor', 'nombre' => 'Contraflor', 'pide' => 'Contraflores ganadas', 'icono' => 'flor', 'cuenta' => 'contraflores_ganadas', 'escalones' => [1, 5, 15]],

    // Las partidas.
    ['clave' => 'jugadas', 'tema' => 'partidas', 'nombre' => 'De la casa', 'pide' => 'Partidas terminadas', 'icono' => 'repartir', 'cuenta' => 'partidas_jugadas', 'escalones' => [25, 100, 400]],
    ['clave' => 'ganadas', 'tema' => 'partidas', 'nombre' => 'Ganador', 'pide' => 'Partidas ganadas', 'icono' => 'ranking', 'cuenta' => 'partidas_ganadas', 'escalones' => [10, 50, 200]],
    ['clave' => 'racha', 'tema' => 'partidas', 'nombre' => 'Racha', 'pide' => 'Partidas ganadas seguidas, las que cuentan para el ranking', 'icono' => 'repetir', 'cuenta' => 'mejor_racha', 'escalones' => [3, 5, 10]],
    ['clave' => 'de-atras', 'tema' => 'partidas', 'nombre' => 'De atrás', 'pide' => 'Partidas ganadas después de ir diez puntos abajo', 'icono' => 'tiempo', 'cuenta' => 'remontadas', 'escalones' => [1, 5, 20]],
    ['clave' => 'en-las-malas', 'tema' => 'partidas', 'nombre' => 'En las malas', 'pide' => 'Partidas ganadas sin que el rival salga de las malas', 'icono' => 'mazo', 'cuenta' => 'sin_salir_de_malas', 'escalones' => [1, 5, 20]],
    ['clave' => 'pardero', 'tema' => 'partidas', 'nombre' => 'Pardero', 'pide' => 'Manos ganadas después de una primera parda', 'icono' => 'mano', 'cuenta' => 'manos_con_parda', 'escalones' => [3, 10, 30], 'titulo' => 'pardero'],
    ['clave' => 'la-dificil', 'tema' => 'partidas', 'nombre' => 'Contra la difícil', 'pide' => 'Partidas ganadas a la máquina difícil', 'icono' => 'bot', 'cuenta' => 'dificil_ganadas', 'escalones' => [1, 10, 50]],
    ['clave' => 'la-ultra', 'tema' => 'partidas', 'nombre' => 'Contra la ultra', 'pide' => 'Partidas ganadas a la máquina ultra difícil', 'icono' => 'bot', 'cuenta' => 'ultra_ganadas', 'escalones' => [1, 10, 50]],

    // Los otros juegos.
    ['clave' => 'campeon', 'tema' => 'juegos', 'nombre' => 'Campeón', 'pide' => 'Torneos ganados', 'icono' => 'torneo', 'cuenta' => 'torneos_ganados', 'escalones' => [1, 5, 20], 'titulo' => 'campeon', 'titulo_en' => 1],
    ['clave' => 'desafiante', 'tema' => 'juegos', 'nombre' => 'Desafiante', 'pide' => 'Desafíos superados', 'icono' => 'desafio', 'cuenta' => 'desafios_pasados', 'escalones' => [8], 'titulo' => 'desafiante', 'titulo_en' => 1],
    ['clave' => 'de-a-cuatro', 'tema' => 'juegos', 'nombre' => 'Pareja', 'pide' => 'Partidas de a cuatro ganadas', 'icono' => 'de-a-cuatro', 'cuenta' => 'de_a_cuatro_ganadas', 'escalones' => [1, 10, 50]],
    ['clave' => 'senas', 'tema' => 'juegos', 'nombre' => 'Señero', 'pide' => 'Señas hechas al compañero', 'icono' => 'sena', 'cuenta' => 'senas_hechas', 'escalones' => [10, 50, 200]],
    ['clave' => 'revancha', 'tema' => 'juegos', 'nombre' => 'La revancha', 'pide' => 'Revanchas ganadas', 'icono' => 'repetir', 'cuenta' => 'revanchas_ganadas', 'escalones' => [1, 5, 20]],

    // La gira de los clubes: un sello por barrio, con el título que deja su jefe, y tres logros de toda la gira.
    ['clave' => 'del-club', 'tema' => 'gira', 'nombre' => 'Boedo', 'pide' => 'Vencer a Don Anselmo en el Club Social Boedo', 'icono' => 'gira', 'cuenta' => 'barrios_completos', 'escalones' => [1], 'titulo' => 'del-club', 'titulo_en' => 1],
    ['clave' => 'anticuario', 'tema' => 'gira', 'nombre' => 'San Telmo', 'pide' => 'Vencer a Don Ceferino en el Bar El Gramófono', 'icono' => 'gira', 'cuenta' => 'barrios_completos', 'escalones' => [2], 'titulo' => 'anticuario', 'titulo_en' => 1],
    ['clave' => 'estibador', 'tema' => 'gira', 'nombre' => 'La Boca', 'pide' => 'Vencer al Grúa Bianchi en la Cantina La Grúa', 'icono' => 'gira', 'cuenta' => 'barrios_completos', 'escalones' => [3], 'titulo' => 'estibador', 'titulo_en' => 1],
    ['clave' => 'adivino', 'tema' => 'gira', 'nombre' => 'Pompeya', 'pide' => 'Vencer a Madama Ferrante en el Café del Farol Roto', 'icono' => 'gira', 'cuenta' => 'barrios_completos', 'escalones' => [4], 'titulo' => 'adivino', 'titulo_en' => 1],
    ['clave' => 'resero', 'tema' => 'gira', 'nombre' => 'Mataderos', 'pide' => 'Vencer al Resero en el Almacén La Tranquera', 'icono' => 'gira', 'cuenta' => 'barrios_completos', 'escalones' => [5], 'titulo' => 'resero', 'titulo_en' => 1],
    ['clave' => 'patron', 'tema' => 'gira', 'nombre' => 'Abasto', 'pide' => 'Vencer al Patrón del Mercado en el Bar La Balanza', 'icono' => 'gira', 'cuenta' => 'barrios_completos', 'escalones' => [6], 'titulo' => 'patron', 'titulo_en' => 1],
    ['clave' => 'porteno', 'tema' => 'gira', 'nombre' => 'Porteño', 'pide' => 'Paradas de la gira pasadas', 'icono' => 'gira', 'cuenta' => 'paradas_pasadas', 'escalones' => [12, 24, 38], 'titulo' => 'porteno', 'titulo_en' => 3],
    ['clave' => 'tres-de-tres', 'tema' => 'gira', 'nombre' => 'Tres de tres', 'pide' => 'Paradas pasadas con los tres fósforos', 'icono' => 'gira', 'cuenta' => 'paradas_perfectas', 'escalones' => [5, 15, 38]],
    ['clave' => 'a-la-primera', 'tema' => 'gira', 'nombre' => 'A la primera', 'pide' => 'Jefes vencidos en el primer intento', 'icono' => 'gira', 'cuenta' => 'jefes_a_la_primera', 'escalones' => [1, 3, 6]],

    // Los secretos.
    ['clave' => 'tres-pardas', 'tema' => 'secretos', 'nombre' => 'Las tres pardas', 'pide' => 'Ganar una mano siendo mano con las tres bazas pardas', 'icono' => 'mano', 'cuenta' => 'tres_pardas', 'escalones' => [1], 'secreto' => true],
    ['clave' => 'treinta-a-cero', 'tema' => 'secretos', 'nombre' => 'Treinta a cero', 'pide' => 'Ganar una partida a 30 sin que el rival anote', 'icono' => 'ranking', 'cuenta' => 'treinta_a_cero', 'escalones' => [1], 'secreto' => true],
    ['clave' => 'ancho-contra-ancho', 'tema' => 'secretos', 'nombre' => 'Ancho contra ancho', 'pide' => 'Ganar una baza con el ancho de espada contra el ancho de basto', 'icono' => 'espada', 'cuenta' => 'ancho_contra_ancho', 'escalones' => [1], 'secreto' => true],
    ['clave' => 'al-resto', 'tema' => 'secretos', 'nombre' => 'Al resto', 'pide' => 'Ganar una contraflor al resto querida', 'icono' => 'flor', 'cuenta' => 'contraflor_al_resto', 'escalones' => [1], 'secreto' => true],
];
