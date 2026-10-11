<?php

namespace Tests\Feature;

use App\Avisos\Avisos;
use App\Avisos\Cartero;
use App\Avisos\Claves;
use App\Avisos\Presencia;
use App\Jobs\AvisarAlJugador;
use App\Juego\Mesa;
use App\Models\ClaveDeAviso;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\SuscripcionDeAviso;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\Fase;
use App\Motor\TipoDeAccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

/**
 * Los avisos que llegan al celular: quién puede anotarse, a quién se le avisa y cuándo, y qué dice el aviso.
 * Nada de acá manda un aviso de verdad: en lugar del cartero va uno que anota lo que le dan.
 */
class AvisosTest extends TestCase
{
    use RefreshDatabase;

    private const DESTINO = 'https://fcm.googleapis.com/fcm/send/abc123';

    // Anotarse y darse de baja

    public function test_un_jugador_anota_su_navegador_tambien_si_juega_sin_cuenta(): void
    {
        foreach ([Jugador::factory()->create(), Jugador::factory()->invitado()->create()] as $numero => $jugador) {
            $destino = self::DESTINO.$numero;

            $this->actingAs($jugador)->postJson('/avisos', ['destino' => $destino, 'p256dh' => 'BPk_clave-publica', 'auth' => 'secreto_1'])
                ->assertCreated();

            $suscripcion = SuscripcionDeAviso::query()->where('jugador_id', $jugador->id)->sole();

            $this->assertSame([$destino, 'BPk_clave-publica', 'secreto_1'], [$suscripcion->destino, $suscripcion->p256dh, $suscripcion->auth]);
        }
    }

    public function test_solo_se_anota_una_direccion_de_un_servicio_de_avisos_conocido(): void
    {
        $jugador = Jugador::factory()->create();

        // El sitio le va a mandar pedidos a esa dirección: no puede ser la que a alguien se le ocurra.
        $malas = [
            'https://ejemplo.com/avisos',
            'http://fcm.googleapis.com/fcm/send/abc',
            'https://fcm.googleapis.com@ejemplo.com/abc',
            'https://fcm.googleapis.com.ejemplo.com/abc',
            'https://malofcm.googleapis.com/abc',
            'https://fcm.googleapis.com:8443/abc',
            'https://127.0.0.1/abc',
            'no es una dirección',
        ];

        foreach ($malas as $destino) {
            $this->actingAs($jugador)->postJson('/avisos', ['destino' => $destino, 'p256dh' => 'clave', 'auth' => 'secreto'])->assertStatus(422);
            $this->assertFalse(Avisos::esDeUnServicioConocido($destino), $destino);
        }

        $buenas = [
            'https://fcm.googleapis.com/fcm/send/abc',
            'https://updates.push.services.mozilla.com/wpush/v2/abc',
            'https://db5p.notify.windows.com/w/?token=abc',
            'https://web.push.apple.com/abc',
        ];

        foreach ($buenas as $destino) {
            $this->assertTrue(Avisos::esDeUnServicioConocido($destino), $destino);
        }

        $this->assertSame(0, SuscripcionDeAviso::count());
    }

    public function test_sin_sesion_no_se_anota_nadie_y_las_claves_tienen_que_venir_bien_escritas(): void
    {
        $this->postJson('/avisos', ['destino' => self::DESTINO, 'p256dh' => 'clave', 'auth' => 'secreto'])->assertUnauthorized();
        $this->postJson('/avisos/vista', ['visible' => true])->assertUnauthorized();

        $jugador = Jugador::factory()->create();

        $this->actingAs($jugador)->postJson('/avisos', ['destino' => self::DESTINO, 'p256dh' => '<script>', 'auth' => 'secreto'])->assertStatus(422);
        $this->actingAs($jugador)->postJson('/avisos', ['destino' => self::DESTINO, 'p256dh' => 'clave'])->assertStatus(422);
        $this->assertSame(0, SuscripcionDeAviso::count());
    }

