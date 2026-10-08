<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El tanteador de fósforos: los puntos se anotan como en el club, en grupos de cinco, con las malas
 * (los primeros quince) separadas de las buenas. Se dibuja bien cualquier tanteo de la partida, de 0 a 30.
 */
class TanteadorTest extends TestCase
{
    use InteractsWithViews;

    /** La raya que separa las malas de las buenas. */
    private const string SEPARADOR = 'w-px';

    #[DataProvider('tanteos')]
    public function test_dibuja_un_fosforo_por_punto_con_las_malas_separadas_de_las_buenas(int $puntos): void
    {
        $html = (string) $this->blade('<x-tanteador nombre="Vos" :puntos="$puntos" />', ['puntos' => $puntos]);

        [$malas, $buenas] = $this->mitades($html);

        // Hay lugar para los treinta: quince de cada lado, en tres grupos de cinco.
        $this->assertSame(3, substr_count($malas, '<svg'));
        $this->assertSame(3, substr_count($buenas, '<svg'));
        $this->assertSame(15, $this->fosforos($malas));
        $this->assertSame(15, $this->fosforos($buenas));

        // Los puntos llenan primero las malas y recién después las buenas.
        $this->assertSame(min($puntos, 15), $this->puestos($malas), "Malas con {$puntos} puntos");
        $this->assertSame(max($puntos - 15, 0), $this->puestos($buenas), "Buenas con {$puntos} puntos");

        // El número va escrito, para quien no cuenta fósforos y para el lector de pantalla.
        $this->assertMatchesRegularExpression('/>\s*'.$puntos.'\s*<\/span>\s*<span class="sr-only">puntos<\/span>/', $html);
    }

    #[DataProvider('tanteos')]
    public function test_los_fosforos_se_ponen_en_orden_sin_saltear_lugares(int $puntos): void
    {
        $html = (string) $this->blade('<x-tanteador nombre="Vos" :puntos="$puntos" />', ['puntos' => $puntos]);

        // Uno por uno, en el orden en que están dibujados: primero todos los puestos, después todos los vacíos.
        preg_match_all('/<g class="fosforo( puesto)?"/', $html, $encontrados);
        $estados = array_map(fn (string $puesto) => $puesto !== '', $encontrados[1]);

        $this->assertCount(30, $estados);
        $this->assertSame(array_merge(array_fill(0, $puntos, true), array_fill(0, 30 - $puntos, false)), $estados);
    }

    public function test_con_un_modelo_cada_fosforo_sabe_desde_que_punto_se_pone(): void
    {
        $html = (string) $this->blade('<x-tanteador nombre="Vos" :puntos="7" modelo="puntos.vos" />');

        // En la mesa los puntos cambian sin recargar: cada fósforo trae su número, del 1 al 30, y cae cuando el tanteo lo alcanza.
        preg_match_all('/:class="\{ puesto: puntos\.vos >= (\d+) \}"/', $html, $encontrados);

        $this->assertSame(range(1, 30), array_map(intval(...), $encontrados[1]));
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function tanteos(): iterable
    {
        foreach (range(0, 30) as $puntos) {
            yield "{$puntos} puntos" => [$puntos];
        }
    }

    /**
     * Lo dibujado antes y después de la raya que separa las malas de las buenas.
     *
     * @return array{0: string, 1: string}
     */
    private function mitades(string $html): array
    {
        $mitades = explode(self::SEPARADOR, $html);

        $this->assertCount(2, $mitades, 'El tanteador tiene una sola raya entre malas y buenas.');

        return [$mitades[0], $mitades[1]];
    }

    private function fosforos(string $html): int
    {
        return preg_match_all('/<g class="fosforo( puesto)?"/', $html);
    }

    private function puestos(string $html): int
    {
        return substr_count($html, '<g class="fosforo puesto"');
    }
}
