<?php

namespace Tests\Feature;

use App\Avisos\Claves;
use App\Identidad\Paleta;
use App\Juego\Mesa;
use App\Models\Jugador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que hace falta para que el sitio se instale como una aplicación: el manifiesto con sus íconos, la
 * página de "sin conexión" y un service worker que guarda solo lo estático.
 *
 * Qué queda guardado de verdad en un navegador lo prueba además la prueba de navegador de una mano entera.
 */
class InstalableTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_manifiesto_dice_el_nombre_los_colores_del_pano_y_los_iconos_que_existen(): void
    {
        $manifiesto = json_decode((string) file_get_contents(public_path('manifest.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Vale Cuatro', $manifiesto['name']);
        $this->assertSame(['/', '/', 'standalone'], [$manifiesto['start_url'], $manifiesto['scope'], $manifiesto['display']]);
        // El fondo es el paño y la barra del sistema, el paño hondo: los mismos de la mesa.
        $this->assertSame(strtolower(Paleta::hex('pano')), $manifiesto['background_color']);
        $this->assertSame(strtolower(Paleta::hex('pano-hondo')), $manifiesto['theme_color']);

        $tamanos = [];

        foreach ($manifiesto['icons'] as $icono) {
            $archivo = public_path(ltrim($icono['src'], '/'));

            $this->assertFileExists($archivo);

            [$ancho, $alto] = getimagesize($archivo);

            $this->assertSame("{$ancho}x{$alto}", $icono['sizes'], $icono['src']);
            $this->assertSame('image/png', $icono['type']);
            $tamanos[$icono['purpose']][] = $ancho;
        }

        // Lo que pide un navegador para ofrecer la instalación: uno de 192, uno de 512 y uno recortable.
        $this->assertEqualsCanonicalizing([192, 512], $tamanos['any']);
        $this->assertSame([512], $tamanos['maskable']);
        // Y los dos que no van en el manifiesto: el de iPhone y la insignia de los avisos.
        $this->assertSame([180, 180], array_slice(getimagesize(public_path('iconos/icono-apple-180.png')), 0, 2));
        $this->assertFileExists(public_path('iconos/insignia-96.png'));
    }

    public function test_todas_las_paginas_nombran_el_manifiesto_y_la_version_de_lo_compilado(): void
    {
        foreach (['/', '/modos', '/ranking', '/sin-conexion'] as $pagina) {
            $this->get($pagina)->assertOk()
                ->assertSee('<link rel="manifest" href="/manifest.json">', false)
                ->assertSee('<link rel="apple-touch-icon" href="/iconos/icono-apple-180.png">', false)
                ->assertSee('<meta name="version" content="', false);
        }
    }

    public function test_la_portada_ofrece_instalarlo_en_una_franja_que_arranca_escondida(): void
    {
        $portada = $this->get('/')->assertOk()->assertSee('Llevalo en el celular.');

        // La franja y su botón no se ven hasta que el navegador avisa que se puede instalar.
        $portada->assertSee('data-donde-instalar hidden', false);
        $this->assertSame(1, preg_match('/<button[^>]*data-instalar hidden>\s*<svg.*?<\/svg>\s*Instalar\s*<\/button>/s', $portada->getContent()));

        // En las demás páginas queda el link del pie, igual de escondido.
        $this->get('/ranking')->assertOk()->assertDontSee('data-donde-instalar', false)->assertSee('<li data-instalar hidden>', false);
    }

    public function test_la_apertura_de_la_aplicacion_instalada_va_solo_en_la_portada(): void
    {
        // Es donde abre la aplicación (start_url). Se muestra solo si está instalada, y eso lo decide el navegador.
        $this->get('/')->assertOk()
            ->assertSee('class="apertura', false)
            ->assertSee('(display-mode: standalone)', false)
            ->assertSee('(prefers-reduced-motion: reduce)', false);

        foreach (['/modos', '/ranking', '/sin-conexion'] as $pagina) {
            $this->get($pagina)->assertOk()->assertDontSee('class="apertura', false)->assertDontSee('abriendo', false);
        }
    }

    public function test_lo_que_se_mide_contra_el_alto_usa_el_que_la_pagina_ve_de_verdad(): void
    {
        // Toda página deja medido el alto visible antes de pintar, y lo usa para su propio alto mínimo.
        $this->get('/')->assertOk()->assertSee("raiz.style.setProperty('--alto'", false)->assertSee('min-h-(--alto)', false);

        // Instalada en un celular, la unidad dvh cuenta más alto del que se ve: los cantos de la mesa y el botón
        // de la portada quedaban tapados. Por eso no se usa suelta en ningún lado: solo como valor de fábrica.
        $estilos = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(resource_path('css/app.css')));

        $this->assertStringContainsString('--alto: 100dvh;', $estilos);
        $this->assertSame(0, preg_match('/dvh/', str_replace(['--alto: 100dvh;', 'var(--alto, 100dvh)'], '', $estilos)));

        foreach (['.mesa', '.mesa-cuatro', '.mesa-completa', '.portada-inicio'] as $regla) {
            $this->assertSame(1, preg_match('/(?<![\w-])'.preg_quote($regla, '/').' \{[^}]*var\(--alto, 100dvh\)/', $estilos), $regla);
        }

        $sueltas = [];

        foreach ([resource_path('views'), resource_path('js')] as $carpeta) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)) as $archivo) {
                foreach (file($archivo->getPathname()) as $numero => $linea) {
                    // Los comentarios pueden nombrar la unidad; el código no.
                    if (preg_match('/^\s*(\*|\||\/\/|\/\*|\{\{--)/', $linea) !== 1 && preg_match('/dvh/', str_replace('var(--alto, 100dvh)', '', $linea)) === 1) {
                        $sueltas[] = $archivo->getFilename().':'.($numero + 1);
                    }
                }
            }
        }

        $this->assertSame([], $sueltas);
    }

    public function test_la_pagina_de_sin_conexion_es_la_misma_para_todos(): void
    {
        $sinSesion = $this->get('/sin-conexion')->assertOk()->assertSee('Sin conexión')->assertSee('Probar de nuevo');

        // El service worker la guarda una vez y se la muestra a quien sea: no puede decir nada de nadie.
        $jugador = Jugador::factory()->create(['apodo' => 'Fulano de Tal']);
        $conSesion = $this->actingAs($jugador)->get('/sin-conexion')->assertOk();

        $conSesion->assertDontSee('Fulano de Tal')->assertDontSee('Ingresar')->assertDontSee('Historial');
        $sinSesion->assertDontSee('Ingresar')->assertDontSee('Historial');
    }

    public function test_el_service_worker_guarda_solo_lo_estatico_y_la_pagina_de_sin_conexion(): void
    {
        $codigo = (string) file_get_contents(public_path('sw.js'));
        $sinComentarios = (string) preg_replace(['~/\*.*?\*/~s', '~(?<![:\'"`])//[^\n]*~'], '', $codigo);

        // Qué se guarda lo decide una sola función, y solo deja pasar lo compilado, los íconos y tres archivos sueltos.
        $this->assertSame(1, preg_match('/function seGuarda\(url\) \{(.*?)\n\}/s', $sinComentarios, $regla));
        $this->assertStringContainsString('url.origin === self.location.origin', $regla[1]);
        preg_match_all("/startsWith\('([^']+)'\)/", $regla[1], $carpetas);
        $this->assertSame(['/build/assets/', '/iconos/'], $carpetas[1]);
        $this->assertSame(1, preg_match("/const SUELTOS = \[([^\]]+)\]/", $sinComentarios, $sueltos));
        $this->assertSame("'/favicon.svg', '/favicon.ico', '/manifest.json'", trim($sueltos[1]));

        // Todo lo que se guarda pasó por esa función o es la página de sin conexión: no hay otro lugar que guarde.
        preg_match_all('/\.put\(([^,]+),/', $sinComentarios, $guardados);
        $this->assertSame(['SIN_CONEXION', 'pieza', 'fuente', 'pedido'], array_map(trim(...), $guardados[1]));
        $this->assertStringNotContainsString('addAll', $sinComentarios);

        // Una página pedida va siempre a la red; si la red no contesta, la única que sale de lo guardado es esa.
        $this->assertSame(1, preg_match("/if \(pedido\.mode === 'navigate'\) \{(.*?)\n    \}/s", $sinComentarios, $paginas));
        $this->assertStringContainsString('fetch(pedido).catch(() => caches.match(SIN_CONEXION', $paginas[1]);
        $this->assertStringNotContainsString('.put(', $paginas[1]);

        // Y lo que no es GET (una jugada, una seña) ni se mira.
        $this->assertStringContainsString("if (pedido.method !== 'GET') {\n        return;", $sinComentarios);
    }

    public function test_el_aviso_que_muestra_el_service_worker_lleva_a_la_mesa(): void
    {
        $codigo = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("self.addEventListener('push'", $codigo);
        $this->assertStringContainsString("self.addEventListener('notificationclick'", $codigo);
        $this->assertStringContainsString("badge: '/iconos/insignia-96.png'", $codigo);
        $this->assertStringContainsString("aviso.url || '/mesa'", $codigo);
    }

    public function test_la_mesa_ofrece_los_avisos_solo_con_otra_persona_enfrente(): void
    {
        $mesa = $this->app->make(Mesa::class);

        // Contra el bot no hay a quién esperar: la mesa no recibe nada para pedir avisos.
        $solo = Jugador::factory()->create();
        $mesa->abrir($solo);

        $this->assertNull($this->actingAs($solo)->get('/mesa')->assertOk()->viewData('avisos'));

        // Entre personas recibe la clave pública del sitio y adónde pedir cada cosa.
        $anfitrion = Jugador::factory()->create();
        $sala = $mesa->crearSala($anfitrion);

        $enLaSala = $this->actingAs($anfitrion)->get(route('sala', $sala->codigo))->assertOk()->assertSee('Avisarme cuando se siente');
        $this->assertSame(['clave', 'suscribir', 'baja', 'vista', 'token'], array_keys($enLaSala->viewData('avisos')));

        $mesa->sentarse($sala->codigo, Jugador::factory()->invitado()->create());

        $enLaMesa = $this->actingAs($anfitrion)->get('/mesa')->assertOk()->assertSee('data-boton-avisos', false);
        $avisos = $enLaMesa->viewData('avisos');

        $this->assertSame($enLaSala->viewData('avisos')['clave'], $avisos['clave']);
        $this->assertSame([route('avisos.suscribir'), route('avisos.baja'), route('avisos.vista')], [$avisos['suscribir'], $avisos['baja'], $avisos['vista']]);
        // La clave que viaja es la pública: la privada no aparece en ninguna parte de la página.
        $this->assertStringNotContainsString($this->app->make(Claves::class)->privada(), $enLaMesa->getContent());
    }
}
