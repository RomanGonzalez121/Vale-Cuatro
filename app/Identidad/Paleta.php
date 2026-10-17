<?php

namespace App\Identidad;

/**
 * Los colores de Vale Cuatro y la medición de contraste WCAG entre ellos.
 *
 * Los valores son los mismos tokens de resources/css/app.css. Esta clase existe
 * para que el contraste se mida con código y no a ojo: la página /identidad lo
 * muestra y un test falla si una combinación en uso deja de alcanzar el mínimo.
 */
class Paleta
{
    public const COLORES = [
        'pano' => ['nombre' => 'Paño', 'hex' => '#1F5A46', 'uso' => 'Fondo de la mesa. Plano, sin textura ni degradado.'],
        'pano-hondo' => ['nombre' => 'Paño hondo', 'hex' => '#153F32', 'uso' => 'Tanteador y barras.'],
        'naipe' => ['nombre' => 'Naipe', 'hex' => '#FBFAF5', 'uso' => 'Cartas, texto sobre el paño y fondo de las páginas de lectura.'],
        'tinta' => ['nombre' => 'Tinta', 'hex' => '#161A18', 'uso' => 'Texto sobre naipe y contornos.'],
        'oro' => ['nombre' => 'Oro', 'hex' => '#F0B429', 'uso' => 'Puntos y envido.'],
        'copa' => ['nombre' => 'Copa', 'hex' => '#C93327', 'uso' => 'Truco y sus subidas, "no quiero" y alertas.'],
        'espada' => ['nombre' => 'Espada', 'hex' => '#2A5CAA', 'uso' => 'Información y links.'],
        'basto' => ['nombre' => 'Basto', 'hex' => '#2A7D4F', 'uso' => '"Quiero" y confirmaciones.'],
        'fosforo' => ['nombre' => 'Fósforo', 'hex' => '#E8C98A', 'uso' => 'El palito de los fósforos del tanteador. La cabeza va en Copa.'],
    ];

    /** Mínimo WCAG AA para texto normal. */
    public const TEXTO_NORMAL = 4.5;

    /** Mínimo WCAG AA para texto grande y para elementos gráficos. */
    public const TEXTO_GRANDE = 3.0;

