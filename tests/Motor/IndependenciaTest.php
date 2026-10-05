<?php

namespace Tests\Motor;

use PHPUnit\Framework\TestCase;

/**
 * El motor es PHP puro: no conoce Laravel, la base de datos ni el resto de la aplicación.
 */
class IndependenciaTest extends TestCase
{
    public function test_el_motor_no_importa_nada_de_laravel_ni_del_resto_de_la_aplicacion(): void
    {
        $archivos = glob(dirname(__DIR__, 2).'/app/Motor/*.php');

        $this->assertNotEmpty($archivos);

        foreach ($archivos as $archivo) {
            $codigo = file_get_contents($archivo);
            $nombre = basename($archivo);

            $this->assertStringNotContainsString('Illuminate\\', $codigo, "{$nombre} usa Laravel.");
            $this->assertDoesNotMatchRegularExpression('/\bApp\\\\(?!Motor\b)/', $codigo, "{$nombre} usa código de afuera del motor.");
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w>:$])(app|config|collect|session|request|auth|cache|event|now|env|view|route|logger|dispatch|resolve)\(/',
                $codigo,
                "{$nombre} llama a una función de ayuda de Laravel.",
            );
        }
    }

    public function test_estos_tests_no_arrancan_laravel(): void
    {
        foreach (glob(__DIR__.'/*.php') as $archivo) {
            $this->assertStringNotContainsString('Tests\\TestCase', file_get_contents($archivo), basename($archivo).' arranca Laravel.');
        }
    }
}
