<?php

namespace Tests\Unit;

use App\Identidad\Paleta;
use App\Juego\Logros;
use App\Juego\Premios;
use PHPUnit\Framework\TestCase;

class PaletaTest extends TestCase
{
    public function test_cada_combinacion_en_uso_alcanza_su_minimo_de_contraste(): void
    {
        foreach (Paleta::combinaciones() as $combinacion) {
            $contraste = Paleta::contraste($combinacion['texto'], $combinacion['fondo']);

            $this->assertGreaterThanOrEqual(
                $combinacion['minimo'],
                $contraste,
                sprintf('%s sobre %s da %.2f y necesita %.1f.', $combinacion['texto'], $combinacion['fondo'], $contraste, $combinacion['minimo']),
            );
        }
    }

    public function test_las_mesas_y_las_formas_de_anotar_alcanzan_su_minimo_de_contraste(): void
    {
        foreach (Paleta::combinacionesDeLosPremios() as $combinacion) {
            $contraste = Paleta::contrasteEntre($combinacion['texto'], $combinacion['fondo']);

            $this->assertGreaterThanOrEqual(
                $combinacion['minimo'],
                $contraste,
                sprintf('%s: %s sobre %s da %.2f y necesita %.1f.', $combinacion['donde'], $combinacion['texto'], $combinacion['fondo'], $contraste, $combinacion['minimo']),
            );
        }
    }

    public function test_cada_mesa_que_se_gana_tiene_su_version_de_noche(): void
    {
        $estilos = file_get_contents(__DIR__.'/../../resources/css/app.css');

        foreach (array_keys(Premios::POR_NIVEL['mesa']) as $mesa) {
            if ($mesa === 'fabrica') {
                continue;
            }

            $this->assertArrayHasKey($mesa, Paleta::MESAS, "La mesa {$mesa} no tiene sus colores en la paleta.");
            $this->assertArrayHasKey('noche', Paleta::MESAS[$mesa], "La mesa {$mesa} no tiene su versión de noche.");
            $this->assertStringContainsString(":root[data-modo='noche'] body:has(.mesa-de-{$mesa})", $estilos, "La mesa {$mesa} no baja la luz de noche en los estilos.");
            $this->assertStringContainsString(Paleta::MESAS[$mesa]['noche']['mesa'], $estilos, "El color de noche de la mesa {$mesa} no está en los estilos.");
        }
    }

    public function test_los_colores_de_los_premios_son_de_la_familia_del_sitio(): void
    {
        foreach (Paleta::coloresDeLosPremios() as $color) {
            $this->assertTrue(Paleta::esDeLaFamilia($color), "{$color} no es de la familia del sitio.");
        }

        // Y la regla deja afuera lo que tiene que dejar afuera.
        $this->assertFalse(Paleta::esDeLaFamilia('#ff2bd6'), 'Un rosa fluorescente');
        $this->assertFalse(Paleta::esDeLaFamilia('#8a4fc9'), 'Un violeta');
        $this->assertFalse(Paleta::esDeLaFamilia('#bfe3f5'), 'Un celeste de bebé');
        $this->assertFalse(Paleta::esDeLaFamilia('#39ff14'), 'Un verde fluorescente');
    }

    public function test_cada_combinacion_del_modo_de_noche_alcanza_su_minimo_de_contraste(): void
    {
        foreach (Paleta::combinacionesDeNoche() as $combinacion) {
            $contraste = Paleta::contraste($combinacion['texto'], $combinacion['fondo']);

            $this->assertGreaterThanOrEqual(
                $combinacion['minimo'],
                $contraste,
                sprintf('De noche, %s sobre %s da %.2f y necesita %.1f.', $combinacion['texto'], $combinacion['fondo'], $contraste, $combinacion['minimo']),
            );
        }
    }

    public function test_cada_combinacion_de_la_mesa_del_ultra_dificil_alcanza_su_minimo_de_contraste(): void
    {
        foreach (Paleta::combinacionesDeLaMesaUltra() as $combinacion) {
            $contraste = Paleta::contraste($combinacion['texto'], $combinacion['fondo']);

            $this->assertGreaterThanOrEqual(
                $combinacion['minimo'],
                $contraste,
                sprintf('En la mesa del Ultra difícil, %s sobre %s da %.2f y necesita %.1f.', $combinacion['texto'], $combinacion['fondo'], $contraste, $combinacion['minimo']),
            );

            // No suma colores: todos sus fondos son tonos que el modo de noche ya tenía.
            $this->assertArrayHasKey($combinacion['fondo'], Paleta::DE_NOCHE);
        }
    }

