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

    /** El bloque que apaga el movimiento de las piezas del sitio (hay otros dos chicos, para el cambio de modo). */
    private const string REDUCIDO = '@media (prefers-reduced-motion: reduce) {'."\n".'    .boton';

    public function test_las_transiciones_de_los_estilos_solo_mueven_transform_y_opacity(): void
    {
        $estilos = $this->estilos();

        // Hasta el punto y coma o hasta la llave, por si es la última declaración de la regla y no lo lleva.
        preg_match_all('/(?<![\w-])transition\s*:\s*([^;}]+)/', $estilos, $transiciones);
        $this->assertNotEmpty($transiciones[1]);

        foreach ($transiciones[1] as $transicion) {
            // Las comas de adentro de una curva (cubic-bezier, steps) no separan transiciones.
            foreach (explode(',', $this->sinParentesis($transicion)) as $parte) {
                $propiedad = strtok(trim($parte), " \t\r\n");

                $this->assertContains($propiedad, [...self::SE_ANIMA, 'none'], "Una transición anima «{$propiedad}»: ".trim($transicion));
            }
        }

        // La propiedad suelta tampoco: es otra manera de escribir lo mismo.
        preg_match_all('/transition-property\s*:\s*([^;}]+)/', $estilos, $propiedades);

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

        // Dentro de un cuadro también puede ir desde dónde gira o con qué curva sigue: eso no es mover otra cosa.
        $permitidas = [...self::SE_ANIMA, 'transform-origin', 'animation-timing-function'];

        foreach ($cuadros as $nombre => $cuerpo) {
            preg_match_all('/([a-z-]+)\s*:/', $this->sinParentesis($cuerpo), $propiedades);

            foreach ($propiedades[1] as $propiedad) {
                $this->assertContains($propiedad, $permitidas, "La animación «{$nombre}» mueve «{$propiedad}».");
            }
        }
    }

    public function test_las_vistas_solo_piden_transiciones_de_transform_y_opacity(): void
    {
        foreach ($this->archivos('resources/views', 'php') as $ruta => $vista) {
            // Las clases de Tailwind, con las variantes que lleven adelante ("sm:", "hover:", "motion-reduce:"): lo que
            // se revisa es la clase, la lleve quien la lleve. "x-transition" es de Alpine y no entra: sus clases se
            // revisan por su nombre.
            preg_match_all('/(?<![\w.:-])(?:[a-z0-9-]+:)*(transition(?:-[a-z\[\],_-]+)?)(?![\w-])/', $vista, $clases);

            foreach ($clases[1] as $clase) {
                $this->assertContains($clase, ['transition-opacity', 'transition-transform', 'transition-none'], "{$ruta} usa «{$clase}».");
            }

            // Las animaciones de fábrica de Tailwind corren en loop.
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])animate-(spin|ping|pulse|bounce)(?![\w-])/', $vista, "{$ruta} usa una animación en loop.");

            // El hover que mueve algo se escribe en los estilos, dentro del bloque de puntero fino: la variante
            // "hover:" de las vistas no lo garantiza.
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])hover:-?(translate|rotate|scale|skew)/', $vista, "{$ruta} mueve algo con hover: desde la vista.");
        }
    }

    /**
     * Es una lista de lo que no se anima nunca, no una lista de lo permitido: no puede saber qué objeto es un cuadro
     * de animación y cuál no. Si aparece una clave con uno de estos nombres que no anima nada, el test falla igual:
     * ahí se mira el caso y, si corresponde, se lo nombra de otra manera.
     */
    public function test_el_javascript_no_anima_medidas_colores_ni_posiciones(): void
    {
        $prohibidas = 'width|height|maxWidth|maxHeight|minWidth|minHeight|top|left|right|bottom|margin\w*|padding\w*|inset\w*|gap'
            .'|color|backgroundColor|background|borderColor|borderWidth|borderRadius|boxShadow|outline\w*|fontSize|letterSpacing|filter|strokeDashoffset';

        foreach ($this->archivos('resources/js', 'js') as $ruta => $codigo) {
            $codigo = $this->sinComentarios($codigo);

            // Como clave de un objeto, con comillas o sin ellas.
            preg_match_all('/[{,]\s*[\'"]?('.$prohibidas.')[\'"]?\s*:/', $codigo, $claves);
            $this->assertSame([], $claves[1], "{$ruta} le pone valores a: ".implode(', ', $claves[1]));

            // Y escrito directo sobre el estilo de un elemento, que es otra manera de mover lo mismo.
            preg_match_all('/\.style\.(\w+)\s*=|\.style\.setProperty\(\s*[\'"]([\w-]+)/', $codigo, $directas, PREG_SET_ORDER);

            foreach ($directas as $directa) {
                $propiedad = $directa[1] !== '' ? $directa[1] : $directa[2];

                $this->assertContains($propiedad, self::SE_ANIMA, "{$ruta} escribe «{$propiedad}» en el estilo de un elemento.");
            }

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
        $reducido = $this->bloque($estilos, self::REDUCIDO);
        $resto = str_replace([$conMouse, $reducido], '', $estilos);

        $this->assertNotSame('', $conMouse);
        $this->assertNotSame('', $reducido);

        // En una pantalla táctil el toque dispara un hover falso: fuera de esos bloques no hay ninguno, salvo la barra de
        // scroll del navegador, que no se mueve (cambia de color y de grosor).
        preg_match_all('/^[^{}@\/]*:hover[^{]*\{/m', $resto, $sueltos);
        $sueltos = array_filter($sueltos[0], fn (string $selector) => ! str_contains($selector, '::-webkit-scrollbar'));

        $this->assertSame([], array_values(array_map(trim(...), $sueltos)));
    }

    public function test_todo_lo_que_se_mueve_en_los_estilos_tiene_su_version_de_movimiento_reducido(): void
    {
        $estilos = $this->estilos();
        $reducido = $this->bloque($estilos, self::REDUCIDO);
        $resto = str_replace($reducido, '', $estilos);

        // Cada regla que mueve algo (una transición de transform, o una animación) tiene que estar nombrada en el
        // bloque de movimiento reducido por la pieza que se mueve: la última clase de su selector, no la de un
        // contenedor. El test comprueba que esté nombrada; cómo queda (quieta, o con un fundido) se mira en el navegador.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $resto, $reglas, PREG_SET_ORDER);
        $seMueven = 0;

        foreach ($reglas as [, $selectores, $cuerpo]) {
            $mueve = preg_match('/(?<![\w-])transition\s*:[^;}]*\btransform\b/', $cuerpo) === 1
                || preg_match('/(?<![\w-])animation\s*:\s*(?!none)/', $cuerpo) === 1;

            if (! $mueve || str_contains($selectores, '::view-transition')) {
                continue;
            }

            foreach (explode(',', $selectores) as $selector) {
                $seMueven++;
                preg_match_all('/\.([a-z][\w-]*)/', $selector, $clases);
                $pieza = end($clases[1]);

                $this->assertMatchesRegularExpression(
                    '/\.'.preg_quote((string) $pieza, '/').'(?![\w-])/',
                    $reducido,
                    'Se mueve y no tiene versión de movimiento reducido: '.trim((string) preg_replace('/\s+/', ' ', $selector)),
                );
            }
        }

        $this->assertGreaterThan(15, $seMueven, 'El test dejó de encontrar las reglas que mueven algo.');
    }

    public function test_el_javascript_que_anima_pregunta_si_hay_que_moverse_menos(): void
    {
        foreach ($this->archivos('resources/js', 'js') as $ruta => $codigo) {
            // Sin los comentarios: que el archivo hable de movimiento reducido no quiere decir que lo consulte.
            $codigo = $this->sinComentarios($codigo);

            if (str_contains($codigo, '.animate(')) {
                $this->assertMatchesRegularExpression('/movimientoReducido|\breducido\b/', $codigo, "{$ruta} anima sin mirar la preferencia de movimiento reducido.");
            }
        }
    }

    private function estilos(): string
    {
        return $this->sinComentarios((string) file_get_contents($this->raiz().'/resources/css/app.css'));
    }

    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Sin los comentarios de bloque ni los de línea (que en JavaScript empiezan con dos barras; las de una
     * dirección, "http://", no son comentario).
     */
    private function sinComentarios(string $codigo): string
    {
        $codigo = (string) preg_replace('~/\*.*?\*/~s', '', $codigo);

        return (string) preg_replace('~(?<![:\'"`])//[^\n]*~', '', $codigo);
    }

    /**
     * Sin lo que va entre paréntesis, también los de adentro: "cubic-bezier(0.23, 1, 0.32, 1)" queda "cubic-bezier".
     */
    private function sinParentesis(string $texto): string
    {
        do {
            $texto = (string) preg_replace('/\([^()]*\)/', '', $texto, -1, $cambios);
        } while ($cambios > 0);

        return $texto;
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
