<?php

namespace Tests\Feature;

use App\Identidad\Iconos;
use App\Juego\Logros;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Titulos;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Sello;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * La libreta del club (M23): los logros, sus sellos y sus fósforos. Cada cuenta nueva sale de una mano armada con
 * cartas elegidas, jugada de punta a punta.
 */
class LibretaTest extends TestCase
{
    use JugandoPartidas;
    use PartidasCortas;
    use RefreshDatabase;

    public function test_cada_logro_esta_bien_escrito_y_cada_titulo_pide_lo_mismo_que_su_escalon(): void
    {
        $columnas = Schema::getColumnListing('jugadores');
        $titulosDeLogros = [];

        foreach (Logros::fichas() as $clave => $ficha) {
            $this->assertContains($ficha['cuenta'], $columnas, "El logro {$clave} cuenta algo que no existe.");
            $this->assertContains($ficha['cuenta'], [...Titulos::CUENTAS, ...Titulos::DE_LOS_JUEGOS], "Nadie suma la cuenta del logro {$clave}.");
            $this->assertArrayHasKey($ficha['tema'], Logros::TEMAS);
            $this->assertNotNull(Iconos::TODOS[$ficha['icono']] ?? null, "El logro {$clave} usa un ícono que no existe.");

            $escalones = $ficha['escalones'];
            $ordenados = $escalones;
            sort($ordenados);
            $this->assertSame($ordenados, $escalones, "Los escalones del logro {$clave} no suben.");
            $this->assertSame(count($escalones), count(array_unique($escalones)));

            if (isset($ficha['titulo'])) {
                $titulo = Titulos::TODOS[$ficha['titulo']];
                $this->assertSame($ficha['cuenta'], $titulo['cuenta'], "El título del logro {$clave} cuenta otra cosa.");
                $this->assertSame($escalones[($ficha['titulo_en'] ?? 2) - 1], $titulo['pide'], "El título del logro {$clave} no pide lo mismo que su escalón.");
                $titulosDeLogros[] = $ficha['titulo'];
            }
        }

        // Todo título que se gana con una cuenta sale de un escalón de la libreta.
        $conCuenta = array_keys(array_filter(Titulos::TODOS, fn (array $titulo) => $titulo['cuenta'] !== null));
        $this->assertEqualsCanonicalizing($conCuenta, $titulosDeLogros);
    }

    public function test_cada_escalon_alcanzado_es_un_sello_con_su_fosforo_y_no_se_da_dos_veces(): void
    {
        $jugador = Jugador::factory()->create(['envidos_ganados' => 30, 'fosforos' => 5]);
        $logros = new Logros;

        // Tantero pide 10, 25 y 100: llega a dos escalones.
        $nuevos = $logros->revisar($jugador);

        $this->assertSame([['logro' => 'tantero', 'escalon' => 1], ['logro' => 'tantero', 'escalon' => 2]], $nuevos);
        $this->assertSame(7, $jugador->fresh()->fosforos);
        $this->assertSame([], $logros->revisar($jugador));
        $this->assertSame(7, $jugador->fresh()->fosforos);
        $this->assertTrue(Titulos::gano($jugador->fresh(), 'tantero'));
    }

    public function test_al_cerrar_la_partida_se_ganan_los_sellos_y_el_final_los_muestra(): void
    {
        $uno = Jugador::factory()->create(['partidas_jugadas' => 24, 'partidas_ganadas' => 9]);
        $dos = Jugador::factory()->create();

        $partida = $this->partidaCorta($uno, $dos);
        $this->ganar($partida, Mesa::JUGADOR);

        // La partida número 25 y la ganada número 10: dos sellos, dos fósforos además de los tres de ganar.
        $this->assertEqualsCanonicalizing(['jugadas', 'ganadas'], Sello::query()->where('jugador_id', $uno->id)->pluck('logro')->all());
        $this->assertSame(5, $uno->fresh()->fosforos);

        $paso = collect($this->laMesaDeVerdad()->pasosDesde($partida->fresh(), 0, Mesa::JUGADOR))->last();

        $this->assertSame(5, $paso['experiencia']['ganados']);
        $this->assertEqualsCanonicalizing(['jugadas', 'ganadas'], array_column($paso['experiencia']['sellos'], 'logro'));
        $this->assertStringContainsString('<svg', $paso['experiencia']['sellos'][0]['dibujo']);

        // Quien perdió no ganó la décima.
        $this->assertFalse(Sello::query()->where('jugador_id', $dos->id)->where('logro', 'ganadas')->exists());
    }

