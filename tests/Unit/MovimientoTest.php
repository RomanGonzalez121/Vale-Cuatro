<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Las reglas de movimiento del sitio, leídas del código: solo se animan transform y opacity, nada corre
 * en loop y ninguna entrada arranca lenta. No miran cómo se ve una animación (eso se revisa en el
 * navegador): cuidan que nadie agregue una que rompa la regla sin darse cuenta.
 */
class MovimientoTest extends TestCase
{
    private const array SE_ANIMA = ['transform', 'opacity'];

    public function test_las_transiciones_de_los_estilos_solo_mueven_transform_y_opacity(): void
    {
        $estilos = $this->estilos();

        preg_match_all('/(?<![\w-])transition\s*:\s*([^;]+);/', $estilos, $transiciones);
        $this->assertNotEmpty($transiciones[1]);

        foreach ($transiciones[1] as $transicion) {
            foreach (explode(',', $transicion) as $parte) {
                $propiedad = strtok(trim($parte), " \t\r\n");

                $this->assertContains($propiedad, [...self::SE_ANIMA, 'none'], "Una transición anima «{$propiedad}»: ".trim($transicion));
            }
        }

        // La propiedad suelta tampoco: es otra manera de escribir lo mismo.
        preg_match_all('/transition-property\s*:\s*([^;]+);/', $estilos, $propiedades);

        foreach ($propiedades[1] as $lista) {
            foreach (array_map(trim(...), explode(',', $lista)) as $propiedad) {
                $this->assertContains($propiedad, [...self::SE_ANIMA, 'none'], "transition-property anima «{$propiedad}».");
            }
        }
    }

    public function test_las_animaciones_de_los_estilos_solo_mueven_transform_y_opacity(): void
    {
        $cuadros = $this->cuadros($this->estilos());
        $this->assertNotEmpty($cuadros);

        foreach ($cuadros as $nombre => $cuerpo) {
            preg_match_all('/([a-z-]+)\s*:/', $cuerpo, $propiedades);

            foreach ($propiedades[1] as $propiedad) {
                $this->assertContains($propiedad, self::SE_ANIMA, "La animación «{$nombre}» mueve «{$propiedad}».");
            }
        }
    }