    public function test_las_tintas_de_los_dorsos_que_se_ganan_alcanzan_su_minimo_de_contraste(): void
    {
        foreach (Paleta::combinacionesDeLosDorsos() as $combinacion) {
            $contraste = Paleta::contraste($combinacion['texto'], $combinacion['fondo']);

            $this->assertGreaterThanOrEqual(
                $combinacion['minimo'],
                $contraste,
                sprintf('En los dorsos, %s sobre %s da %.2f y necesita %.1f.', $combinacion['texto'], $combinacion['fondo'], $contraste, $combinacion['minimo']),
            );

            // No suman colores: todas sus tintas son de la paleta del mazo.
            $this->assertArrayHasKey($combinacion['texto'], Paleta::COLORES);
            $this->assertArrayHasKey($combinacion['fondo'], Paleta::COLORES);
        }
    }

    public function test_los_colores_de_palo_sin_aclarar_no_alcanzan_como_texto_sobre_tinta(): void
    {
        // Por esto existen los tonos de noche: sin aclararlos, no se leen.
        foreach (['copa', 'espada', 'basto'] as $palo) {
            $this->assertLessThan(Paleta::TEXTO_NORMAL, Paleta::contraste($palo, 'tinta'));
        }
    }

    public function test_los_contrastes_documentados_en_la_direccion_visual_son_ciertos(): void
    {
        $this->assertEqualsWithDelta(1.5, Paleta::contraste('copa', 'pano'), 0.1);
        $this->assertEqualsWithDelta(4.3, Paleta::contraste('oro', 'pano'), 0.1);
        $this->assertEqualsWithDelta(6.3, Paleta::contraste('oro', 'pano-hondo'), 0.1);
        $this->assertEqualsWithDelta(7.7, Paleta::contraste('naipe', 'pano'), 0.1);
    }

    public function test_los_colores_de_palo_no_sirven_como_texto_sobre_el_pano(): void
    {
        foreach (['copa', 'espada', 'basto'] as $palo) {
            $this->assertLessThan(Paleta::TEXTO_GRANDE, Paleta::contraste($palo, 'pano'));
        }
    }

    public function test_blanco_sobre_negro_es_el_contraste_maximo(): void
    {
        $this->assertEqualsWithDelta(1.0, Paleta::luminancia('#FFFFFF'), 0.0001);
        $this->assertEqualsWithDelta(0.0, Paleta::luminancia('#000000'), 0.0001);
    }

    public function test_en_las_mesas_claras_los_cantos_en_ficha_vuelven_a_su_texto_claro(): void
    {
        // En una mesa clara el texto pasa a tinta; las fichas de los cantos (Truco en Copa, Quiero en Basto)
        // tienen que volver a su texto claro, como las cartas y los botones. Pasó una vez: Truco salía en tinta.
        $estilos = file_get_contents(__DIR__.'/../../resources/css/app.css');

        $this->assertSame(2, substr_count($estilos, '.boton, .canto-copa, .canto-basto, .canto-naipe) {'));
    }

    public function test_las_tintas_de_los_sellos_se_leen_de_dia_y_de_noche(): void
    {
        // La letra del sello es chica: pide el contraste de texto normal sobre el papel de día y sobre Tinta de noche.
        foreach (Logros::TEMAS as $tema => $datos) {
            $this->assertGreaterThanOrEqual(4.5, Paleta::contrasteEntre($datos['tinta'], '#fbfaf5'), "La tinta de {$tema} no se lee de día.");
            $this->assertGreaterThanOrEqual(4.5, Paleta::contrasteEntre($datos['noche'], '#161a18'), "La tinta de {$tema} no se lee de noche.");
            // Las tintas de noche son las de la paleta del sitio; la que no lo es tiene que ser de la familia.
            $dePaleta = in_array(strtolower($datos['noche']), ['#f0b429', '#fbfaf5', ...array_map(fn (array $tono) => strtolower($tono['hex']), Paleta::DE_NOCHE)], true);
            $this->assertTrue($dePaleta || Paleta::esDeLaFamilia($datos['noche']), "La tinta de noche de {$tema} no es de la familia de colores.");
        }
    }
}
