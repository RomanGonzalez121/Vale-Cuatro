<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

/**
 * La base de las pruebas de navegador: un Chrome de verdad, sin ventana, que usa el sitio como una persona.
 *
 * Estas pruebas no tocan la base de datos por su cuenta: todo lo hacen por la pantalla (entrar, registrarse,
 * jugar). El sitio contra el que corren se levanta aparte, con su propia base vacía; cómo, está en la
 * integración continua (.github/workflows) y en el README.
 */
abstract class DuskTestCase extends BaseTestCase
{
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Dónde está el sitio a probar. No es la dirección del .env: es un servidor levantado para esto.
     */
    protected function baseUrl(): string
    {
        return rtrim((string) env('DUSK_URL', 'http://127.0.0.1:8010'), '/');
    }

    protected function driver(): RemoteWebDriver
    {
        $opciones = (new ChromeOptions)->addArguments([
            '--window-size=1280,800',
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ]);

        // En una máquina sin Chrome instalado se le puede decir cuál usar (cualquier Chromium sirve).
        if ($navegador = env('DUSK_CHROME')) {
            $opciones->setBinary($navegador);
        }

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(ChromeOptions::CAPABILITY, $opciones),
        );
    }
}
