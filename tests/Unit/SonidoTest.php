<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Las reglas del sonido de la mesa, leídas del código: ningún sonido es largo, nada suena en loop y no
 * hay archivos de audio (los sonidos los fabrica el navegador). Cómo suenan se juzga escuchando: esto
 * cuida que nadie rompa una regla sin darse cuenta.
 */
class SonidoTest extends TestCase
{
    /** Lo más que puede durar un sonido entero, en segundos. */
    private const float TOPE = 1.5;

    public function test_ningun_sonido_llega_al_segundo_y_medio(): void
    {
        $codigo = $this->sonido();

        // Cada capa dice cuánto dura y, si no arranca con el sonido, cuándo empieza.
        preg_match_all('/\bdura:\s*([\d.]+)/', $codigo, $duraciones);
        preg_match_all('/\ben:\s*([\d.]+)/', $codigo, $comienzos);

        $this->assertGreaterThan(10, count($duraciones[1]), 'El test dejó de encontrar las capas de los sonidos.');

        // Aunque la capa más larga arrancara en el comienzo más tardío, el sonido entero entra en el tope.
        $masLarga = max(array_map(floatval(...), $duraciones[1]));
        $masTarde = max([0.0, ...array_map(floatval(...), $comienzos[1])]);

        $this->assertLessThan(self::TOPE, $masLarga + $masTarde, "La capa más larga dura {$masLarga} s y la que más tarde arranca lo hace a los {$masTarde} s.");
    }

    public function test_nada_suena_en_loop(): void
    {
        foreach ($this->archivos('resources/js', 'js') as $ruta => $codigo) {
            $this->assertDoesNotMatchRegularExpression('/\.loop\s*=\s*true|\bloop:\s*true/', $codigo, "{$ruta} deja algo sonando en loop.");
        }
    }

    public function test_no_hay_archivos_de_audio_ni_etiquetas_que_los_pidan(): void
    {
        $raiz = dirname(__DIR__, 2);
        $audios = [];

        foreach (['public', 'resources'] as $carpeta) {
            $recorrido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$raiz}/{$carpeta}", \FilesystemIterator::SKIP_DOTS));

            foreach ($recorrido as $archivo) {
                if (in_array(strtolower($archivo->getExtension()), ['mp3', 'ogg', 'wav', 'm4a', 'aac', 'flac', 'opus'], true)) {
                    $audios[] = $archivo->getPathname();
                }
            }
        }

        $this->assertSame([], $audios, 'Los sonidos se fabrican en el navegador: no hay archivos de audio.');

        foreach ($this->archivos('resources/views', 'php') as $ruta => $vista) {
            $this->assertDoesNotMatchRegularExpression('/<audio\b/', $vista, "{$ruta} tiene una etiqueta de audio.");
        }
    }

    public function test_el_audio_se_abre_solo_al_prender_el_sonido(): void
    {
        $codigo = $this->sonido();

        // Un solo lugar crea el contexto de audio, y es la función que prende el sonido.
        $this->assertSame(1, preg_match_all('/new Contexto\(/', $codigo));
        $this->assertSame(0, preg_match_all('/new (webkit)?AudioContext\(/', implode("\n", $this->archivos('resources/js', 'js'))));
        $this->assertMatchesRegularExpression('/export function prenderSonido\(\) \{.*?new Contexto\(/s', $codigo);

        // Y la mesa arranca con lo guardado, que sin nada guardado es apagado.
        $this->assertMatchesRegularExpression("/return localStorage\.getItem\(CLAVE\) === 'si';/", $codigo);
    }

    private function sonido(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/sonido.js');
    }

    /**
     * El contenido de cada archivo de una carpeta, por su ruta dentro del proyecto.
     *
     * @return array<string, string>
     */
    private function archivos(string $carpeta, string $extension): array
    {
        $archivos = [];
        $recorrido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$carpeta, \FilesystemIterator::SKIP_DOTS));

        foreach ($recorrido as $archivo) {
            if ($archivo->getExtension() === $extension) {
                $archivos[$carpeta.'/'.str_replace('\\', '/', $recorrido->getSubPathname())] = (string) file_get_contents($archivo->getPathname());
            }
        }

        return $archivos;
    }
}