    public function test_un_navegador_es_de_un_solo_jugador_y_cada_jugador_tiene_un_tope_de_navegadores(): void
    {
        $avisos = $this->app->make(Avisos::class);
        [$uno, $otro] = Jugador::factory()->count(2)->create();

        // El mismo navegador, primero con una cuenta y después con otra: queda a nombre de la última.
        $avisos->suscribir($uno, self::DESTINO, 'clave', 'secreto');
        $avisos->suscribir($otro, self::DESTINO, 'clave', 'secreto');

        $this->assertSame($otro->id, SuscripcionDeAviso::sole()->jugador_id);

        foreach (range(1, Avisos::NAVEGADORES_POR_JUGADOR + 2) as $numero) {
            $this->travel(1)->seconds();
            $avisos->suscribir($uno, self::DESTINO.'-'.$numero, 'clave', 'secreto');
        }

        $deUno = SuscripcionDeAviso::query()->where('jugador_id', $uno->id)->pluck('destino');

        // Se quedan los más nuevos.
        $this->assertCount(Avisos::NAVEGADORES_POR_JUGADOR, $deUno);
        $this->assertNotContains(self::DESTINO.'-1', $deUno);
        $this->assertContains(self::DESTINO.'-'.(Avisos::NAVEGADORES_POR_JUGADOR + 2), $deUno);
    }

    public function test_la_baja_borra_la_suscripcion_y_nadie_borra_la_de_otro(): void
    {
        [$uno, $otro] = Jugador::factory()->count(2)->create();
        $this->app->make(Avisos::class)->suscribir($uno, self::DESTINO, 'clave', 'secreto');

        $this->actingAs($otro)->postJson('/avisos/baja', ['destino' => self::DESTINO])->assertOk();
        $this->assertSame(1, SuscripcionDeAviso::count());

        $this->actingAs($uno)->postJson('/avisos/baja', ['destino' => self::DESTINO])->assertOk()->assertExactJson(['avisos' => false]);
        $this->assertSame(0, SuscripcionDeAviso::count());
    }

    public function test_si_se_borra_un_invitado_se_van_sus_suscripciones(): void
    {
        $invitado = Jugador::factory()->invitado()->create();
        $this->app->make(Avisos::class)->suscribir($invitado, self::DESTINO, 'clave', 'secreto');

        $invitado->delete();

        $this->assertSame(0, SuscripcionDeAviso::count());
    }

    // Las claves

    public function test_las_claves_se_generan_una_vez_y_la_privada_queda_cifrada(): void
    {
        $claves = $this->app->make(Claves::class);

        $publica = $claves->publica();
        $privada = $claves->privada();

        // Siempre las mismas: si cambiaran, las suscripciones ya hechas dejarían de recibir avisos.
        $this->assertSame([$publica, $privada], [(new Claves)->publica(), (new Claves)->privada()]);
        $this->assertSame(1, ClaveDeAviso::count());
        $this->assertNotSame($publica, $privada);
        // Son las que pide el estándar: la librería que manda los avisos las acepta tal cual.
        $aceptadas = VAPID::validate(['subject' => 'https://vale-cuatro.test', 'publicKey' => $publica, 'privateKey' => $privada]);
        $this->assertSame([65, 32], [strlen($aceptadas['publicKey']), strlen($aceptadas['privateKey'])]);

        // En la base la privada no está a la vista, y no figura en la configuración.
        $guardada = DB::table('claves_de_aviso')->value('privada');

        $this->assertNotSame($privada, $guardada);
        $this->assertStringNotContainsString($privada, (string) $guardada);
        $this->assertStringNotContainsString($privada, print_r(config()->all(), true));
    }

    // A quién se le avisa

    public function test_el_aviso_se_encola_cuando_el_turno_pasa_a_alguien_suscripto_que_no_tiene_la_mesa_a_la_vista(): void
    {
        [$partida, $uno, $dos] = $this->partidaEntreDos();
        $this->app->make(Avisos::class)->suscribir($dos, self::DESTINO, 'clave', 'secreto');

        Queue::fake([AvisarAlJugador::class]);

        $evento = $this->jugarUnaCarta($partida, 0);

        // Le toca al otro, que pidió avisos y no está mirando: sale su aviso, con unos segundos de demora.
        Queue::assertPushed(AvisarAlJugador::class, 1);
        Queue::assertPushed(AvisarAlJugador::class, fn (AvisarAlJugador $trabajo) => $trabajo->jugadorId === $dos->id
            && $trabajo->partidaId === $partida->id
            && $trabajo->evento === $evento
            && $trabajo->aviso['titulo'] === 'Te toca jugar'
            && $trabajo->delay !== null);
    }

