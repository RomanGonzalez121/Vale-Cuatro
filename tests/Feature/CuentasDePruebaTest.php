<?php

namespace Tests\Feature;

use App\Models\Jugador;
use Database\Seeders\CuentasDePruebaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentasDePruebaTest extends TestCase
{
    use RefreshDatabase;

    public function test_con_las_cuentas_sembradas_se_puede_ingresar(): void
    {
        $this->seed();

        foreach (CuentasDePruebaSeeder::CUENTAS as $apodo => $email) {
            $this->post('/ingresar', ['email' => $email, 'password' => CuentasDePruebaSeeder::PASSWORD])->assertRedirect('/');

            $this->assertSame($apodo, auth()->user()->apodo);

            $this->post('/salir');
        }
    }

    public function test_sembrar_dos_veces_no_duplica_las_cuentas(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(count(CuentasDePruebaSeeder::CUENTAS), Jugador::count());
    }

    public function test_en_produccion_no_se_crean_cuentas_de_prueba(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Se llama al seeder directo: en producción el comando de sembrar pide confirmación.
        (new CuentasDePruebaSeeder)->run();

        $this->assertSame(0, Jugador::count());
    }
}
