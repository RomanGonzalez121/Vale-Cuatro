<?php

namespace Tests\Motor;

use App\Motor\Envido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CadenasDeEnvidoTest extends TestCase
{
    private const E = Envido::ENVIDO;

    private const R = Envido::REAL;

    private const F = Envido::FALTA;

    /**
     * La tabla de envido del reglamento, fila por fila. Con el tanteo 12 a 20,
     * al puntero le faltan 10: eso vale la falta.
     *
     * @param  list<string>  $cadena
     */
    #[DataProvider('tablaDeEnvido')]
    public function test_tabla_de_envido(array $cadena, int $querido, int $noQuerido): void
    {
        $this->assertSame($querido, Envido::querido($cadena, [12, 20], 30));
        $this->assertSame($noQuerido, Envido::noQuerido($cadena));
    }

    public static function tablaDeEnvido(): array
    {
        $falta = 10;

        return [
            'envido' => [[self::E], 2, 1],
            'real envido' => [[self::R], 3, 1],
            'falta envido' => [[self::F], $falta, 1],
            'envido, envido' => [[self::E, self::E], 4, 2],
            'envido, real envido' => [[self::E, self::R], 5, 2],
            'envido, falta envido' => [[self::E, self::F], $falta, 2],
            'real envido, falta envido' => [[self::R, self::F], $falta, 3],
            'envido, envido, real envido' => [[self::E, self::E, self::R], 7, 4],
            'envido, envido, falta envido' => [[self::E, self::E, self::F], $falta, 4],
            'envido, real envido, falta envido' => [[self::E, self::R, self::F], $falta, 5],
            'envido, envido, real envido, falta envido' => [[self::E, self::E, self::R, self::F], $falta, 7],
        ];
    }

    public function test_la_falta_es_lo_que_le_falta_al_puntero_sin_distinguir_malas_y_buenas(): void
    {
        $this->assertSame(30, Envido::falta([0, 0], 30));
        $this->assertSame(23, Envido::falta([7, 3], 30));
        $this->assertSame(23, Envido::falta([3, 7], 30));
        $this->assertSame(2, Envido::falta([14, 28], 30));
        $this->assertSame(1, Envido::falta([29, 29], 30));
    }

    public function test_la_falta_depende_de_a_cuantos_puntos_va_la_partida(): void
    {
        $this->assertSame(5, Envido::falta([10, 4], 15));
    }

    /**
     * @param  list<string>  $cadena
     */
    #[DataProvider('subidasValidas')]
    public function test_siempre_se_puede_subir_y_saltear_pasos(array $cadena, string $canto): void
    {
        $this->assertTrue(Envido::puedeSeguir($cadena, $canto));
    }

    public static function subidasValidas(): array
    {
        return [
            'abrir con envido' => [[], self::E],
            'abrir con real envido' => [[], self::R],
            'abrir con falta envido' => [[], self::F],
            'envido sobre envido' => [[self::E], self::E],
            'real sobre envido' => [[self::E], self::R],
            'falta sobre envido' => [[self::E], self::F],
            'real sobre dos envidos' => [[self::E, self::E], self::R],
            'falta sobre dos envidos' => [[self::E, self::E], self::F],
            'falta sobre real' => [[self::R], self::F],
            'falta sobre la cadena más larga' => [[self::E, self::E, self::R], self::F],
        ];
    }

    /**
     * @param  list<string>  $cadena
     */
    #[DataProvider('subidasInvalidas')]
    public function test_no_se_vuelve_atras_ni_se_repite(array $cadena, string $canto): void
    {
        $this->assertFalse(Envido::puedeSeguir($cadena, $canto));
    }

    public static function subidasInvalidas(): array
    {
        return [
            'tercer envido' => [[self::E, self::E], self::E],
            'envido después de real' => [[self::R], self::E],
            'envido después de envido y real' => [[self::E, self::R], self::E],
            'real después de real' => [[self::R], self::R],
            'real después de falta' => [[self::F], self::R],
            'falta después de falta' => [[self::E, self::F], self::F],
            'envido después de falta' => [[self::F], self::E],
        ];
    }
}
