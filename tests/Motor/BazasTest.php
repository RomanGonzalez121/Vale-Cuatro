<?php

namespace Tests\Motor;

use App\Motor\Bazas;
use App\Motor\Carta;
use App\Motor\Mesa;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BazasTest extends TestCase
{
    private const NOSOTROS = 0;

    private const ELLOS = 1;

    public function test_la_baza_la_gana_la_carta_mas_alta(): void
    {
        $this->assertSame(1, $this->baza(new Mesa, [[0, '3-oro'], [1, '7-espada']]));
        $this->assertSame(0, $this->baza(new Mesa, [[0, '1-basto'], [1, '7-espada']]));
    }

    public function test_dos_cartas_del_mismo_valor_hacen_parda(): void
    {
        $this->assertNull($this->baza(new Mesa, [[0, '3-oro'], [1, '3-copa']]));
        $this->assertNull($this->baza(new Mesa, [[1, '1-copa'], [0, '1-oro']]));
    }

    public function test_de_a_cuatro_gana_el_equipo_de_la_carta_mas_alta(): void
    {
        $mesa = new Mesa(4);

        $this->assertSame(2, $this->baza($mesa, [[0, '4-oro'], [1, '3-copa'], [2, '7-oro'], [3, '12-basto']]));
    }

    public function test_de_a_cuatro_si_empatan_rivales_es_parda_aunque_haya_cartas_mas_bajas(): void
    {
        $mesa = new Mesa(4);

        $this->assertNull($this->baza($mesa, [[0, '3-oro'], [1, '3-copa'], [2, '4-oro'], [3, '5-basto']]));
    }

    public function test_de_a_cuatro_si_empatan_dos_companeros_gana_su_equipo_y_se_la_lleva_el_primero(): void
    {
        $mesa = new Mesa(4);

        $this->assertSame(3, $this->baza($mesa, [[3, '3-oro'], [0, '4-copa'], [1, '3-basto'], [2, '5-basto']]));
    }

    public function test_una_baza_puede_tener_tres_cartas_si_alguien_se_fue_al_mazo(): void
    {
        $mesa = new Mesa(4);

        $this->assertSame(1, $this->baza($mesa, [[0, '4-oro'], [1, '2-copa'], [2, '6-oro']]));
    }

    /**
     * La tabla de pardas del reglamento, fila por fila. El mano es de ELLOS para que
     * se note cuándo decide el mano y cuándo no.
     *
     * @param  list<int|null>  $resultados
     */
    #[DataProvider('tablaDePardas')]
    public function test_tabla_de_pardas(array $resultados, ?int $gana): void
    {
        $this->assertSame($gana, Bazas::ganadorDeMano($resultados, self::ELLOS));
    }

    public static function tablaDePardas(): array
    {
        return [
            'parda la primera: gana quien gana la segunda' => [[null, self::NOSOTROS], self::NOSOTROS],
            'pardas la primera y la segunda: gana quien gana la tercera' => [[null, null, self::NOSOTROS], self::NOSOTROS],
            'pardas las tres: gana el mano' => [[null, null, null], self::ELLOS],
            'parda la segunda: gana quien ganó la primera' => [[self::NOSOTROS, null], self::NOSOTROS],
            'parda la tercera con una y una: gana quien ganó la primera' => [[self::NOSOTROS, self::ELLOS, null], self::NOSOTROS],
        ];
    }

    /**
     * @param  list<int|null>  $resultados
     */
    #[DataProvider('manosSinParda')]
    public function test_sin_pardas_gana_quien_gana_dos_bazas(array $resultados, ?int $gana): void
    {
        $this->assertSame($gana, Bazas::ganadorDeMano($resultados, self::ELLOS));
    }

    public static function manosSinParda(): array
    {
        return [
            'con una baza todavía no hay ganador' => [[self::NOSOTROS], null],
            'una baza parda sola tampoco define' => [[null], null],
            'las dos primeras' => [[self::NOSOTROS, self::NOSOTROS], self::NOSOTROS],
            'una y una: se sigue' => [[self::NOSOTROS, self::ELLOS], null],
            'dos pardas: se sigue' => [[null, null], null],
            'primera y tercera' => [[self::NOSOTROS, self::ELLOS, self::NOSOTROS], self::NOSOTROS],
            'segunda y tercera' => [[self::NOSOTROS, self::ELLOS, self::ELLOS], self::ELLOS],
        ];
    }

    /**
     * @param  list<array{0: int, 1: string}>  $jugadas
     */
    private function baza(Mesa $mesa, array $jugadas): ?int
    {
        return Bazas::ganadorDeBaza(array_map(fn (array $jugada) => [$jugada[0], Carta::de($jugada[1])], $jugadas), $mesa);
    }
}
