<?php

namespace Tests\Unit;

use App\Identidad\Paleta;
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
}
