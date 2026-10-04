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
            ['texto' => 'naipe', 'fondo' => 'tinta', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Botón principal sobre naipe y marca de bot'],
            ['texto' => 'naipe', 'fondo' => 'copa', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Ficha de truco y de "no quiero"'],
            ['texto' => 'naipe', 'fondo' => 'basto', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Ficha de "quiero"'],
            ['texto' => 'naipe', 'fondo' => 'espada', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Ficha de información'],
            ['texto' => 'espada', 'fondo' => 'naipe', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Links en las páginas de lectura'],
            ['texto' => 'copa', 'fondo' => 'naipe', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Derrotas y alertas en las páginas de lectura'],
            ['texto' => 'basto', 'fondo' => 'naipe', 'minimo' => self::TEXTO_NORMAL, 'donde' => 'Victorias en las páginas de lectura'],
        ];
    }

    public static function hex(string $color): string
    {
        return self::COLORES[$color]['hex'];
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
