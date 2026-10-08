<?php

namespace Tests\Feature;

use App\Events\PartidaActualizada;
use App\Juego\Mesa;
use App\Models\Jugador;
use App\Models\Partida;
use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\TipoDeAccion;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

/**
 * El tiempo real de una partida entre personas. Por el WebSocket no viajan cartas: solo un aviso con el
 * número del último evento, por un canal privado al que entran las dos personas de la partida y nadie más.
 */
class TiempoRealTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_aviso_lleva_solo_el_numero_del_evento_en_un_canal_privado(): void
    {
        $aviso = new PartidaActualizada(7, 12);

        $this->assertSame('partida.actualizada', $aviso->broadcastAs());
        $this->assertSame(['evento' => 12], $aviso->broadcastWith());
        $this->assertCount(1, $aviso->broadcastOn());
        $this->assertInstanceOf(PrivateChannel::class, $aviso->broadcastOn()[0]);
        $this->assertSame('private-partida.7', $aviso->broadcastOn()[0]->name);
    }

    public function test_en_una_partida_entera_cada_evento_guardado_avisa_una_vez_y_ningun_aviso_trae_cartas(): void
    {
        Event::fake([PartidaActualizada::class]);

        $mesa = $this->mesa();
        $azar = Azar::deSemilla(41);
        $avisos = 0;
        $guardados = 0;

        // Una partida puede cerrarse enseguida: se juegan las que hagan falta hasta revisar una cantidad fija.
        for ($partidas = 0; $avisos < 150; $partidas++) {
            $this->assertLessThan(40, $partidas, 'No se llegó a revisar lo suficiente.');

            [$partida] = $this->partidaEnCurso();

            for ($pedidos = 0; $partida->fresh()->enCurso(); $pedidos++) {
                $this->assertLessThan(3000, $pedidos, 'La partida no termina.');
                $this->unPedidoAlAzar($mesa, $partida, $azar);
            }

            $guardados += $partida->eventos()->count();
            $avisos = Event::dispatched(PartidaActualizada::class)->count();
        }

        // Un aviso por cada evento guardado, ni uno más ni uno menos.
        $this->assertSame($guardados, $avisos);

        Event::dispatched(PartidaActualizada::class)->each(function (array $despacho) {
            /** @var PartidaActualizada $aviso */
            [$aviso] = $despacho;

            $emitido = json_encode([
                'nombre' => $aviso->broadcastAs(),
                'datos' => $aviso->broadcastWith(),
                'canales' => array_map(fn (PrivateChannel $canal) => $canal->name, $aviso->broadcastOn()),
            ]);

            $this->assertSame(0, preg_match('/\b(?:1[0-2]|[1-7])-(?:espada|basto|oro|copa)\b/', $emitido), "Un aviso trae una carta: {$emitido}");
            $this->assertSame(['evento'], array_keys($aviso->broadcastWith()));
            $this->assertIsInt($aviso->broadcastWith()['evento']);
        });
    }

    public function test_los_avisos_de_una_partida_van_numerados_y_al_canal_de_esa_partida(): void
    {
        Event::fake([PartidaActualizada::class]);

        [$partida] = $this->partidaEnCurso();
        $this->unPedidoAlAzar($this->mesa(), $partida, Azar::deSemilla(3));
        $this->unPedidoAlAzar($this->mesa(), $partida, Azar::deSemilla(4));

        $numeros = [];

        Event::dispatched(PartidaActualizada::class)->each(function (array $despacho) use ($partida, &$numeros) {
            $this->assertSame("private-partida.{$partida->id}", $despacho[0]->broadcastOn()[0]->name);
            $numeros[] = $despacho[0]->evento;
        });

        $this->assertSame($partida->eventos()->pluck('numero')->all(), $numeros);
    }

    public function test_la_partida_contra_el_bot_no_emite_avisos(): void
    {
        Event::fake([PartidaActualizada::class]);

        $partida = $this->mesa()->abrir(Jugador::factory()->invitado()->create());
        $azar = Azar::deSemilla(6);

        for ($i = 0; $i < 20 && $partida->fresh()->enCurso(); $i++) {
            $this->unPedidoContraElBot($this->mesa(), $partida, $azar);
        }

        Event::assertNotDispatched(PartidaActualizada::class);
    }

    public function test_sentarse_avisa_a_quien_espera_en_la_sala(): void
    {
        Event::fake([PartidaActualizada::class]);

        $sala = $this->mesa()->crearSala(Jugador::factory()->invitado()->create());

        // Mientras nadie se sienta no hay nada que avisar.
        Event::assertNotDispatched(PartidaActualizada::class);

        $this->mesa()->sentarse($sala->codigo, Jugador::factory()->invitado()->create());

        Event::assertDispatchedTimes(PartidaActualizada::class, 1);
        Event::assertDispatched(PartidaActualizada::class, fn (PartidaActualizada $aviso) => $aviso->partida === $sala->id && $aviso->evento === 1);
    }

    public function test_el_aviso_sale_recien_cuando_se_confirma_la_transaccion(): void
    {
        $recibidos = [];
        Event::listen(PartidaActualizada::class, function (PartidaActualizada $aviso) use (&$recibidos) {
            $recibidos[] = $aviso->evento;
        });

        // Si se deshace lo guardado, nadie tiene que enterarse de algo que no pasó.
        try {
            DB::transaction(function () {
                PartidaActualizada::avisar(1, 1);

                throw new RuntimeException('Se deshace todo.');
            });
        } catch (RuntimeException) {
            // Esperado.
        }

        $this->assertSame([], $recibidos, 'Salió un aviso de una transacción que se deshizo.');

        // Si se confirma, sale, y recién después de confirmar.
        DB::transaction(function () use (&$recibidos) {
            PartidaActualizada::avisar(1, 2);

            $this->assertSame([], $recibidos, 'Salió antes de confirmar.');
        });

        $this->assertSame([2], $recibidos);
    }

    public function test_si_el_tiempo_real_falla_la_jugada_se_guarda_igual_y_el_error_queda_anotado(): void
    {
        [$partida] = $this->partidaEnCurso();
        $mesa = $this->mesa();

        // Un emisor que siempre falla, como Reverb caído.
        Broadcast::extend('caido', fn () => new class extends Broadcaster
        {
            public function auth($request): mixed
            {
                return null;
            }

            public function validAuthenticationResponse($request, $result): mixed
            {
                return null;
            }

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new RuntimeException('Reverb caído');
            }
        });
        config(['broadcasting.default' => 'caido', 'broadcasting.connections.caido' => ['driver' => 'caido']]);
        Exceptions::fake();

        $antes = $partida->eventos()->count();
        $asiento = $mesa->vista($partida, 0)['acciones'] !== [] ? 0 : 1;

        // La jugada entra y quien la hizo recibe su respuesta, aunque el aviso no haya podido salir.
        $pasos = $mesa->actuar($partida, Accion::de(TipoDeAccion::Truco), $asiento);

        $this->assertCount(1, $pasos);
        $this->assertSame($antes + 1, $partida->eventos()->count());
        Exceptions::assertReported(fn (RuntimeException $falla) => $falla->getMessage() === 'Reverb caído');
    }

    public function test_al_canal_de_la_partida_entran_sus_dos_personas_y_nadie_mas(): void
    {
        // Primero se arma la partida y recién después se cambia el emisor: sentarse manda un aviso.
        [$partida, $uno, $dos] = $this->partidaEnCurso();
        $tercero = Jugador::factory()->invitado()->create();
        $this->conReverbDePrueba();

        foreach ([$uno, $dos] as $participante) {
            $this->actingAs($participante)->post('/broadcasting/auth', $this->pedido($partida))->assertOk()->assertJsonStructure(['auth']);
        }

        $this->actingAs($tercero)->post('/broadcasting/auth', $this->pedido($partida))->assertForbidden();
    }

    public function test_sin_sesion_no_se_entra_a_ningun_canal(): void
    {
        [$partida] = $this->partidaEnCurso();
        $this->conReverbDePrueba();

        $this->post('/broadcasting/auth', $this->pedido($partida))->assertForbidden();
    }

    public function test_quien_abrio_la_sala_ya_puede_escuchar_y_un_extrano_no(): void
    {
        $this->conReverbDePrueba();

        $creador = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($creador);

        $this->actingAs($creador)->post('/broadcasting/auth', $this->pedido($sala))->assertOk();
        $this->actingAs(Jugador::factory()->invitado()->create())->post('/broadcasting/auth', $this->pedido($sala))->assertForbidden();
    }

    public function test_el_canal_de_una_partida_contra_el_bot_no_admite_a_nadie(): void
    {
        $this->conReverbDePrueba();

        $jugador = Jugador::factory()->invitado()->create();
        $contraElBot = $this->mesa()->abrir($jugador);

        $this->actingAs($jugador)->post('/broadcasting/auth', $this->pedido($contraElBot))->assertForbidden();
    }

    public function test_un_canal_de_una_partida_que_no_existe_no_admite_a_nadie(): void
    {
        $this->conReverbDePrueba();

        $this->actingAs(Jugador::factory()->invitado()->create())
            ->post('/broadcasting/auth', ['channel_name' => 'private-partida.99999', 'socket_id' => '1234.5678'])
            ->assertForbidden();
    }

    /**
     * Claves de mentira para que el servidor pueda firmar la autorización de un canal privado.
     */
    private function conReverbDePrueba(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'clave-de-prueba',
            'broadcasting.connections.reverb.secret' => 'secreto-de-prueba',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);

        Broadcast::purge('reverb');

        // Los canales se registran en el emisor que está activo cuando arranca la aplicación (acá, el nulo):
        // al cambiarlo hay que volver a registrarlos. En el sitio el emisor no cambia nunca.
        require base_path('routes/channels.php');
    }

    /**
     * @return array{channel_name: string, socket_id: string}
     */
    private function pedido(Partida $partida): array
    {
        return ['channel_name' => "private-partida.{$partida->id}", 'socket_id' => '1234.5678'];
    }

    private function mesa(): Mesa
    {
        return $this->app->make(Mesa::class);
    }

    /**
     * Una partida entre dos personas ya en curso, con la primera mano repartida.
     *
     * @return array{0: Partida, 1: Jugador, 2: Jugador}
     */
    private function partidaEnCurso(): array
    {
        $uno = Jugador::factory()->invitado()->create();
        $dos = Jugador::factory()->invitado()->create();
        $sala = $this->mesa()->crearSala($uno);
        $partida = $this->mesa()->sentarse($sala->codigo, $dos);

        return [$partida, $uno, $dos];
    }

    /**
     * Hace lo que haría quien tiene el turno: una acción al azar, o repartir si la mano está cerrada.
     */
    private function unPedidoAlAzar(Mesa $mesa, Partida $partida, Azar $azar): void
    {
        foreach ([0, 1] as $asiento) {
            $acciones = $mesa->vista($partida->fresh(), $asiento)['acciones'];

            if ($acciones !== []) {
                $mesa->actuar($partida, Accion::desdeArray($acciones[$azar->entero(0, count($acciones) - 1)]), $asiento);

                return;
            }
        }

        $mesa->repartir($partida, 0);
    }

    private function unPedidoContraElBot(Mesa $mesa, Partida $partida, Azar $azar): void
    {
        $vista = $mesa->vista($partida->fresh());

        if ($vista['acciones'] === []) {
            $mesa->repartir($partida);

            return;
        }

        $mesa->actuar($partida, Accion::desdeArray($vista['acciones'][$azar->entero(0, count($vista['acciones']) - 1)]));
    }
}
