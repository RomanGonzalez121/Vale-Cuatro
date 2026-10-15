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
     * Las mesas que se ganan (resources/css/app.css, "Las mesas que se ganan subiendo de nivel"). Los colores
     * de premio pueden ser nuevos si son de la familia (esDeLaFamilia): tintas de imprenta y materiales de
     * club. "mesa" es el campo, "barras" el tanteador y la barra de cantos, "texto" lo que se escribe sobre
     * la mesa y "canto" el envido; "adornos", lo que se dibuja en el borde.
     *
     * @var array<string, array{mesa: string, barras: string, texto: string, canto: string, fosforo: string, adornos: list<string>}>
     */
    public const MESAS = [
        'pano-petroleo' => ['mesa' => '#1c4652', 'barras' => '#12313a', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => []],
        'pizarron' => ['mesa' => '#28302d', 'barras' => '#1b201e', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#7a5a3c', '#ecebe4', '#4d3826', '#8b6a48']],
        'madera' => ['mesa' => '#4a3022', 'barras' => '#2e1e15', 'texto' => '#fbfaf5', 'canto' => '#f0b429', 'fosforo' => '#e8c98a', 'adornos' => ['#a57c48', '#e6d7b5', '#1b120c', '#5b3d2b', '#3c2619']],
        // La única clara: el texto pasa a tinta, el envido a un ocre del oro y el palito de los fósforos se oscurece.
        'marmol' => ['mesa' => '#e9e6df', 'barras' => '#3a3f3d', 'texto' => '#161a18', 'canto' => '#8a5a0c', 'fosforo' => '#9a7838', 'adornos' => ['#9c8754', '#b4aea2', '#c4bfb4', '#dad6cd']],
    ];

    /**
     * Las formas de anotar que traen su superficie: el trazo sobre el fondo, y el tanteo en Oro (o en tinta, sobre papel).
     *
     * @var array<string, array{trazo: string, fondo: string, tanteo: string}>
     */
    public const ANOTADORES = [
        'palotes' => ['trazo' => '#3f4447', 'fondo' => '#fbfaf5', 'tanteo' => '#161a18'],
        'tiza' => ['trazo' => '#ecebe4', 'fondo' => '#28302d', 'tanteo' => '#f0b429'],
    ];

    /**
     * Las combinaciones de cada mesa y cada anotador, con colores escritos (no nombres de la paleta).
     *
     * @return list<array{texto: string, fondo: string, minimo: float, donde: string}>
     */
    public static function combinacionesDeLosPremios(): array
    {
        $combinaciones = [];

        foreach (self::MESAS as $mesa => $c) {
            $combinaciones[] = ['texto' => $c['texto'], 'fondo' => $c['mesa'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: texto sobre la mesa"];
            $combinaciones[] = ['texto' => $c['canto'], 'fondo' => $c['mesa'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: cantos de envido"];
            $combinaciones[] = ['texto' => '#fbfaf5', 'fondo' => $c['barras'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: texto del tanteador y las barras"];
            $combinaciones[] = ['texto' => '#f0b429', 'fondo' => $c['barras'], 'minimo' => self::TEXTO_NORMAL, 'donde' => "Mesa {$mesa}: puntos del tanteador"];
            $combinaciones[] = ['texto' => '#e8c98a', 'fondo' => $c['barras'], 'minimo' => self::TEXTO_GRANDE, 'donde' => "Mesa {$mesa}: fósforos del tanteador (gráfico)"];
            $combinaciones[] = ['texto' => $c['fosforo'], 'fondo' => $c['mesa'], 'minimo' => self::TEXTO_GRANDE, 'donde' => "Mesa {$mesa}: fósforos del nivel junto al rival (gráfico)"];
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
            array_push($colores, $c['mesa'], $c['barras'], $c['canto'], $c['fosforo'], ...$c['adornos']);
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