    public function test_recalcular_conserva_los_sellos_que_siguen_y_saca_los_que_ya_no_alcanzan(): void
    {
        $jugador = Jugador::factory()->create(['envidos_cantados' => 12]);
        (new Logros)->revisar($jugador);
        $sello = Sello::query()->where('jugador_id', $jugador->id)->sole();
        $sello->forceFill(['ganado_en' => now()->subMonth(), 'visto' => true])->save();

        // Un sello que las cuentas ya no respaldan se va al recalcular.
        Sello::query()->insert(['jugador_id' => $jugador->id, 'logro' => 'chamuyero', 'escalon' => 1, 'ganado_en' => now(), 'visto' => true]);

        $this->artisan('fosforos:recalcular')->assertSuccessful();

        // Sin partidas, las cuentas quedan en cero: no queda ninguno. Con la cuenta de vuelta, vuelve uno nuevo.
        $this->assertSame(0, Sello::query()->where('jugador_id', $jugador->id)->count());

        $jugador = $jugador->fresh();
        $jugador->forceFill(['envidos_cantados' => 12])->save();
        (new Logros)->revisar($jugador);
        $this->assertSame(1, Sello::query()->where('jugador_id', $jugador->id)->count());
        $this->assertSame(1, $jugador->fresh()->fosforos);
    }

    public function test_la_libreta_no_dice_que_pide_un_secreto_hasta_ganarlo_y_marca_lo_nuevo(): void
    {
        $jugador = Jugador::factory()->create(['tres_pardas' => 1]);
        $otro = Jugador::factory()->create();
        (new Logros)->revisar($jugador);

        $secretos = collect((new Logros)->libreta($jugador)['secretos']['logros'])->keyBy('clave');
        $this->assertSame('Las tres pardas', $secretos['tres-pardas']['nombre']);
        $this->assertSame('Secreto', $secretos['treinta-a-cero']['nombre']);

        // El menú marca el sello nuevo hasta que se ve en el perfil.
        $this->actingAs($jugador)->get('/ranking')->assertSee('con-marca', false);
        $this->actingAs($jugador)->get('/perfil')->assertOk()->assertSee('Libreta de socio')->assertSee('Nuevo');
        $this->assertFalse((new Logros)->hayNuevos($jugador));

        // En el perfil público se ve su libreta, sin el "Nuevo" y sin decir qué piden los secretos que no ganó.
        $publico = $this->actingAs($otro)->get('/jugadores/'.$jugador->id)->assertOk();
        $publico->assertSee('Las tres pardas')->assertDontSee('Ganar una partida a 30 sin que el rival anote');
    }