    public function test_no_se_encola_si_no_pidio_avisos_ni_si_tiene_la_mesa_a_la_vista(): void
    {
        // No pidió avisos (los pidió el otro, que es quien acaba de jugar).
        [$partida, $uno] = $this->partidaEntreDos();
        $this->app->make(Avisos::class)->suscribir($uno, self::DESTINO, 'clave', 'secreto');

        Queue::fake([AvisarAlJugador::class]);
        $this->jugarUnaCarta($partida, 0);
        Queue::assertNothingPushed();

        // Los pidió, pero tiene la mesa a la vista: la propia mesa lo avisa.
        [$otra, , $dos] = $this->partidaEntreDos();
        $this->app->make(Avisos::class)->suscribir($dos, self::DESTINO.'-otro', 'clave', 'secreto');
        $this->actingAs($dos)->postJson('/avisos/vista', ['visible' => true])->assertOk();

        $this->jugarUnaCarta($otra, 0);
        Queue::assertNothingPushed();

        // Tapó la mesa: ahora sí, apenas le vuelva a tocar.
        $this->actingAs($dos)->postJson('/avisos/vista', ['visible' => false])->assertOk();
        $this->jugarUnaCarta($otra, 1);

        // La baza la abre quien ganó la anterior: si fue el otro, juega y el turno vuelve.
        if ($this->app->make(Mesa::class)->vista($otra->fresh(), 0)['acciones'] !== []) {
            $this->jugarUnaCarta($otra, 0);
        }

        Queue::assertPushed(AvisarAlJugador::class, fn (AvisarAlJugador $trabajo) => $trabajo->jugadorId === $dos->id);
    }

    public function test_contra_el_bot_no_hay_avisos(): void
    {
        $jugador = Jugador::factory()->create();
        $this->app->make(Avisos::class)->suscribir($jugador, self::DESTINO, 'clave', 'secreto');

        Queue::fake([AvisarAlJugador::class]);

        $mesa = $this->app->make(Mesa::class);
        $partida = $mesa->abrir($jugador);
        $mesa->actuar($partida, Accion::de(TipoDeAccion::Mazo));

        Queue::assertNothingPushed();
    }

    public function test_a_quien_abrio_la_sala_se_le_avisa_que_alguien_se_sento_y_no_dos_veces(): void
    {
        $anfitrion = Jugador::factory()->create(['apodo' => 'El Zurdo']);
        $invitado = Jugador::factory()->invitado()->create(['apodo' => 'La Tana']);
        $mesa = $this->app->make(Mesa::class);
        $sala = $mesa->crearSala($anfitrion);

        $this->app->make(Avisos::class)->suscribir($anfitrion, self::DESTINO, 'clave', 'secreto');
        Queue::fake([AvisarAlJugador::class]);

        $mesa->sentarse($sala->codigo, $invitado);

        // Un solo aviso: el de la llegada. El primer reparto no suma un "te toca" aparte.
        Queue::assertPushed(AvisarAlJugador::class, 1);
        Queue::assertPushed(AvisarAlJugador::class, fn (AvisarAlJugador $trabajo) => $trabajo->jugadorId === $anfitrion->id
            && $trabajo->aviso['titulo'] === 'La Tana se sentó'
            && $trabajo->evento === null);
    }

