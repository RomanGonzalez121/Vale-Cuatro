<?php

namespace Tests\Feature;

use App\Juego\Bot;
use App\Juego\Desafios;
use App\Juego\Escalera;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Progresos;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Progreso;
use App\Models\Resultado;
use App\Motor\Accion;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\ResolviendoDesafios;

/**
 * Los desafíos y la escalera del club por las rutas del sitio: las listas, la puerta de entrada a cada
 * uno, el candado de la escalera y cómo quedan en el historial.
 */
class DesafiosYEscaleraPorHttpTest extends TestCase
{
    use RefreshDatabase;
    use ResolviendoDesafios;

    /**
     * Las rutas pasan a jugar contra un bot de prueba, para que una partida de la escalera se gane o se
     * pierda en pocas manos y sin depender del reparto. Si $seVa, se va al mazo apenas le toca; si no,
     * tira una carta (y quien se va es la persona).
     */
    private function contraUnBotDePrueba(bool $seVa): void
    {
        $this->app->instance(Mesa::class, new Mesa(new class($seVa) implements Bot
        {
            public function __construct(private readonly bool $seVa) {}

            public function decidir(array $vista): Accion
            {
                return $this->seVa
                    ? Accion::de(TipoDeAccion::Mazo)
                    : Accion::desdeArray(collect($vista['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value));
            }
        }));
    }

    /**
     * Juega la partida en curso hasta que se cierra. Si $gana, tira una carta cada vez que le toca (y el
     * bot se va); si no, se va al mazo en todas las manos.
     */
    private function jugarLaPartida(Jugador $jugador, bool $gana): Partida
    {
        $mesa = $this->app->make(Mesa::class);
        $partida = $mesa->enCursoDe($jugador);

        while ($partida->refresh()->enCurso()) {
            $vista = $mesa->vista($partida);

            if ($vista['fase'] === 'por_repartir') {
                $mesa->repartir($partida);

                continue;
            }

            $carta = collect($vista['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value);
            $mesa->actuar($partida, $gana ? Accion::desdeArray($carta) : Accion::de(TipoDeAccion::Mazo));
        }

        return $partida;
    }

    /**
     * Juega por las rutas las jugadas de la persona y devuelve el último paso que contestó el servidor.
     *
     * @param  list<Accion>  $jugadas
     * @return array<string, mixed>
     */
    private function jugarPorLasRutas(array $jugadas, array $deMas = []): array
    {
        $paso = [];

        foreach ($jugadas as $accion) {
            $paso = $this->postJson('/mesa/accion', [...$accion->aArray(), ...$deMas])->assertOk()->json('pasos.0');
        }

        return $paso;
    }

    public function test_la_lista_de_desafios_se_mira_sin_sesion_y_no_crea_nada(): void
    {
        $respuesta = $this->get('/desafios')->assertOk()->assertSee('0</span>', false)->assertSee('de 8 superados');

        foreach (Desafios::sueltos() as $desafio) {
            $respuesta->assertSee($desafio['titulo'])->assertSee($desafio['consigna'])
                ->assertSee('action="'.route('desafios.jugar', $desafio['clave']).'"', false);
        }

        // Las manos de la escalera no están en esta lista.
        $respuesta->assertDontSee('Vale cuatro')->assertDontSee('Los tres que faltan');

        $this->assertGuest();
        $this->assertSame(0, Jugador::query()->count());
    }

    public function test_quien_llega_sin_sesion_entra_a_un_desafio_como_invitado_y_ve_el_objetivo_en_la_mesa(): void
    {
        $this->post('/desafios/parda-la-primera')->assertRedirect(route('mesa'));

        $this->assertAuthenticated();
        $this->assertTrue($this->app['auth']->user()->esInvitado());

        $partida = Partida::query()->sole();
        $this->assertSame('parda-la-primera', $partida->desafio);

        $mesa = $this->get('/mesa')->assertOk()
            ->assertSee('objetivo-de-la-mesa')
            ->assertSee('Parda la primera')
            ->assertSee('La máquina salió con el ancho falso. Ganá la mano.')
            ->assertSee('Otros desafíos')
            // Un desafío no tiene revancha.
            ->assertDontSee('Jugar la revancha');

        $this->assertSame(['esMano' => true, 'escalon' => null], array_intersect_key($mesa->viewData('practica'), ['esMano' => 1, 'escalon' => 1]));
        $this->assertSame(route('desafios.jugar', 'parda-la-primera'), $mesa->viewData('practica')['reintentar']);
    }

    public function test_una_mano_de_la_escalera_o_una_clave_inventada_no_se_abren_como_desafio(): void
    {
        $jugador = Jugador::factory()->create();

        // "con-la-justa" existe, pero es el escalón 2: por acá no se llega.
        $this->actingAs($jugador)->post('/desafios/con-la-justa')->assertNotFound();
        $this->actingAs($jugador)->post('/desafios/uno-que-no-existe')->assertNotFound();
        $this->actingAs($jugador)->post('/desafios/CON_MAYUSCULAS')->assertNotFound();

        $this->assertSame(0, Partida::query()->count());
    }

    public function test_superar_un_desafio_lo_decide_el_servidor_y_queda_en_la_lista_y_en_el_historial(): void
    {
        $jugador = Jugador::factory()->create();
        $ficha = Desafios::de('parda-la-primera');
        $resultado = $this->resolver($ficha);

        // Primero mal, y diciéndole al servidor que salió bien: lo que mande el navegador no cuenta.
        $this->actingAs($jugador)->post('/desafios/parda-la-primera');
        $paso = $this->jugarPorLasRutas($resultado['falla'], ['superado' => true, 'ganador' => 0]);

        $this->assertFalse($paso['superado']);
        $this->get('/mesa')->assertRedirect(route('desafios'))->assertSessionHas('aviso', 'El desafío no salió esta vez.');
        $this->get('/desafios')->assertOk()->assertSee('0</span>', false);

        // Después bien.
        $this->post('/desafios/parda-la-primera')->assertRedirect(route('mesa'));
        $paso = $this->jugarPorLasRutas($resultado['cumple']);

        $this->assertTrue($paso['superado']);
        $this->assertSame('por_repartir', $paso['fase']);
        $this->get('/mesa')->assertRedirect(route('desafios'))->assertSessionHas('aviso', 'Desafío superado.');
        $this->get('/desafios')->assertOk()->assertSee('1</span>', false)->assertSee('Jugar de nuevo');

        // No fue al ranking, y el historial dice qué fue y cómo salió cada intento.
        $this->assertSame(0, Resultado::query()->count());
        $this->get('/historial')->assertOk()
            ->assertSee('Desafío: Parda la primera.')
            ->assertSee('Superado')
            ->assertSee('No salió');
    }

    public function test_dejar_un_desafio_vuelve_a_la_lista(): void
    {
        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->post('/desafios/contraflor');
        $this->post('/mesa/abandonar')->assertRedirect(route('desafios'))->assertSessionHas('aviso', 'Dejaste el desafío.');

        $this->assertSame(Partida::ABANDONADA, Partida::query()->sole()->estado);
        $this->assertSame([], (new Progresos)->superados($jugador));
    }

    public function test_con_una_partida_sin_terminar_un_desafio_lleva_a_esa_partida(): void
    {
        $jugador = Jugador::factory()->create();
        $comun = $this->app->make(Mesa::class)->abrir($jugador, Nivel::Facil);

        $this->actingAs($jugador)->post('/desafios/contraflor')->assertRedirect(route('mesa'));
        $this->post('/escalera/1')->assertRedirect(route('mesa'));

        $this->assertTrue(Partida::query()->sole()->is($comun));
        $this->get('/desafios')->assertOk()->assertSee('Seguir en la mesa')->assertDontSee('action="'.route('desafios.jugar', 'contraflor').'"', false);
        $this->get('/modos')->assertOk()->assertSee('Tenés una partida sin terminar');
    }

    public function test_la_escalera_se_mira_sin_sesion_con_un_solo_escalon_abierto(): void
    {
        $html = $this->get('/escalera')->assertOk()
            ->assertSee('Escalera del club')
            ->assertSee('Pichón Ibarra')
            ->assertSee('Jugar el escalón')
            // Lo que hay en los escalones cerrados no se cuenta: están boca abajo.
            ->assertDontSee('Don Anselmo')
            ->assertDontSee('Vale cuatro')
            ->getContent();

        $this->assertSame(8, substr_count($html, 'class="escalon"'));
        $this->assertSame(1, substr_count($html, 'data-estado="toca"'));
        $this->assertSame(7, substr_count($html, 'data-estado="cerrado"'));
        $this->assertSame(1, preg_match_all('/<form[^>]*action="[^"]*\/escalera\/\d+"/', $html));
        $this->assertGuest();
    }

    public function test_un_escalon_cerrado_no_se_abre_tampoco_pidiendolo_a_mano(): void
    {
        // Sin sesión: no se crea ni el invitado.
        $this->post('/escalera/2')->assertRedirect(route('escalera'))->assertSessionHas('aviso', 'Ese escalón todavía está cerrado: primero pasá el anterior.');
        $this->assertGuest();
        $this->assertSame(0, Jugador::query()->count());

        // Con sesión, y habiendo pasado solo el primero: el tercero y el último siguen cerrados.
        $jugador = Jugador::factory()->create();
        Progreso::query()->create(['jugador_id' => $jugador->id, 'clave' => Progresos::claveDeEscalon(1)])->forceFill(['superado_en' => now()])->save();

        $this->actingAs($jugador)->post('/escalera/3')->assertRedirect(route('escalera'));
        $this->post('/escalera/8')->assertRedirect(route('escalera'));
        $this->post('/escalera/9')->assertNotFound();
        $this->post('/escalera/0')->assertNotFound();

        $this->assertSame(0, Partida::query()->count());

        // El segundo, que se abrió al pasar el primero, sí.
        $this->post('/escalera/2')->assertRedirect(route('mesa'));
        $this->assertSame(['con-la-justa', 2], [Partida::query()->sole()->desafio, Partida::query()->sole()->escalon]);
    }

    public function test_el_primer_escalon_es_una_partida_corta_contra_alguien_del_club(): void
    {
        $this->post('/escalera/1')->assertRedirect(route('mesa'));

        $partida = Partida::query()->sole();

        $this->assertSame([1, null, Escalera::PUNTOS, Nivel::Facil], [$partida->escalon, $partida->desafio, $partida->puntos, $partida->nivel_bot]);

        $this->get('/mesa')->assertOk()
            ->assertSee('Escalón 1')
            ->assertSee('Pichón Ibarra')
            ->assertSee('Ver la escalera')
            ->assertDontSee('Jugar la revancha')
            // No es una mano con objetivo: no hay ventanita de objetivo.
            ->assertDontSee('objetivo-de-la-mesa');
    }

    public function test_pasar_un_escalon_abre_el_siguiente_y_perderlo_no(): void
    {
        $jugador = Jugador::factory()->create();

        // Se pierde el primero: sigue siendo el que toca.
        $this->contraUnBotDePrueba(seVa: false);
        $this->actingAs($jugador)->post('/escalera/1')->assertRedirect(route('mesa'));
        $perdida = $this->jugarLaPartida($jugador, gana: false);

        $this->assertSame(Mesa::BOT, $perdida->ganador);
        $this->assertSame(1, (new Progresos)->escalonActual($jugador));
        $this->post('/escalera/2')->assertRedirect(route('escalera'));

        // Se gana: queda pasado y se abre el segundo.
        $this->contraUnBotDePrueba(seVa: true);
        $this->post('/escalera/1')->assertRedirect(route('mesa'));
        $ganada = $this->jugarLaPartida($jugador, gana: true);

        $this->assertSame([Partida::TERMINADA, Mesa::JUGADOR, 15], [$ganada->estado, $ganada->ganador, $this->app->make(Mesa::class)->reconstruir($ganada)->tanteo()[0]]);
        $this->assertSame(2, (new Progresos)->escalonActual($jugador));
        $this->get('/mesa')->assertRedirect(route('escalera'))->assertSessionHas('aviso', 'Pasaste el escalón 1.');

        $html = $this->get('/escalera')->assertOk()->assertSee('Con la justa')->getContent();

        $this->assertSame([1, 1, 6], [substr_count($html, 'data-estado="pasado"'), substr_count($html, 'data-estado="toca"'), substr_count($html, 'data-estado="cerrado"')]);

        // El segundo es una mano con objetivo: se juega contra el bot de siempre, y cuenta para su escalón.
        $this->app->forgetInstance(Mesa::class);
        $this->post('/escalera/2')->assertRedirect(route('mesa'));
        $this->get('/mesa')->assertOk()->assertSee('objetivo-de-la-mesa')->assertSee('Con la justa')->assertSee('Subir al escalón 3');

        // La mano la cierra el bot con su última carta: el resultado se le pregunta a la mesa, como hace la pantalla.
        $this->jugarPorLasRutas($this->resolver(Desafios::de('con-la-justa'))['cumple']);
        $mano = Partida::query()->latest('id')->first();

        $this->assertTrue($this->getJson('/mesa/estado?partida='.$mano->id.'&desde=0')->assertOk()->collect('pasos')->last()['superado']);
        $this->assertSame(3, (new Progresos)->escalonActual($jugador));
        $this->assertEqualsCanonicalizing(['escalon:1', 'escalon:2'], (new Progresos)->superados($jugador));

        // Nada de esto fue al ranking, y el historial dice de qué escalón fue cada cosa.
        $this->assertSame(0, Resultado::query()->count());
        $this->get('/historial')->assertOk()
            ->assertSee('Escalera, escalón 2: Con la justa.')
            ->assertSee('Escalera, escalón 1.')
            ->assertSee('Contra Pichón Ibarra');
    }
}