    public function test_las_cuentas_nuevas_salen_de_manos_armadas(): void
    {
        // Treinta y tres, y el envido está primero: le cantan truco y contesta envido con 33.
        $envido = $this->cuentas(['7-oro', '6-oro', '4-copa'], ['4-espada', '5-basto', '10-copa'], [[1, 'truco'], [0, 'envido'], [1, 'quiero']], mano: 1);
        $this->assertSame([1, 1], [$envido['treinta_y_tres'], $envido['envido_primero']]);

        // Quiso el truco y lo ganó.
        $quiero = $this->cuentas(['1-espada', '1-basto', '7-espada'], ['4-copa', '4-oro', '5-copa'], [
            [1, 'truco'], [0, 'quiero'], [1, 'jugar', '4-copa'], [0, 'jugar', '1-espada'], [0, 'jugar', '1-basto'], [1, 'jugar', '4-oro'],
        ], mano: 1);
        $this->assertSame([1, 1], [$quiero['quieros_ganados'], $quiero['trucos_ganados']]);

        // El ancho de espada le gana al de basto.
        $anchos = $this->cuentas(['1-espada', '4-copa', '5-oro'], ['1-basto', '6-copa', '7-copa'], [[0, 'jugar', '1-espada'], [1, 'jugar', '1-basto']]);
        $this->assertSame(1, $anchos['ancho_contra_ancho']);

        // Las tres pardas: gana el mano.
        $pardas = $this->cuentas(['3-oro', '2-copa', '4-espada'], ['3-copa', '2-oro', '4-basto'], [
            [0, 'jugar', '3-oro'], [1, 'jugar', '3-copa'], [0, 'jugar', '2-copa'], [1, 'jugar', '2-oro'], [0, 'jugar', '4-espada'], [1, 'jugar', '4-basto'],
        ]);
        $this->assertSame([1, 1], [$pardas['tres_pardas'], $pardas['manos_con_parda']]);

        // Una contraflor al resto querida y ganada.
        $flor = $this->cuentas(['7-copa', '6-copa', '5-copa'], ['4-oro', '5-oro', '1-oro'], [[0, 'flor'], [0, 'jugar', '5-copa'], [1, 'contraflor_al_resto'], [0, 'quiero']]);
        $this->assertSame([1, 1], [$flor['contraflores_ganadas'], $flor['contraflor_al_resto']]);
    }

    public function test_las_cuentas_de_la_partida_entera_salen_de_quien_la_gano(): void
    {
        $jugador = Jugador::factory()->create();
        [$partida] = $this->partidaContraElBot($jugador, Nivel::Dificil, 100);
        $gano = $partida->ganador === Mesa::JUGADOR;

        $cuentas = (new Titulos)->en($partida, $this->app->make(Mesa::class))[0];

        $this->assertSame(1, $cuentas['partidas_jugadas']);
        $this->assertSame((int) $gano, $cuentas['partidas_ganadas']);
        $this->assertSame((int) $gano, $cuentas['dificil_ganadas']);
        $this->assertSame(0, $cuentas['ultra_ganadas']);
    }

    /**
     * Juega una mano armada y devuelve lo que cuenta para la libreta del asiento 0.
     *
     * @param  list<string>  $mias
     * @param  list<string>  $suyas
     * @param  list<array{0: int, 1: string, 2?: string}>  $jugadas
     * @return array<string, int>
     */
    private function cuentas(array $mias, array $suyas, array $jugadas, int $mano = Mesa::JUGADOR): array
    {
        $jugador = Jugador::factory()->create();
        $partida = Partida::create(['jugador_id' => $jugador->id, 'primer_mano' => $mano, 'puntos' => 30]);
        $partida->eventos()->create(['numero' => 1, 'tipo' => EventoDePartida::REPARTO, 'datos' => ['manos' => [$mias, $suyas]], 'creado_en' => now()]);

        foreach ($jugadas as $i => $jugada) {
            $partida->eventos()->create([
                'numero' => $i + 2,
                'tipo' => EventoDePartida::ACCION,
                'asiento' => $jugada[0],
                'datos' => isset($jugada[2]) ? ['tipo' => $jugada[1], 'carta' => $jugada[2]] : ['tipo' => $jugada[1]],
                'creado_en' => now(),
            ]);
        }

        $partida->forceFill(['estado' => Partida::ABANDONADA])->save();

        return (new Titulos)->en($partida, $this->app->make(Mesa::class))[0];
    }
}