    public function test_un_canto_se_avisa_como_canto_y_ningun_aviso_nombra_una_carta(): void
    {
        $avisos = $this->app->make(Avisos::class);
        $textos = [];

        // Varias partidas enteras entre dos personas que piden avisos, tocando cualquier botón válido.
        foreach ([11, 12, 13] as $semilla) {
            [$partida, $uno, $dos] = $this->partidaEntreDos();
            $avisos->suscribir($uno, self::DESTINO."-{$semilla}-uno", 'clave', 'secreto');
            $avisos->suscribir($dos, self::DESTINO."-{$semilla}-dos", 'clave', 'secreto');

            Queue::fake([AvisarAlJugador::class]);
            $this->jugarAlAzar($partida, Azar::deSemilla($semilla));

            foreach (Queue::pushed(AvisarAlJugador::class) as $trabajo) {
                $textos[] = $trabajo->aviso['titulo'].' | '.$trabajo->aviso['cuerpo'];
                $this->assertSame("partida-{$partida->id}", $trabajo->aviso['etiqueta']);
                $this->assertSame('/mesa', $trabajo->aviso['url']);
            }
        }

        $this->assertGreaterThan(30, count($textos));
        // Los turnos comunes y los cantos se dicen distinto.
        $this->assertNotEmpty(array_filter($textos, fn (string $texto) => str_starts_with($texto, 'Te toca jugar')));
        $this->assertNotEmpty(array_filter($textos, fn (string $texto) => preg_match('/ te cantó (truco|retruco|vale cuatro|envido|real envido|falta envido)/', $texto) === 1));

        // Un aviso se lee en la pantalla bloqueada: nunca dice una carta, ni escrita ni con su código.
        foreach ($textos as $texto) {
            $this->assertDoesNotMatchRegularExpression('/\d+\s*(de|-)\s*(espada|basto|oro|copa)/iu', $texto);
            $this->assertDoesNotMatchRegularExpression('/\b(ancho|siete|tres|dos|sota|caballo|rey)\b/iu', $texto);
        }
    }

    // Mandarlo

    public function test_al_mandarlo_llega_a_todos_los_navegadores_del_jugador_y_se_borra_el_que_ya_no_existe(): void
    {
        [$partida, , $dos] = $this->partidaEntreDos();
        $cartero = $this->carteroDeMentira([self::DESTINO.'-viejo' => Cartero::VENCIDA]);
        $avisos = $this->app->make(Avisos::class);

        $avisos->suscribir($dos, self::DESTINO.'-celular', 'clave', 'secreto');
        $avisos->suscribir($dos, self::DESTINO.'-viejo', 'clave', 'secreto');

        // En los tests la cola corre en el momento: se la frena para mandar el aviso a mano, una sola vez.
        Queue::fake([AvisarAlJugador::class]);

        $evento = $this->jugarUnaCarta($partida, 0);
        $aviso = ['titulo' => 'Te toca jugar', 'cuerpo' => 'Te esperan.', 'etiqueta' => "partida-{$partida->id}", 'url' => '/mesa'];

        (new AvisarAlJugador($dos->id, $aviso, $partida->id, $evento))->handle($avisos);

        $this->assertEqualsCanonicalizing([self::DESTINO.'-celular', self::DESTINO.'-viejo'], array_column($cartero->entregas, 0));
        $this->assertSame($aviso, $cartero->entregas[0][1]);
        // El navegador que ya no existe se da de baja solo.
        $this->assertSame([self::DESTINO.'-celular'], SuscripcionDeAviso::query()->pluck('destino')->all());
    }

    public function test_al_mandarlo_no_sale_si_ya_jugo_si_volvio_a_la_mesa_o_si_la_partida_se_cerro(): void
    {
        [$partida, $uno, $dos] = $this->partidaEntreDos();
        $cartero = $this->carteroDeMentira();
        $avisos = $this->app->make(Avisos::class);
        $avisos->suscribir($dos, self::DESTINO, 'clave', 'secreto');
        $aviso = ['titulo' => 'Te toca jugar', 'cuerpo' => 'Te esperan.', 'etiqueta' => 'partida', 'url' => '/mesa'];

        Queue::fake([AvisarAlJugador::class]);

        $evento = $this->jugarUnaCarta($partida, 0);

        // Volvió a la mesa en esos segundos.
        $this->app->make(Presencia::class)->marcar($dos->id, true);
        $this->assertSame(0, $avisos->entregar($dos->id, $aviso, $partida->id, $evento));

        // La tapó, pero jugó: el aviso quedó viejo.
        $this->app->make(Presencia::class)->marcar($dos->id, false);
        $this->jugarUnaCarta($partida, 1);
        $this->assertSame(0, $avisos->entregar($dos->id, $aviso, $partida->id, $evento));

        // La partida se cerró.
        $this->app->make(Mesa::class)->abandonar($partida->fresh(), 0);
        $this->assertSame(0, $avisos->entregar($dos->id, $aviso, $partida->id, null));

        $this->assertSame([], $cartero->entregas);
    }

