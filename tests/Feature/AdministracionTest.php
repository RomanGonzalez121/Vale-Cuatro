<?php

namespace Tests\Feature;

use App\Administracion\Acciones;
use App\Administracion\Panel;
use App\Jobs\TurnoDelBot;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Models\AccionDeAdministracion;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use App\Models\Serie;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * El panel de administración: quién entra, qué muestra y qué deja hacer.
 */
class AdministracionTest extends TestCase
{
    use JugandoPartidas, PartidasCortas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El bot y los plazos quedan en la cola sin correr: las partidas se quedan donde el test las deja.
        Queue::fake();
    }

    // Quién entra

    public function test_el_panel_no_existe_para_nadie_que_no_administre_el_sitio(): void
    {
        $partida = $this->partidaQuietaContraElBot();
        $fallido = $this->trabajoFallido();
        $otro = Jugador::factory()->create(['apodo' => 'Tano']);

        $rutas = [
            ['get', '/administracion'],
            ['post', "/administracion/partidas/{$partida->id}/cerrar"],
            ['post', "/administracion/trabajos/{$fallido}/reintentar"],
            ['post', "/administracion/trabajos/{$fallido}/descartar"],
            ['post', '/administracion/limpieza'],
            ['post', "/administracion/jugadores/{$otro->id}/ocultar-apodo"],
        ];

        $visitantes = [
            'sin sesión' => null,
            'invitado' => Jugador::factory()->invitado()->create(),
            'jugador con cuenta' => Jugador::factory()->create(),
        ];

        foreach ($visitantes as $quien => $visitante) {
            foreach ($rutas as [$metodo, $ruta]) {
                $pedido = $visitante === null ? $this : $this->actingAs($visitante);

                // Lo mismo que una página que no está: ni "prohibido" ni una vuelta al ingreso.
                $pedido->{$metodo}($ruta)->assertNotFound();
            }

            auth()->logout();
        }

        // Nada cambió.
        $this->assertTrue($partida->fresh()->enCurso());
        $this->assertSame('Tano', $otro->fresh()->apodo);
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertSame(0, AccionDeAdministracion::query()->count());
    }

    public function test_quien_administra_el_sitio_entra_al_panel(): void
    {
        $this->actingAs($this->administrador())->get('/administracion')
            ->assertOk()
            ->assertSee('Administración')
            ->assertSee('No hay partidas en juego.')
            ->assertSee('Ninguna quedó sin movimiento.')
            ->assertSee('Ningún trabajo falló.')
            ->assertSee('No hay nada para limpiar.')
            ->assertSee('Todavía no hay nada anotado.');
    }

    public function test_al_ingresar_quien_administra_va_a_su_panel_y_los_demas_a_la_portada(): void
    {
        $administrador = $this->administrador();
        $jugador = Jugador::factory()->create();

        $this->post('/ingresar', ['email' => $administrador->email, 'password' => 'password'])->assertRedirect(route('administracion'));
        $this->post('/salir');
        $this->post('/ingresar', ['email' => $jugador->email, 'password' => 'password'])->assertRedirect('/');
    }

    public function test_solo_quien_administra_ve_el_link_al_panel(): void
    {
        $this->get('/ranking')->assertOk()->assertDontSee('Administración');
        $this->actingAs(Jugador::factory()->create())->get('/ranking')->assertOk()->assertDontSee('Administración');
        $this->actingAs($this->administrador())->get('/ranking')->assertOk()->assertSee(route('administracion'));
    }

    public function test_ninguna_pantalla_otorga_el_rol(): void
    {
        // Ni al registrarse ni al cambiar el apodo, aunque el pedido lo mande a mano.
        $this->post('/registro', ['apodo' => 'Vivo', 'email' => 'vivo@example.com', 'password' => 'una-clave-larga', 'password_confirmation' => 'una-clave-larga', 'es_administrador' => '1']);

        $registrado = Jugador::query()->where('email', 'vivo@example.com')->sole();

        $this->assertFalse($registrado->esAdministrador());

        $this->actingAs($registrado)->put('/perfil', ['apodo' => 'Mas vivo', 'es_administrador' => '1']);

        $this->assertSame('Mas vivo', $registrado->fresh()->apodo);
        $this->assertFalse($registrado->fresh()->esAdministrador());
        $this->actingAs($registrado)->get('/administracion')->assertNotFound();

        // Y la asignación en masa tampoco lo deja pasar.
        $this->assertFalse((new Jugador(['apodo' => 'Otro', 'es_administrador' => true]))->esAdministrador());
    }

    public function test_las_acciones_se_niegan_aunque_alguien_las_llame_sin_pasar_por_la_ruta(): void
    {
        $partida = $this->partidaQuietaContraElBot();

        try {
            $this->app->make(Acciones::class)->cerrarPartida(Jugador::factory()->create(), $partida);
            $this->fail('Una acción del panel tenía que negarse a quien no administra el sitio.');
        } catch (AuthorizationException) {
            $this->assertTrue($partida->fresh()->enCurso());
        }
    }

    // Qué muestra

    public function test_el_panel_no_muestra_ninguna_carta_de_una_partida_en_curso(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'El Zurdo']), Jugador::factory()->create(['apodo' => 'La Tana'])];
        $mesa = $this->laMesaDeVerdad();
        $entrePersonas = $mesa->sentarse($mesa->crearSala($uno)->codigo, $dos);
        $contraElBot = $mesa->abrir(Jugador::factory()->create(['apodo' => 'Pichón']), Nivel::Dificil);

        $respuesta = $this->actingAs($this->administrador())->get('/administracion')->assertOk()
            ->assertSee('El Zurdo contra La Tana')
            ->assertSee('Pichón contra Bot difícil')
            ->assertSee('Hay 2 partidas en juego.');

        // Cada renglón trae estos datos y ninguno más: ni manos, ni cartas jugadas, ni la vista de un asiento.
        $filas = $respuesta->viewData('partidas')['enJuego'];

        $this->assertCount(2, $filas);

        foreach ($filas as $fila) {
            $this->assertSame(['id', 'uno', 'otro', 'contraElBot', 'enSerie', 'tanteo', 'mano', 'movida', 'quieta'], array_keys($fila));
        }

        // Y ninguna de las doce cartas repartidas aparece en lo que recibe la pantalla.
        $recibido = json_encode($respuesta->original->getData(), JSON_THROW_ON_ERROR);

        foreach ([$entrePersonas, $contraElBot] as $partida) {
            $repartidas = collect($partida->eventos()->first()->datos['manos'])->flatten();

            $this->assertCount(6, $repartidas);

            foreach ($repartidas as $carta) {
                $this->assertStringNotContainsString('"'.$carta.'"', $recibido);
            }
        }
    }

    public function test_las_partidas_quietas_van_aparte_y_las_simuladas_no_figuran(): void
    {
        $quieta = $this->partidaQuietaContraElBot('Cacho');
        [$uno, $dos] = [Jugador::factory()->create(['apodo' => 'El Zurdo']), Jugador::factory()->create(['apodo' => 'La Tana'])];
        $this->partidaCorta($uno, $dos);

        $partidas = $this->app->make(Panel::class)->partidas();

        $this->assertSame([$quieta->id], array_column($partidas['quietas'], 'id'));
        $this->assertSame(['El Zurdo'], array_column($partidas['enJuego'], 'uno'));
        $this->assertSame([1, 1], [$partidas['cuantasQuietas'], $partidas['cuantasEnJuego']]);

        // Entre personas, diez minutos sin jugadas alcanzan.
        $this->travel(Mesa::MINUTOS_QUIETA_ENTRE_PERSONAS + 1)->minutes();

        $this->assertCount(2, $this->app->make(Panel::class)->partidas()['quietas']);

        $this->actingAs($this->administrador())->get('/administracion')->assertOk()
            ->assertSee('2 quedaron sin movimiento.')
            ->assertSee('Cacho contra Bot intermedio');
    }

    public function test_los_totales_cuentan_todas_las_partidas_aunque_las_listas_muestren_algunas(): void
    {
        // Más partidas quietas que las que entran en la lista, y después una que se está jugando.
        for ($cuantas = 0; $cuantas < Panel::PARTIDAS_A_LA_VISTA + 3; $cuantas++) {
            $this->laMesaDeVerdad()->abrir(Jugador::factory()->create());
        }

        $this->travel(Mesa::HORAS_QUIETA_CONTRA_EL_BOT + 1)->hours();
        $this->laMesaDeVerdad()->abrir(Jugador::factory()->create(['apodo' => 'Recien']));

        $partidas = $this->app->make(Panel::class)->partidas();

        $this->assertCount(Panel::PARTIDAS_A_LA_VISTA, $partidas['quietas']);
        $this->assertSame(Panel::PARTIDAS_A_LA_VISTA + 3, $partidas['cuantasQuietas']);
        // La que se juega está en su lista aunque haya muchas quietas más viejas que ella.
        $this->assertSame(['Recien'], array_column($partidas['enJuego'], 'uno'));
        $this->assertSame(1, $partidas['cuantasEnJuego']);

        $this->actingAs($this->administrador())->get('/administracion')->assertOk()
            ->assertSee('Hay 1 partida en juego.')
            ->assertSee((Panel::PARTIDAS_A_LA_VISTA + 3).' quedaron sin movimiento.')
            ->assertSee('Se muestran las '.Panel::PARTIDAS_A_LA_VISTA.' que hace más que están quietas, de '.(Panel::PARTIDAS_A_LA_VISTA + 3).'.');
    }

    public function test_una_partida_que_el_motor_ya_no_puede_leer_sale_igual_en_el_panel_y_se_puede_cerrar(): void
    {
        Exceptions::fake();

        $administrador = $this->administrador();
        $partida = $this->partidaQuietaContraElBot();
        // Su reparto quedó dañado: volver a pasarla por el motor falla.
        $partida->eventos()->first()->update(['datos' => ['manos' => [['carta-que-no-existe'], []]]]);

        $this->actingAs($administrador)->get('/administracion')->assertOk()
            ->assertSee('1 quedó sin movimiento.')
            ->assertSee('No se pudo leer cómo va.');

        $this->actingAs($administrador)->post("/administracion/partidas/{$partida->id}/cerrar")->assertSessionHas('hecho');

        $this->assertSame(Partida::CERRADA, $partida->fresh()->estado);
    }

    public function test_de_un_trabajo_fallido_se_ve_que_era_y_la_clase_del_error_pero_no_su_mensaje(): void
    {
        $this->trabajoFallido('insert into eventos_de_partida values ("1-espada", "7-oro")');

        $this->actingAs($this->administrador())->get('/administracion')->assertOk()
            ->assertSee('Falló 1 trabajo.')
            ->assertSee('TurnoDelBot')
            ->assertSee('con un error RuntimeException')
            ->assertDontSee('insert into')
            ->assertDontSee('1-espada');
    }

    // Qué deja hacer

    public function test_cerrar_una_partida_quieta_le_agrega_un_evento_y_no_borra_nada(): void
    {
        $administrador = $this->administrador();
        $partida = $this->partidaQuietaContraElBot();
        $jugador = $partida->jugador;
        $antes = $partida->eventos()->get()->map->only(['numero', 'tipo', 'asiento', 'datos'])->all();

        $this->actingAs($administrador)->post("/administracion/partidas/{$partida->id}/cerrar")
            ->assertRedirect(route('administracion'))
            ->assertSessionHas('hecho', "Partida {$partida->id} cerrada.");

        $partida->refresh();
        $despues = $partida->eventos()->get();

        // Todo lo jugado sigue ahí, igual, y al final quedó dicho por qué terminó.
        $this->assertSame($antes, $despues->take(count($antes))->map->only(['numero', 'tipo', 'asiento', 'datos'])->all());
        $this->assertCount(count($antes) + 1, $despues);
        $this->assertSame(EventoDePartida::CIERRE, $despues->last()->tipo);
        $this->assertNull($despues->last()->asiento);

        // Quedó cerrada, no la ganó nadie, y no cuenta para el ranking ni figura en el historial.
        $this->assertSame(Partida::CERRADA, $partida->estado);
        $this->assertNull($partida->ganador);
        $this->assertSame(0, Resultado::query()->count());
        $this->actingAs($jugador)->get('/historial')->assertOk()->assertDontSee('Ver de nuevo');

        // Su jugador ya puede empezar otra, y al volver a la mesa se entera de qué pasó.
        $this->assertNull($this->laMesaDeVerdad()->abiertaDe($jugador));
        $this->actingAs($jugador)->get('/mesa')->assertRedirect(route('modos'))
            ->assertSessionHas('aviso', 'Tu última partida se cerró porque había quedado sin movimiento. No la ganó nadie.');
    }

    public function test_una_partida_que_se_esta_jugando_no_se_cierra_desde_el_panel(): void
    {
        $partida = $this->laMesaDeVerdad()->abrir(Jugador::factory()->create());
        $eventos = $partida->eventos()->count();

        $this->actingAs($this->administrador())->post("/administracion/partidas/{$partida->id}/cerrar")
            ->assertRedirect(route('administracion'))
            ->assertSessionHas('aviso', 'Esa partida ya terminó o volvió a moverse: no se cerró.');

        $this->assertTrue($partida->fresh()->enCurso());
        $this->assertSame($eventos, $partida->eventos()->count());
        $this->assertSame(0, AccionDeAdministracion::query()->count());

        // Una que no existe, tampoco.
        $this->actingAs($this->administrador())->post('/administracion/partidas/999999/cerrar')->assertNotFound();
    }

    public function test_cerrar_una_partida_quieta_de_una_serie_cierra_la_serie_sin_ganador(): void
    {
        [$uno, $dos] = [Jugador::factory()->create(), Jugador::factory()->create(['apodo' => 'La Tana'])];
        $primera = $this->partidaCorta($uno, $dos, enSerie: true);

        $this->ganar($primera, 0);
        $segunda = $this->laQueSiguioA($primera);
        $this->travel(Mesa::MINUTOS_QUIETA_ENTRE_PERSONAS + 1)->minutes();

        $this->assertTrue($this->laMesaDeVerdad()->cerrarQuieta($segunda));

        $serie = Serie::query()->sole();

        // La serie no se borra (ya tenía una partida jugada) ni se reparte otra: queda cerrada, sin ganador.
        $this->assertTrue($serie->cerrada());
        $this->assertNull($serie->ganador);
        $this->assertNull($this->laQueSiguioA($segunda));
        $this->actingAs($uno)->get('/historial')->assertOk()->assertSee('Al mejor de tres contra La Tana')->assertSee('La serie quedó sin terminar.');
    }

    public function test_un_trabajo_fallido_se_reintenta_o_se_descarta(): void
    {
        // Acá hace falta la cola de verdad, guardada en la base: reintentar es volver a ponerle el trabajo.
        config(['queue.default' => 'database']);
        $this->app->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');

        $administrador = $this->administrador();
        $uno = $this->trabajoFallido();
        $otro = $this->trabajoFallido();

        $this->actingAs($administrador)->post("/administracion/trabajos/{$uno}/reintentar")
            ->assertRedirect(route('administracion'))
            ->assertSessionHas('hecho', 'El trabajo volvió a la cola.');

        // Salió de los fallidos y volvió a la cola.
        $this->assertSame([$otro], DB::table('failed_jobs')->pluck('uuid')->all());
        $this->assertSame(1, DB::table('jobs')->count());

        $this->actingAs($administrador)->post("/administracion/trabajos/{$otro}/descartar")
            ->assertRedirect(route('administracion'))
            ->assertSessionHas('hecho', 'Trabajo descartado.');

        // El descartado no vuelve a ningún lado.
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('jobs')->count());

        // Uno que ya no está no hace nada.
        $this->actingAs($administrador)->post("/administracion/trabajos/{$otro}/descartar")->assertSessionHas('aviso', 'Ese trabajo ya no está entre los fallidos.');
        $this->actingAs($administrador)->post("/administracion/trabajos/{$otro}/reintentar")->assertSessionHas('aviso', 'Ese trabajo ya no está entre los fallidos.');

        $this->assertSame(
            [AccionDeAdministracion::REINTENTAR_TRABAJO, AccionDeAdministracion::DESCARTAR_TRABAJO],
            AccionDeAdministracion::query()->orderBy('id')->pluck('accion')->all(),
        );
    }

    public function test_la_limpieza_cierra_las_salas_vencidas_y_borra_a_los_invitados_que_no_volvieron(): void
    {
        $vencida = $this->laMesaDeVerdad()->crearSala(Jugador::factory()->create());
        $seFue = Jugador::factory()->invitado()->create();

        $this->travel(Jugador::DIAS_DE_INVITADO + 1)->days();

        $nueva = $this->laMesaDeVerdad()->crearSala(Jugador::factory()->create());
        $recien = Jugador::factory()->invitado()->create();

        $this->actingAs($this->administrador())->get('/administracion')->assertOk()
            ->assertSee('La limpieza tiene 2 cosas para llevarse.')
            ->assertSee('Ahora hay 1 sala vencida y 1 invitado por borrar.');

        $this->actingAs($this->administrador())->post('/administracion/limpieza')
            ->assertRedirect(route('administracion'))
            ->assertSessionHas('hecho', 'Limpieza hecha: 1 sala cerrada y 1 invitado borrado.');

        $this->assertSame(Partida::ABANDONADA, $vencida->fresh()->estado);
        $this->assertTrue($nueva->fresh()->esperando());
        $this->assertNull(Jugador::query()->find($seFue->id));
        $this->assertNotNull(Jugador::query()->find($recien->id));
    }

    public function test_un_apodo_ocultado_deja_de_verse_en_todas_las_pantallas(): void
    {
        $administrador = $this->administrador();
        $grosero = Jugador::factory()->create(['apodo' => 'Malapalabra']);
        $rival = Jugador::factory()->create(['apodo' => 'La Tana']);
        $this->partidaEntrePersonas($grosero, $rival);
        $enCurso = $this->partidaCorta($grosero, $rival);

        // Antes se ve donde se ven los apodos: el ranking, el historial y la mesa del rival, y el panel.
        $pantallas = fn () => [
            $this->actingAs($rival)->get('/ranking')->assertOk()->getContent(),
            $this->actingAs($rival)->get('/historial')->assertOk()->getContent(),
            $this->actingAs($rival)->get('/mesa')->assertOk()->getContent(),
            $this->actingAs($administrador)->get('/administracion?apodo=mala')->assertOk()->getContent(),
        ];

        foreach ($pantallas() as $pantalla) {
            $this->assertStringContainsString('Malapalabra', $pantalla);
        }

        $this->actingAs($administrador)->post("/administracion/jugadores/{$grosero->id}/ocultar-apodo")->assertRedirect(route('administracion'));

        $nuevo = $grosero->fresh()->apodo;

        $this->assertMatchesRegularExpression('/^Jugador \d{5,}$/', $nuevo);
        $this->assertTrue($enCurso->fresh()->enCurso());

        foreach ([...array_slice($pantallas(), 0, 3), $this->actingAs($administrador)->get('/administracion?apodo=jugador')->getContent()] as $numero => $pantalla) {
            // En el cuaderno del panel queda anotado qué apodo se ocultó: ahí sí figura, y solo ahí.
            $sinCuaderno = Str::before($pantalla, 'id="cuaderno"');

            $this->assertStringNotContainsString('Malapalabra', $sinCuaderno, "Pantalla {$numero}");
            $this->assertStringContainsString($nuevo, $pantalla, "Pantalla {$numero}");
        }

        // Su dueño puede elegir otro.
        $this->actingAs($grosero->fresh())->put('/perfil', ['apodo' => 'Don Bueno'])->assertSessionHasNoErrors();
        $this->assertSame('Don Bueno', $grosero->fresh()->apodo);
    }

    public function test_no_se_oculta_el_apodo_de_un_jugador_de_ejemplo_ni_el_de_la_administracion(): void
    {
        $administrador = $this->administrador();
        $deEjemplo = Jugador::factory()->deEjemplo()->create(['apodo' => 'Don Anselmo']);

        foreach ([$deEjemplo, $administrador] as $intocable) {
            $apodo = $intocable->apodo;

            $this->actingAs($administrador)->post("/administracion/jugadores/{$intocable->id}/ocultar-apodo")
                ->assertSessionHas('aviso', 'Ese apodo no se puede ocultar.');

            $this->assertSame($apodo, $intocable->fresh()->apodo);
        }

        // Los de ejemplo ni siquiera salen en la búsqueda.
        $this->assertSame([], $this->app->make(Panel::class)->jugadores('Anselmo'));
        $this->assertSame(0, AccionDeAdministracion::query()->count());
    }

    public function test_la_busqueda_de_apodos_toma_el_texto_tal_cual(): void
    {
        Jugador::factory()->create(['apodo' => 'Tano_1']);
        Jugador::factory()->create(['apodo' => 'Tanos1']);
        Jugador::factory()->invitado()->create(['apodo' => 'Invitado 12345']);
        $panel = $this->app->make(Panel::class);

        // Un guion bajo o un porcentaje escritos no son comodines.
        $this->assertSame(['Tano_1'], array_column($panel->jugadores('o_1'), 'apodo'));
        $this->assertSame([], $panel->jugadores('%'));
        $this->assertSame([], $panel->jugadores('   '));

        // Un apodo sorteado no tiene nada que ocultar.
        $this->assertSame([['apodo' => 'Invitado 12345', 'conCuenta' => false, 'neutro' => true]], array_map(
            fn (array $jugador) => array_diff_key($jugador, ['id' => 0]),
            $panel->jugadores('invitado'),
        ));

        $this->actingAs($this->administrador())->get('/administracion?apodo='.str_repeat('a', 21))->assertSessionHasErrors('apodo');
    }

    public function test_si_la_anotacion_no_se_puede_guardar_la_accion_tampoco_queda_hecha(): void
    {
        $administrador = $this->administrador();
        $partida = $this->partidaQuietaContraElBot();
        $eventos = $partida->eventos()->count();

        // El cuaderno falla justo al escribir: la acción y su anotación van juntas, o no va ninguna.
        AccionDeAdministracion::creating(fn () => throw new RuntimeException('No se pudo escribir en el cuaderno.'));

        try {
            $this->app->make(Acciones::class)->cerrarPartida($administrador, $partida);
            $this->fail('Sin poder anotarla, la acción tenía que fallar.');
        } catch (RuntimeException) {
            $this->assertTrue($partida->fresh()->enCurso());
            $this->assertSame($eventos, $partida->eventos()->count());
        } finally {
            AccionDeAdministracion::flushEventListeners();
        }
    }

    public function test_cada_accion_queda_anotada_con_quien_que_y_cuando(): void
    {
        $this->travelTo(now()->startOfSecond());

        $administrador = $this->administrador();
        $partida = $this->partidaQuietaContraElBot();
        $fallidos = [$this->trabajoFallido(), $this->trabajoFallido()];
        $grosero = Jugador::factory()->create(['apodo' => 'Malapalabra']);

        $this->actingAs($administrador)->post("/administracion/partidas/{$partida->id}/cerrar");
        $this->actingAs($administrador)->post("/administracion/trabajos/{$fallidos[0]}/reintentar");
        $this->actingAs($administrador)->post("/administracion/trabajos/{$fallidos[1]}/descartar");
        $this->actingAs($administrador)->post('/administracion/limpieza');
        $this->actingAs($administrador)->post("/administracion/jugadores/{$grosero->id}/ocultar-apodo");

        $anotadas = AccionDeAdministracion::query()->orderBy('id')->get();

        $this->assertSame([
            AccionDeAdministracion::CERRAR_PARTIDA,
            AccionDeAdministracion::REINTENTAR_TRABAJO,
            AccionDeAdministracion::DESCARTAR_TRABAJO,
            AccionDeAdministracion::LIMPIAR,
            AccionDeAdministracion::OCULTAR_APODO,
        ], $anotadas->pluck('accion')->all());

        foreach ($anotadas as $anotada) {
            $this->assertSame($administrador->id, $anotada->administrador_id);
            $this->assertSame(now()->getTimestamp(), $anotada->creada_en->getTimestamp());
            $this->assertNotSame('', $anotada->detalle);
        }

        // El cuaderno las muestra, de la más nueva a la más vieja, con quién las hizo.
        $this->actingAs($administrador)->get('/administracion')->assertOk()->assertSeeInOrder([
            'El cuaderno',
            'ocultó el apodo «Malapalabra»',
            'corrió la limpieza: 0 salas cerradas y 0 invitados borrados',
            'descartó un trabajo que había fallado (TurnoDelBot)',
            'reintentó un trabajo que había fallado (TurnoDelBot)',
            "cerró la partida {$partida->id}, que llevaba 1 día sin movimiento",
        ])->assertSee($administrador->apodo);
    }

    private function administrador(): Jugador
    {
        return Jugador::factory()->administrador()->create();
    }

    /**
     * Una partida contra el bot que nadie toca hace más de un día.
     */
    private function partidaQuietaContraElBot(string $apodo = 'Cacho'): Partida
    {
        $partida = $this->laMesaDeVerdad()->abrir(Jugador::factory()->create(['apodo' => $apodo]));

        $this->travel(Mesa::HORAS_QUIETA_CONTRA_EL_BOT + 1)->hours();

        return $partida;
    }

    /**
     * Un turno del bot que falló, como lo deja la cola. Devuelve su identificador.
     */
    private function trabajoFallido(string $mensaje = 'se rompió'): string
    {
        $id = (string) Str::uuid();

        $this->app->make('queue.failer')->log('database', 'default', json_encode([
            'uuid' => $id,
            'displayName' => TurnoDelBot::class,
            'job' => 'Illuminate\Queue\CallQueuedHandler@call',
            'data' => ['commandName' => TurnoDelBot::class, 'command' => serialize(new TurnoDelBot(999999))],
        ], JSON_THROW_ON_ERROR), new RuntimeException($mensaje));

        return $id;
    }
}