    /**
     * Cada combinación de texto y fondo que usa el sitio, con el mínimo que le toca.
     *
     * @return list<array{texto: string, fondo: string, minimo: float, donde: string}>
     */
    public static function combinaciones(): array
    {
        return [
            ['texto' => 'naipe', 'fondo' => 'pano', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Texto sobre la mesa'],
            ['texto' => 'naipe', 'fondo' => 'pano-hondo', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Texto del tanteador y las barras'],
            ['texto' => 'oro', 'fondo' => 'pano', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Cantos de envido, solo en texto grande'],
            ['texto' => 'oro', 'fondo' => 'pano-hondo', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Puntos del tanteador'],
            ['texto' => 'fosforo', 'fondo' => 'pano-hondo', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Fósforos del tanteador (gráfico)'],
            ['texto' => 'tinta', 'fondo' => 'naipe', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Texto de las páginas de lectura y de las cartas'],
            ['texto' => 'tinta', 'fondo' => 'oro', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Botón de envido'],
            ['texto' => 'naipe', 'fondo' => 'tinta', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Botón principal sobre naipe y marca de la máquina'],
            ['texto' => 'naipe', 'fondo' => 'copa', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Ficha de truco y de "no quiero"'],
            ['texto' => 'naipe', 'fondo' => 'basto', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Ficha de "quiero"'],
            ['texto' => 'naipe', 'fondo' => 'espada', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Ficha de información'],
            ['texto' => 'espada', 'fondo' => 'naipe', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Links en las páginas de lectura'],
            ['texto' => 'copa', 'fondo' => 'naipe', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Derrotas y alertas en las páginas de lectura'],
            ['texto' => 'basto', 'fondo' => 'naipe', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Victorias en las páginas de lectura'],
        ];
    }

    /**
     * Los tonos que cambian en el modo de noche. No son colores nuevos: la mesa
     * baja un paso y Espada, Basto y Copa se aclaran para leerse sobre Tinta.
     */
    public const DE_NOCHE = [
        'mesa' => ['nombre' => 'Mesa de noche', 'hex' => '#153F32', 'uso' => 'El paño toma el tono del paño hondo.'],
        'barras' => ['nombre' => 'Barras de noche', 'hex' => '#0E2B21', 'uso' => 'Tanteador y barras, un paso más abajo.'],
        'enlace' => ['nombre' => 'Espada clara', 'hex' => '#6FA0E8', 'uso' => 'Links sobre Tinta.'],
        'gana' => ['nombre' => 'Basto claro', 'hex' => '#57B983', 'uso' => 'Victorias sobre Tinta.'],
        'pierde' => ['nombre' => 'Copa clara', 'hex' => '#F0685C', 'uso' => 'Derrotas y alertas sobre Tinta.'],
    ];

    /**
     * Las combinaciones de texto y fondo que solo existen de noche.
     *
     * @return list<array{texto: string, fondo: string, minimo: float, donde: string}>
     */
    public static function combinacionesDeNoche(): array
    {
        return [
            ['texto' => 'naipe', 'fondo' => 'tinta', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Texto de las páginas de lectura'],
            ['texto' => 'naipe', 'fondo' => 'mesa', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Texto sobre la mesa'],
            ['texto' => 'naipe', 'fondo' => 'barras', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Texto del tanteador y las barras'],
            ['texto' => 'oro', 'fondo' => 'mesa', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Cantos de envido'],
            ['texto' => 'oro', 'fondo' => 'barras', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Puntos del tanteador'],
            ['texto' => 'fosforo', 'fondo' => 'barras', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Fósforos del tanteador (gráfico)'],
            ['texto' => 'enlace', 'fondo' => 'tinta', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Links'],
            ['texto' => 'gana', 'fondo' => 'tinta', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Victorias'],
            ['texto' => 'pierde', 'fondo' => 'tinta', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Derrotas y alertas'],
        ];
    }

    /**
     * La mesa contra el Ultra difícil baja un paso sin sumar colores: de día usa los tonos de noche
     * y de noche el paño toma el de las barras. Estas son sus combinaciones, con los tonos de DE_NOCHE.
     *
     * @return list<array{texto: string, fondo: string, minimo: float, donde: string}>
     */
    public static function combinacionesDeLaMesaUltra(): array
    {
        return [
            ['texto' => 'naipe', 'fondo' => 'mesa', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'De día, texto sobre la mesa'],
            ['texto' => 'oro', 'fondo' => 'mesa', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'De día, cantos de envido'],
            ['texto' => 'naipe', 'fondo' => 'barras', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Texto del tanteador y las barras, y de noche también el de la mesa'],
            ['texto' => 'oro', 'fondo' => 'barras', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Puntos del tanteador, y de noche también los cantos de envido'],
            ['texto' => 'fosforo', 'fondo' => 'barras', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Fósforos del tanteador (gráfico)'],
        ];
    }

    /**
     * Las tintas de los dorsos que se ganan en la escalera. No suman colores: son las del mazo.
     * Son dibujo, así que les toca el mínimo de un elemento gráfico.
     *
     * @return list<array{texto: string, fondo: string, minimo: float, donde: string}>
     */
    public static function combinacionesDeLosDorsos(): array
    {
        return [
            ['texto' => 'tinta', 'fondo' => 'naipe', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Trama de los dorsos Fósforos y Ronda'],
            ['texto' => 'copa', 'fondo' => 'naipe', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Cabezas del dorso Fósforos'],
            ['texto' => 'naipe', 'fondo' => 'tinta', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Isotipo del dorso Tinta'],
            ['texto' => 'espada', 'fondo' => 'naipe', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Casillas del dorso Damero'],
            ['texto' => 'copa', 'fondo' => 'naipe', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Puntos del dorso Lunares y raya del Zigzag'],
            ['texto' => 'tinta', 'fondo' => 'naipe', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Renglones del dorso Zigzag'],
        ];
    }

    /**
     * Las tintas de los mazos que se ganan (App\Juego\Mazos y resources/css/app.css, "mazo-tinta-*"). No suman
     * colores: la de noche es la carta en Tinta con los tonos de noche del sitio, y la de una tinta, todo en Tinta
     * sobre Naipe. El número de las esquinas es texto; los palos y las figuras, dibujo.
     *
     * @var array<string, array{naipe: string, tinta: string, palos: array<string, string>}>
     */
    public const MAZOS = [
        'noche' => ['naipe' => '#161a18', 'tinta' => '#fbfaf5', 'palos' => ['oro' => '#f0b429', 'copa' => '#f0685c', 'espada' => '#6fa0e8', 'basto' => '#57b983']],
        'una' => ['naipe' => '#fbfaf5', 'tinta' => '#161a18', 'palos' => ['oro' => '#fbfaf5', 'copa' => '#fbfaf5', 'espada' => '#fbfaf5', 'basto' => '#fbfaf5']],
    ];

    /**
     * Las barajas propias de los mazos de nivel alto (resources/views/components/cartas): cada una con su papel,
     * su número y las tintas de su dibujo. El número es texto; las tintas, dibujo. Los colores nuevos son de la
     * familia (tintas de imprenta, papel, cuero, tierra).
     *
     * @var array<string, list<array{texto: string, fondo: string, minimo: float, donde: string}>>
     */
    public const BARAJAS = [
        'tango' => [
            ['texto' => '#141414', 'fondo' => '#f2e9db', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número sobre el pentagrama y siluetas'],
            ['texto' => '#a37a22', 'fondo' => '#141414', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Detalle del oro sobre la silueta'],
            ['texto' => '#6b1f24', 'fondo' => '#f2e9db', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Marco bordó y rayos del escenario'],
        ],
        'diario' => [
            ['texto' => '#1c1b19', 'fondo' => '#ebe6d8', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Titular, epígrafe y trama'],
        ],
        'caja-de-fosforos' => [
            ['texto' => '#1c1612', 'fondo' => '#f5ecd6', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número en su sello'],
            ['texto' => '#1c1612', 'fondo' => '#f0b429', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Leyenda sobre la etiqueta del oro'],
            ['texto' => '#fbfaf5', 'fondo' => '#c93327', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Leyenda sobre la etiqueta de la copa'],
            ['texto' => '#6b4a2e', 'fondo' => '#d8c3a0', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Raspador sobre el cartón'],
        ],
        'pizarron' => [
            ['texto' => '#ece9df', 'fondo' => '#2b3330', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y trazo de tiza'],
            ['texto' => '#e9cf7a', 'fondo' => '#2b3330', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tiza del oro'],
            ['texto' => '#d8796a', 'fondo' => '#2b3330', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tiza de la copa'],
            ['texto' => '#7d9fc4', 'fondo' => '#2b3330', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tiza de la espada'],
            ['texto' => '#8fbf8a', 'fondo' => '#2b3330', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tiza del basto'],
        ],
        'tipografico' => [
            ['texto' => '#191714', 'fondo' => '#efe6cc', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número de madera y palo entre filetes'],
            ['texto' => '#9a6a12', 'fondo' => '#efe6cc', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Segunda tinta del oro'],
        ],
        'billete' => [
            ['texto' => '#161a18', 'fondo' => '#f3ebd6', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número en su medallón'],
            ['texto' => '#8a5f12', 'fondo' => '#f3ebd6', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tinta de grabado del oro'],
            ['texto' => '#a3271d', 'fondo' => '#f3ebd6', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tinta de grabado de la copa'],
            ['texto' => '#24508f', 'fondo' => '#f3ebd6', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tinta de grabado de la espada'],
            ['texto' => '#22663f', 'fondo' => '#f3ebd6', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Tinta de grabado del basto'],
        ],
        'afiche' => [
            ['texto' => '#161a18', 'fondo' => '#f0b429', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número sobre la franja del oro'],
            ['texto' => '#fbfaf5', 'fondo' => '#c93327', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número sobre la franja de la copa'],
            ['texto' => '#fbfaf5', 'fondo' => '#2a5caa', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número sobre la franja de la espada'],
            ['texto' => '#fbfaf5', 'fondo' => '#2a7d4f', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número sobre la franja del basto'],
        ],
        'catalan' => [
            ['texto' => '#161a18', 'fondo' => '#ece0c0', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número en su recuadro y línea de taco'],
        ],
        'fileteado' => [
            ['texto' => '#f0b429', 'fondo' => '#15191a', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número en oro'],
            ['texto' => '#c8564a', 'fondo' => '#15191a', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Copa de las figuras'],
            ['texto' => '#5d8fd6', 'fondo' => '#15191a', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Espada de los palos y las figuras'],
            ['texto' => '#3f9b67', 'fondo' => '#15191a', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Basto de los palos y las figuras'],
        ],
        'estampilla' => [
            ['texto' => '#f4eedc', 'fondo' => '#955a17', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y franja sobre la tinta del oro'],
            ['texto' => '#f4eedc', 'fondo' => '#b23a2a', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y franja sobre la tinta de la copa'],
            ['texto' => '#f4eedc', 'fondo' => '#2d4f96', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y franja sobre la tinta de la espada'],
            ['texto' => '#f4eedc', 'fondo' => '#2f6e45', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y franja sobre la tinta del basto'],
            ['texto' => '#2b2a27', 'fondo' => '#d9c8a3', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Matasellos sobre el sobre'],
        ],
        'bordado' => [
            ['texto' => '#a5741c', 'fondo' => '#f1ebdd', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Hilo del oro sobre la tela'],
            ['texto' => '#b03a2c', 'fondo' => '#f1ebdd', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Hilo de la copa sobre la tela'],
            ['texto' => '#2d5596', 'fondo' => '#f1ebdd', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Hilo de la espada sobre la tela'],
            ['texto' => '#2f6e45', 'fondo' => '#f1ebdd', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Hilo del basto sobre la tela'],
        ],
        'azulejo' => [
            ['texto' => '#161a18', 'fondo' => '#f6f4ee', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número del cartel'],
            ['texto' => '#1f2d4f', 'fondo' => '#f6f4ee', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Nombre del palo en el cartel'],
            ['texto' => '#a87a24', 'fondo' => '#f6f4ee', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Franja y esmalte del oro'],
            ['texto' => '#b8432f', 'fondo' => '#f6f4ee', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Franja y esmalte de la copa'],
            ['texto' => '#2f56a0', 'fondo' => '#f6f4ee', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Franja y esmalte de la espada'],
            ['texto' => '#2f7a4c', 'fondo' => '#f6f4ee', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Franja y esmalte del basto'],
        ],
        'murga' => [
            ['texto' => '#f3e7cf', 'fondo' => '#4f3a14', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y nombre sobre el raso del oro'],
            ['texto' => '#d4aa50', 'fondo' => '#4f3a14', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Lentejuelas del oro'],
            ['texto' => '#f3e7cf', 'fondo' => '#5b1e22', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y nombre sobre el raso de la copa'],
            ['texto' => '#d4594c', 'fondo' => '#5b1e22', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Lentejuelas de la copa'],
            ['texto' => '#f3e7cf', 'fondo' => '#1e3157', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y nombre sobre el raso de la espada'],
            ['texto' => '#5d8fd6', 'fondo' => '#1e3157', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Lentejuelas de la espada'],
            ['texto' => '#f3e7cf', 'fondo' => '#1d4730', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y nombre sobre el raso del basto'],
            ['texto' => '#55a86f', 'fondo' => '#1d4730', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Lentejuelas del basto'],
        ],
        'clasico' => [
            ['texto' => '#161a18', 'fondo' => '#fbfaf5', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número dentro del marco de la pinta'],
            ['texto' => '#5f8fcf', 'fondo' => '#fbfaf5', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Hoja de la espada'],
            ['texto' => '#161a18', 'fondo' => '#c59a55', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Contorno del basto sobre la madera'],
        ],
        'tarot' => [
            ['texto' => '#161a18', 'fondo' => '#efe2c4', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número romano y nombre en las cintas'],
            ['texto' => '#b23a2e', 'fondo' => '#efe2c4', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Rojo de grabado'],
            ['texto' => '#4f7db5', 'fondo' => '#efe2c4', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Azul de grabado'],
        ],
        'gatos' => [
            ['texto' => '#f7f0e3', 'fondo' => '#2a2622', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número en la cabeza de gato'],
            ['texto' => '#2a2622', 'fondo' => '#f7f0e3', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Contornos sobre el papel'],
            ['texto' => '#c27a3a', 'fondo' => '#f7f0e3', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Gato atigrado'],
        ],
        'retratos' => [
            ['texto' => '#f3e6c8', 'fondo' => '#7a5a22', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y pie sobre el cartón del oro'],
            ['texto' => '#f3e6c8', 'fondo' => '#5c2626', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y pie sobre el cartón del copa'],
            ['texto' => '#f3e6c8', 'fondo' => '#2c3c58', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y pie sobre el cartón del espada'],
            ['texto' => '#f3e6c8', 'fondo' => '#2d4a34', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y pie sobre el cartón del basto'],
            ['texto' => '#4d3423', 'fondo' => '#e3cfa6', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Sepia oscuro sobre el óvalo claro de la foto'],
        ],
        'criollo' => [
            ['texto' => '#2a1f17', 'fondo' => '#f0e6d0', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Número y línea de campo'],
            ['texto' => '#b8483a', 'fondo' => '#2a1f17', 'minimo' => self::TEXTO_GRANDE, 'donde' => 'Guarda pampa'],
        ],
    ];

    /**
     * Las mesas que se ganan (resources/css/app.css, "Las mesas que se ganan subiendo de nivel"). Los colores
     * de premio pueden ser nuevos si son de la familia (esDeLaFamilia): tintas de imprenta y materiales de
     * club. "mesa" es el campo, "barras" el tanteador y la barra de cantos, "texto" lo que se escribe sobre
     * la mesa y "canto" el envido; "adornos", lo que se dibuja en el borde.
     *
     * Cada una tiene su "noche": la misma mesa con la luz baja (sus colores por 0,7; el mármol, por 0,78).
     *
     * @var array<string, array{mesa: string, barras: string, texto: string, canto: string, fosforo: string, adornos: list<string>, noche: array{mesa: string, barras: string, canto: string, fosforo: string}}>
     */
    public const MESAS = [
        'pano-petroleo' => ['mesa' => '#1c4652', 'barras' => '#12313a', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => [],
            'noche' => ['mesa' => '#143139', 'barras' => '#0d2229', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'pizarron' => ['mesa' => '#28302d', 'barras' => '#1b201e', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#7a5a3c', '#ecebe4', '#4d3826', '#8b6a48'],
            'noche' => ['mesa' => '#1c221f', 'barras' => '#131615', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'madera' => ['mesa' => '#4a3022', 'barras' => '#2e1e15', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#a57c48', '#e6d7b5', '#1b120c', '#5b3d2b', '#3c2619'],
            'noche' => ['mesa' => '#342218', 'barras' => '#20150f', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        // La única clara: el texto pasa a tinta, el envido a un ocre del oro y el palito de los fósforos se oscurece.
        'marmol' => ['mesa' => '#e9e6df', 'barras' => '#3a3f3d', 'texto' => '#161a18', 'canto' => '#8a5a0c', 'fosforo' => '#9a7838', 'adornos' => ['#9c8754', '#b4aea2', '#c4bfb4', '#dad6cd'],
            'noche' => ['mesa' => '#b6b3ae', 'barras' => '#2d3130', 'canto' => '#5e3b07', 'fosforo' => '#74592b']],
        'baldosa' => ['mesa' => '#e4dac8', 'barras' => '#3d3a33', 'texto' => '#161a18', 'canto' => '#7d5209', 'fosforo' => '#8f7034', 'adornos' => ['#7a2e2a', '#3f5b4a', '#a8834a', '#d3c8b3'],
            'noche' => ['mesa' => '#b2aa9c', 'barras' => '#302d28', 'canto' => '#553506', 'fosforo' => '#6b5228']],
        'mantel' => ['mesa' => '#ece2d2', 'barras' => '#4a2b29', 'texto' => '#161a18', 'canto' => '#7d5209', 'fosforo' => '#8f7034', 'adornos' => ['#8c3a35'],
            'noche' => ['mesa' => '#b8b0a4', 'barras' => '#3a2220', 'canto' => '#553506', 'fosforo' => '#6b5228']],
        'diario' => ['mesa' => '#e7e3d6', 'barras' => '#2f302c', 'texto' => '#161a18', 'canto' => '#7d5209', 'fosforo' => '#8f7034', 'adornos' => ['#2b2d29'],
            'noche' => ['mesa' => '#b4b1a7', 'barras' => '#252522', 'canto' => '#553506', 'fosforo' => '#6b5228']],
        // Las oscuras también bajan de noche: la mesa un paso y lo claro más, para que la noche se note (Román, 15 de octubre de 2026).
        'milonga' => ['mesa' => '#4a1f25', 'barras' => '#2e1317', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#e6d7b5'],
            'noche' => ['mesa' => '#34161a', 'barras' => '#1e0b0d', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'pana' => ['mesa' => '#203a2c', 'barras' => '#14261c', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#9c8754', '#2b4a3a', '#15281e'],
            'noche' => ['mesa' => '#162920', 'barras' => '#0b160f', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'marmol-negro' => ['mesa' => '#161d1c', 'barras' => '#0c1110', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#9c8754', '#b49a5c', '#8f7a4c', '#d8ccae', '#24302d'],
            'noche' => ['mesa' => '#0f1414', 'barras' => '#070a0a', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        // La tanda 6: cada una con su elemento propio. El fileteado se pinta con las tintas del mazo (que no suman
        // colores) y sus sombras; el cobre lleva el verde del cardenillo para no parecer dorado de casino.
        'cuero' => ['mesa' => '#5c3a22', 'barras' => '#382314', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#2e1c10', '#8f6340', '#6d4529', '#d8c29a', '#24140a', '#40271a'],
            'noche' => ['mesa' => '#402918', 'barras' => '#26180e', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'pulperia' => ['mesa' => '#4a3828', 'barras' => '#2b2018', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#271c13', '#5c4633', '#1b140e', '#1c1a18', '#4f4943', '#634b38'],
            'noche' => ['mesa' => '#34271c', 'barras' => '#1e1611', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'cobre' => ['mesa' => '#743c24', 'barras' => '#4f2a18', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#b06a43', '#5a2e1b', '#5e8a78', '#a86c45', '#4f2a19', '#874c2f'],
            'noche' => ['mesa' => '#673921', 'barras' => '#371d11', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'art-deco' => ['mesa' => '#3b2418', 'barras' => '#24160e', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#4b2e1d', '#5a3824', '#a8905a', '#1a120c', '#c9a97a'],
            'noche' => ['mesa' => '#291911', 'barras' => '#190f0a', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'grafito' => ['mesa' => '#2c2f31', 'barras' => '#1c1e20', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#a8905a', '#6f5d36', '#4a3d22', '#8f7a4b', '#2e2615'],
            'noche' => ['mesa' => '#1f2122', 'barras' => '#131516', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'fileteado' => ['mesa' => '#15191a', 'barras' => '#0b0d0e', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#8a1f17', '#9a6c1a', '#1a5434', '#1b3d73'],
            'noche' => ['mesa' => '#0f1212', 'barras' => '#070909', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        // Las mesas de la gira (16 de octubre de 2026): tres oscuras y tres claras.
        'chapa' => ['mesa' => '#2f5244', 'barras' => '#1f382e', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#8a3d2e', '#a8823c', '#3c5d7a', '#4e7a55', '#7a5a3a', '#1b1a17', '#e6d7b5', '#26322d'],
            'noche' => ['mesa' => '#213a30', 'barras' => '#15271f', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'adoquines' => ['mesa' => '#4b4943', 'barras' => '#312f2b', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#6b6860', '#77736a', '#5e5b54', '#827d72', '#706b62', '#65615a', '#2c2a26', '#8f8a7e'],
            'noche' => ['mesa' => '#353330', 'barras' => '#22211e', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'tarot' => ['mesa' => '#1e2b40', 'barras' => '#131c2b', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#9c8754'],
            'noche' => ['mesa' => '#151e2d', 'barras' => '#0d131e', 'canto' => '#f0b429', 'fosforo' => '#e8c98a']],
        'cajon' => ['mesa' => '#e6d0a8', 'barras' => '#4a3a28', 'texto' => '#161a18', 'canto' => '#7d5209', 'fosforo' => '#8f7034', 'adornos' => ['#c9ae80', '#a6875a', '#4a3a28', '#f1e4c3', '#9a3a2c', '#b8903f', '#2f4f6e'],
            'noche' => ['mesa' => '#bba988', 'barras' => '#3a2d1f', 'canto' => '#553506', 'fosforo' => '#6b5228']],
        'bordado' => ['mesa' => '#ece3cf', 'barras' => '#2f3a4a', 'texto' => '#161a18', 'canto' => '#7d5209', 'fosforo' => '#8f7034', 'adornos' => ['#9a3a2c', '#34507a', '#4e7a55'],
            'noche' => ['mesa' => '#b8b1a1', 'barras' => '#252e3a', 'canto' => '#553506', 'fosforo' => '#6b5228']],
        'guia' => ['mesa' => '#f2e8cf', 'barras' => '#3d3426', 'texto' => '#161a18', 'canto' => '#7d5209', 'fosforo' => '#8f7034', 'adornos' => ['#e7d3a1', '#c4a96f', '#7a2e26', '#3f6b47', '#34507a', '#e8c98a', '#161a18'],
            'noche' => ['mesa' => '#bdb5a1', 'barras' => '#2f281d', 'canto' => '#553506', 'fosforo' => '#6b5228']],
    ];

    /**
     * Las formas de anotar que traen su superficie: el trazo sobre el fondo, y el tanteo en Oro (o en tinta, sobre papel).
     *
     * @var array<string, array{trazo: string, fondo: string, tanteo: string}>
     */
    public const ANOTADORES = [
        'palotes' => ['trazo' => '#3f4447', 'fondo' => '#fbfaf5', 'tanteo' => '#161a18'],
        'tiza' => ['trazo' => '#ecebe4', 'fondo' => '#28302d', 'tanteo' => '#f0b429'],
        'porotos' => ['trazo' => '#6b2c22', 'fondo' => '#efe6d2', 'tanteo' => '#161a18'],
        'punto-cruz' => ['trazo' => '#9a3a2c', 'fondo' => '#ece3cf', 'tanteo' => '#161a18'],
        'constelacion' => ['trazo' => '#e8c98a', 'fondo' => '#1e2b40', 'tanteo' => '#f0b429'],
    ];

    /**
     * Las combinaciones de cada mesa y cada anotador, con colores escritos (no nombres de la paleta).
     *
     * @return list<array{texto: string, fondo: string, minimo: float, donde: string}>
     */
    public static function combinacionesDeLosPremios(): array
    {
        $combinaciones = [];

        foreach (self::MESAS as $nombre => $mesa) {
            foreach (['de día' => $mesa, 'de noche' => ['texto' => $mesa['texto'], ...$mesa['noche']]] as $cuando => $c) {
                $mesa = "{$nombre} {$cuando}";
                $combinaciones[] = ['texto' => $c['texto'], 'fondo' => $c['mesa'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: texto sobre la mesa"];
                $combinaciones[] = ['texto' => $c['canto'], 'fondo' => $c['mesa'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: cantos de envido"];
                $combinaciones[] = ['texto' => '#fbfaf5', 'fondo' => $c['barras'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: texto del tanteador y las barras"];
                $combinaciones[] = ['texto' => '#f0b429', 'fondo' => $c['barras'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: puntos del tanteador"];
                $combinaciones[] = ['texto' => '#e8c98a', 'fondo' => $c['barras'], 'minimo' => self::TEXTO_GRANDE, 'donde' => "Mesa {$mesa}: fósforos del tanteador (gráfico)"];
                $combinaciones[] = ['texto' => $c['fosforo'], 'fondo' => $c['mesa'], 'minimo' => self::TEXTO_GRANDE, 'donde' => "Mesa {$mesa}: fósforos del nivel junto al rival (gráfico)"];
            }
        }

        foreach (self::ANOTADORES as $forma => $c) {
            $combinaciones[] = ['texto' => $c['trazo'], 'fondo' => $c['fondo'], 'minimo' => self::TEXTO_GRANDE, 'donde' => "Anotar con {$forma}: el trazo (gráfico)"];
            $combinaciones[] = ['texto' => $c['tanteo'], 'fondo' => $c['fondo'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Anotar con {$forma}: el tanteo"];
        }

        return $combinaciones;
    }

    /**
     * Los colores nuevos de los premios: los que tienen que ser de la familia.
     *
     * @return list<string>
     */
    public static function coloresDeLosPremios(): array
    {
        $colores = [];

        foreach (self::MESAS as $c) {
            array_push($colores, $c['mesa'], $c['barras'], $c['canto'], $c['fosforo'], ...$c['adornos'], ...array_values($c['noche']));
        }

        foreach (self::ANOTADORES as $c) {
            array_push($colores, $c['trazo'], $c['fondo']);
        }

        // Los de la paleta fija (el oro, el fósforo, el naipe) no son nuevos: no pasan por la regla de la familia.
        $deLaPaleta = array_map(fn (array $color) => strtolower($color['hex']), self::COLORES);

        return array_values(array_diff(array_unique(array_map('strtolower', $colores)), $deLaPaleta));
    }

    /**
     * Contraste WCAG 2 entre dos colores escritos (#rrggbb), de 1 a 21.
     */
    public static function contrasteEntre(string $hexA, string $hexB): float
    {
        $a = self::luminancia($hexA);
        $b = self::luminancia($hexB);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Si un color de premio es de la familia del sitio: tintas de imprenta y materiales de club. No pasa de un
     * tope de intensidad (nada fluorescente), no cae en la franja de rosas y violetas, y no es un celeste
     * claro de bebé. Lo decidió Román el 14 de octubre de 2026.
     */
    public static function esDeLaFamilia(string $hex): bool
    {
        [$r, $g, $b] = array_map(fn (string $par) => hexdec($par) / 255, str_split(ltrim($hex, '#'), 2));
        $mayor = max($r, $g, $b);
        $menor = min($r, $g, $b);
        $intensidad = $mayor - $menor;
        $luz = ($mayor + $menor) / 2;

        if ($intensidad > self::INTENSIDAD_MAXIMA) {
            return false;
        }

        // Casi gris: no tiene tono que mirar.
        if ($intensidad < 0.08) {
            return true;
        }

        $tono = match ($mayor) {
            $r => 60 * fmod(($g - $b) / $intensidad, 6),
            $g => 60 * (($b - $r) / $intensidad + 2),
            default => 60 * (($r - $g) / $intensidad + 4),
        };
        $tono = $tono < 0 ? $tono + 360 : $tono;

        $rosaOVioleta = $tono >= 260 && $tono <= 345;
        $celesteDeBebe = $tono >= 180 && $tono <= 230 && $luz > 0.75;

        return ! $rosaOVioleta && ! $celesteDeBebe;
    }

    /**
     * El tope de intensidad de un color de premio (la diferencia entre su canal más alto y el más bajo).
     * El oro de la paleta, que no es de premio, pasa de largo (0,78); un color fluorescente anda cerca de 1.
     */
    public const INTENSIDAD_MAXIMA = 0.55;

    public static function nombre(string $color): string
    {
        return (self::COLORES[$color] ?? self::DE_NOCHE[$color])['nombre'];
    }

    public static function hex(string $color): string
    {
        return (self::COLORES[$color] ?? self::DE_NOCHE[$color])['hex'];
    }

    /**
     * Contraste WCAG 2 entre dos colores de la paleta, de 1 a 21.
     */
    public static function contraste(string $colorA, string $colorB): float
    {
        $a = self::luminancia(self::hex($colorA));
        $b = self::luminancia(self::hex($colorB));

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * Luminancia relativa de un color hexadecimal, según la fórmula de WCAG.
     */
    public static function luminancia(string $hex): float
    {
        $canales = array_map(
            function (string $par): float {
                $valor = hexdec($par) / 255;

                return $valor <= 0.03928 ? $valor / 12.92 : (($valor + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $canales[0] + 0.7152 * $canales[1] + 0.0722 * $canales[2];
    }
}