    public function test_las_vistas_solo_piden_transiciones_de_transform_y_opacity(): void
    {
        foreach ($this->archivos('resources/views', 'php') as $ruta => $vista) {
            // Las clases de Tailwind. "x-transition" es de Alpine y no entra: sus clases se revisan por su nombre.
            preg_match_all('/(?<![\w:.-])transition(?:-[a-z\[\],_-]+)?(?![\w-])/', $vista, $clases);

            foreach ($clases[0] as $clase) {
                $this->assertContains($clase, ['transition-opacity', 'transition-transform', 'transition-none'], "{$ruta} usa «{$clase}».");
            }

            // Las animaciones de fábrica de Tailwind corren en loop.
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])animate-(spin|ping|pulse|bounce)(?![\w-])/', $vista, "{$ruta} usa una animación en loop.");
        }
    }

    public function test_el_javascript_no_anima_medidas_colores_ni_posiciones(): void
    {
        // Las propiedades que no se animan nunca, escritas como clave de un cuadro de animación o de un estilo.
        $prohibidas = 'width|height|top|left|right|bottom|margin\w*|padding\w*|inset\w*|color|backgroundColor|background|borderRadius|boxShadow|fontSize|filter';

        foreach ($this->archivos('resources/js', 'js') as $ruta => $codigo) {
            preg_match_all('/[{,]\s*('.$prohibidas.')\s*:/', $this->sinComentarios($codigo), $claves);

            $this->assertSame([], $claves[1], "{$ruta} le pone valores a: ".implode(', ', $claves[1]));

            // Recortar con clip-path es la única excepción, y está en un solo lugar: la página que se abre en
            // círculo al cambiar de modo.
            if (basename($ruta) !== 'modo.js') {
                $this->assertStringNotContainsString('clipPath', $codigo, "{$ruta} anima clip-path.");
            }
        }
    }

    public function test_nada_corre_en_loop(): void
    {
        $this->assertDoesNotMatchRegularExpression('/\binfinite\b/', $this->estilos(), 'Los estilos tienen una animación infinita.');

        foreach ($this->archivos('resources/js', 'js') as $ruta => $codigo) {
            $this->assertDoesNotMatchRegularExpression('/\biterations\s*:/', $this->sinComentarios($codigo), "{$ruta} repite una animación.");
        }
    }

    public function test_ninguna_curva_arranca_lenta(): void
    {
        // "ease-in" deja para el final lo que la persona está esperando ver. Las entradas desaceleran al llegar.
        $lenta = '/\bease-in\b(?!-out)/';

        $this->assertDoesNotMatchRegularExpression($lenta, $this->estilos());

        foreach ([...$this->archivos('resources/js', 'js'), ...$this->archivos('resources/views', 'php')] as $ruta => $contenido) {
            $this->assertDoesNotMatchRegularExpression($lenta, $contenido, "{$ruta} usa ease-in.");
        }
    }

    public function test_el_movimiento_al_pasar_el_mouse_solo_vale_con_puntero_fino(): void
    {
        $estilos = $this->estilos();
        $conMouse = $this->bloque($estilos, '@media (hover: hover) and (pointer: fine)');
        // En el bloque de movimiento reducido los hover aparecen solo para apagarlos.
        $reducido = $this->bloque($estilos, '@media (prefers-reduced-motion: reduce) {'."\n".'    .boton');
        $resto = str_replace([$conMouse, $reducido], '', $estilos);

        $this->assertNotSame('', $conMouse);
        $this->assertNotSame('', $reducido);

        // En una pantalla táctil el toque dispara un hover falso: fuera de esos bloques no hay ninguno, salvo la barra de
        // scroll del navegador, que no se mueve (cambia de color y de grosor).
        preg_match_all('/^[^{}@\/]*:hover[^{]*\{/m', $resto, $sueltos);
        $sueltos = array_filter($sueltos[0], fn (string $selector) => ! str_contains($selector, '::-webkit-scrollbar'));

        $this->assertSame([], array_values(array_map(trim(...), $sueltos)));
    }

    private function estilos(): string
    {
        return $this->sinComentarios((string) file_get_contents($this->raiz().'/resources/css/app.css'));
    }

    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    private function sinComentarios(string $codigo): string
    {
        return (string) preg_replace('~/\*.*?\*/~s', '', $codigo);
    }

    /**
     * El cuerpo de cada @keyframes, por su nombre.
     *
     * @return array<string, string>
     */
    private function cuadros(string $estilos): array
    {
        preg_match_all('/@keyframes\s+([\w-]+)\s*\{/', $estilos, $inicios, PREG_OFFSET_CAPTURE);
        $cuadros = [];

        foreach ($inicios[1] as [$nombre, $desde]) {
            $cuadros[$nombre] = $this->entreLlaves($estilos, (int) strpos($estilos, '{', $desde));
        }

        return $cuadros;
    }

    /**
     * Un bloque entero de los estilos, desde su encabezado hasta la llave que lo cierra.
     */
    private function bloque(string $estilos, string $encabezado): string
    {
        $desde = strpos($estilos, $encabezado);

        if ($desde === false) {
            return '';
        }

        $abre = (int) strpos($estilos, '{', $desde);

        return substr($estilos, $desde, $abre - $desde + strlen($this->entreLlaves($estilos, $abre)) + 2);
    }

    /**
     * Lo que hay entre una llave que abre y la que la cierra, contando las de adentro.
     */
    private function entreLlaves(string $texto, int $abre): string
    {
        $hondo = 0;

        for ($i = $abre, $largo = strlen($texto); $i < $largo; $i++) {
            $hondo += match ($texto[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($hondo === 0) {
                return substr($texto, $abre + 1, $i - $abre - 1);
            }
        }

        return '';
    }

    /**
     * El contenido de cada archivo de una carpeta, por su ruta dentro del proyecto.
     *
     * @return array<string, string>
     */
    private function archivos(string $carpeta, string $extension): array
    {
        $archivos = [];
        $recorrido = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->raiz().'/'.$carpeta, \FilesystemIterator::SKIP_DOTS));

        foreach ($recorrido as $archivo) {
            if ($archivo->getExtension() === $extension) {
                $archivos[$carpeta.'/'.str_replace('\\', '/', $recorrido->getSubPathname())] = (string) file_get_contents($archivo->getPathname());
            }
        }

        return $archivos;
    }
}
