<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que el sitio necesita para andar publicado: detrás del servidor de Render, que es quien atiende el
 * HTTPS, y con el tiempo real entrando por la misma dirección que las páginas.
 */
class PublicacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_detras_de_un_servidor_con_https_los_links_del_sitio_salen_con_https(): void
    {
        // El servidor de adelante avisa con esta cabecera que el visitante entró por HTTPS.
        $pagina = $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://vale-cuatro.example/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('action="https://vale-cuatro.example/jugar"', $pagina);
        $this->assertStringNotContainsString('http://vale-cuatro.example', $pagina);
    }

    public function test_nadie_puede_hacer_que_los_links_del_sitio_apunten_a_otro_lado(): void
    {
        // Esta cabecera la puede mandar cualquiera en su pedido. Si el sitio le creyera, sus links (el de
        // invitación, y el de recuperar la contraseña cuando exista) saldrían con el nombre que diga ella.
        $pagina = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'sitio-trucho.example'])
            ->get('http://vale-cuatro.example/')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('sitio-trucho.example', $pagina);
        $this->assertStringContainsString('action="https://vale-cuatro.example/jugar"', $pagina);
    }

    public function test_la_pagina_le_dice_al_navegador_adonde_conectarse_para_el_tiempo_real(): void
    {
        config(['tiempo_real.navegador' => ['clave' => 'clave-publica', 'host' => 'vale-cuatro.example', 'puerto' => 443, 'esquema' => 'https']]);

        $this->assertSame(
            ['clave' => 'clave-publica', 'host' => 'vale-cuatro.example', 'puerto' => 443, 'esquema' => 'https'],
            $this->tiempoRealDe($this->get('/')->assertOk()->getContent()),
        );
    }

    public function test_lo_que_va_a_la_pagina_es_la_clave_publica_y_nunca_la_secreta(): void
    {
        config([
            'broadcasting.connections.reverb.secret' => 'el-secreto-de-reverb',
            'reverb.apps.apps.0.secret' => 'el-secreto-de-reverb',
            'tiempo_real.navegador.clave' => 'clave-publica',
        ]);

        foreach (['/', '/modos', '/ranking', '/ingresar'] as $ruta) {
            $pagina = $this->get($ruta)->assertOk()->getContent();

            $this->assertStringContainsString('clave-publica', $pagina, $ruta);
            $this->assertStringNotContainsString('el-secreto-de-reverb', $pagina, $ruta);
        }
    }

    public function test_sin_tiempo_real_configurado_la_pagina_lo_dice_con_la_clave_vacia(): void
    {
        config(['tiempo_real.navegador.clave' => null]);

        // El JavaScript ve que no hay clave y no intenta conectarse: la mesa sigue preguntando por su cuenta.
        $this->assertNull($this->tiempoRealDe($this->get('/')->assertOk()->getContent())['clave']);
    }

    /**
     * Lo que dice la etiqueta "tiempo-real" del encabezado de una página.
     *
     * @return array<string, mixed>
     */
    private function tiempoRealDe(string $pagina): array
    {
        $this->assertSame(1, preg_match('/<meta name="tiempo-real" content="([^"]*)">/', $pagina, $encontrado));

        return json_decode(html_entity_decode($encontrado[1]), true);
    }
}