    public function test_la_marca_de_que_esta_mirando_se_vence_sola(): void
    {
        $presencia = $this->app->make(Presencia::class);

        $presencia->marcar(7, true);
        $this->assertTrue($presencia->aLaVista(7));
        $this->assertFalse($presencia->aLaVista(8));

        // Cerró la pestaña sin llegar a avisar: a los pocos segundos ya no cuenta como presente.
        $this->travel(Presencia::SEGUNDOS + 1)->seconds();
        $this->assertFalse($presencia->aLaVista(7));
    }

    /**
     * Una partida entre dos personas armada por el camino real: una abre la sala y la otra se sienta.
     * El asiento 0 es mano, para saber a quién le toca.
     *
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaEntreDos(): array
    {
        // Con apodos fijos: los avisos los nombran, y un apodo sorteado podría parecerse a una carta.
        $numero = Jugador::count();
        $uno = Jugador::factory()->create(['apodo' => "El Zurdo {$numero}"]);
        $dos = Jugador::factory()->invitado()->create(['apodo' => "La Tana {$numero}"]);
        $mesa = $this->app->make(Mesa::class);

        // Hasta que le toque ser mano al asiento 0: el sorteo es de verdad.
        do {
            $sala = $mesa->crearSala($uno);
            $partida = $mesa->sentarse($sala->codigo, $dos)->fresh();

            if ($partida->primer_mano !== Mesa::JUGADOR) {
                $mesa->abandonar($partida, 0);
            }
        } while ($partida->primer_mano !== Mesa::JUGADOR);

        return [$partida, $uno, $dos];
    }

    /**
     * Ese asiento tira la primera carta que pueda. Devuelve el número del evento que quedó guardado.
     */
    private function jugarUnaCarta(Partida $partida, int $asiento): int
    {
        $mesa = $this->app->make(Mesa::class);
        $carta = collect($mesa->vista($partida->fresh(), $asiento)['acciones'])->firstWhere('tipo', TipoDeAccion::Jugar->value);

        $this->assertNotNull($carta, "Al asiento {$asiento} no le toca tirar una carta.");

        $mesa->actuar($partida->fresh(), Accion::desdeArray($carta), $asiento);

        return (int) $partida->eventos()->max('numero');
    }

    /**
     * Juega la partida hasta el final tocando cualquier botón válido de quien tenga el turno.
     */
    private function jugarAlAzar(Partida $partida, Azar $azar): void
    {
        $mesa = $this->app->make(Mesa::class);

        for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
            $this->assertLessThan(3000, $pedidos, 'La partida no termina.');

            $actual = $partida->fresh();

            if ($mesa->vista($actual, 0)['fase'] === Fase::PorRepartir->value) {
                $mesa->repartir($actual, 0);

                continue;
            }

            foreach ([0, 1] as $asiento) {
                $acciones = $mesa->vista($actual, $asiento)['acciones'];

                if ($acciones !== []) {
                    $mesa->actuar($actual, Accion::desdeArray($acciones[$azar->entero(0, count($acciones) - 1)]), $asiento);

                    break;
                }
            }
        }
    }

    /**
     * Un cartero que no manda nada: anota a qué dirección le dieron cada aviso, y contesta lo que se le diga.
     *
     * @param  array<string, string>  $respuestas  Qué contesta para cada dirección; para las demás, que lo entregó.
     */
    private function carteroDeMentira(array $respuestas = []): Cartero
    {
        $cartero = new class($respuestas) implements Cartero
        {
            /** @var list<array{0: string, 1: array<string, string>}> */
            public array $entregas = [];

            /** @param  array<string, string>  $respuestas */
            public function __construct(private readonly array $respuestas) {}

            public function entregar(SuscripcionDeAviso $suscripcion, array $aviso): string
            {
                $this->entregas[] = [$suscripcion->destino, $aviso];

                return $this->respuestas[$suscripcion->destino] ?? Cartero::ENTREGADO;
            }
        };

        $this->app->instance(Cartero::class, $cartero);

        return $cartero;
    }
}
