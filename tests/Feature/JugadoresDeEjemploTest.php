<?php

namespace Tests\Feature;

use App\Juego\JugadoresDeEjemplo;
use App\Juego\Mesa;
use App\Juego\Nivel;
use App\Juego\Ranking;
use App\Juego\Simulacion;
use App\Models\EventoDePartida;
use App\Models\Jugador;
use App\Models\Partida;
use App\Models\Resultado;
use App\Motor\Fase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los jugadores de ejemplo del ranking y las partidas simuladas de las que salen sus números.
 * Son simulados, pero juegan de verdad: con el motor, con los bots y dejando sus eventos guardados.
 */
class JugadoresDeEjemploTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_misma_semilla_da_siempre_la_misma_partida(): void
    {
        $simulacion = new Simulacion;

        $una = $simulacion->enElMotor(Nivel::Dificil, Nivel::Intermedio, 77);
        $otra = $simulacion->enElMotor(Nivel::Dificil, Nivel::Intermedio, 77);
        $distinta = $simulacion->enElMotor(Nivel::Dificil, Nivel::Intermedio, 78);

        $this->assertSame($una, $otra, 'Mano, eventos y ganador: todo igual, carta por carta.');
        $this->assertNotSame($una[1], $distinta[1]);
    }

    public function test_una_partida_simulada_queda_guardada_como_cualquier_otra_y_se_reconstruye_igual(): void
    {
        [$uno, $dos] = [Jugador::factory()->deEjemplo()->create(), Jugador::factory()->deEjemplo()->create()];
        $mesa = $this->app->make(Mesa::class);
        $empieza = now()->subDays(3)->startOfMinute();

        $partida = (new Simulacion)->jugar($uno, Nivel::Intermedio, $dos, Nivel::Facil, 5, $empieza, $mesa);

        $this->assertSame(Partida::TERMINADA, $partida->estado);
        $this->assertTrue($partida->fresh()->simulada, 'Lo simulado va marcado, también en la base.');
        $this->assertTrue($partida->created_at->equalTo($empieza));
        $this->assertTrue($partida->terminada_en->greaterThan($empieza));

        // Los eventos guardados, aplicados al motor por el camino de siempre, llegan al mismo final.
        $motor = $mesa->reconstruir($partida->fresh());

        $this->assertSame(Fase::Terminada, $motor->fase());
        $this->assertSame($partida->ganador, $motor->ganador());
        $this->assertSame(30, max($motor->tanteo()));

        // Y son exactamente los que salieron del motor con esa semilla.
        [, $eventos] = (new Simulacion)->enElMotor(Nivel::Intermedio, Nivel::Facil, 5);
        $this->assertSame(
            array_map(fn (array $evento) => [$evento[0], $evento[1], $evento[2]], $eventos),
            $partida->eventos()->get()->map(fn (EventoDePartida $evento) => [$evento->tipo, $evento->asiento, $evento->datos])->all(),
        );

        // Los dos quedan anotados para el ranking, uno ganando y el otro perdiendo.
        $this->assertSame([true, false], Resultado::query()->where('partida_id', $partida->id)->orderByDesc('gano')->pluck('gano')->all());
    }

    public function test_los_jugadores_de_ejemplo_juegan_entre_ellos_y_todos_salen_marcados_como_bots(): void
    {
        $ejemplo = new JugadoresDeEjemplo;
        $mesa = $this->app->make(Mesa::class);
        $cuantos = count(JugadoresDeEjemplo::lista());

        // Una vuelta: cada uno juega una vez contra cada uno de los otros.
        $jugadas = $ejemplo->sembrar($mesa, vueltas: 1);

        $this->assertSame($cuantos * ($cuantos - 1) / 2, $jugadas);
        $this->assertSame($jugadas, Partida::count());
        $this->assertSame($cuantos, Jugador::query()->where('de_ejemplo', true)->count());

        $filas = (new Ranking)->para()['filas'];

        $this->assertEqualsCanonicalizing(array_keys(JugadoresDeEjemplo::lista()), array_column($filas, 'apodo'));

        foreach ($filas as $fila) {
            $this->assertTrue($fila['bot'], "{$fila['apodo']} no está marcado como bot.");
            $this->assertSame($cuantos - 1, $fila['jugadas'], $fila['apodo']);
        }

        // Entre todos ganaron tantas partidas como se jugaron: ningún número está escrito a mano.
        $this->assertSame($jugadas, array_sum(array_column($filas, 'ganadas')));

        // En la pantalla, cada uno lleva la marca.
        $pagina = $this->get('/ranking')->assertOk()->getContent();
        $this->assertSame($cuantos, preg_match_all('/<\/svg>\s*bot\s*<\/span>/', $pagina));

        // Sembrar otra vez no juega nada ni duplica a nadie: se puede correr en cada publicación.
        $this->assertSame(0, $ejemplo->sembrar($mesa, vueltas: 1));
        $this->artisan('ranking:ejemplo')->expectsOutputToContain('ya tenían')->assertSuccessful();
        $this->assertSame($jugadas, Partida::count());
        $this->assertSame($cuantos, Jugador::query()->where('de_ejemplo', true)->count());

        // No son invitados que no volvieron: la limpieza diaria no se los lleva.
        $this->travel(Jugador::DIAS_DE_INVITADO + 30)->days();
        $this->artisan('model:prune', ['--model' => [Jugador::class]]);
        $this->assertSame($cuantos, Jugador::query()->where('de_ejemplo', true)->count());

        // Borrarlos se lleva todo lo suyo.
        $ejemplo->borrar();
        $this->assertSame([0, 0, 0, 0], [Jugador::count(), Partida::count(), EventoDePartida::count(), Resultado::count()]);
    }

    public function test_un_jugador_de_ejemplo_no_es_una_cuenta_ni_un_invitado_y_no_le_saca_el_apodo_a_nadie(): void
    {
        // Alguien ya se llama como uno de los de ejemplo: ese apodo es suyo.
        $apodo = array_key_first(JugadoresDeEjemplo::lista());
        $persona = Jugador::factory()->create(['apodo' => $apodo]);

        // Sin vueltas: solo se crean los jugadores.
        (new JugadoresDeEjemplo)->sembrar($this->app->make(Mesa::class), vueltas: 0);

        $deEjemplo = Jugador::query()->where('de_ejemplo', true)->get();

        $this->assertCount(count(JugadoresDeEjemplo::lista()) - 1, $deEjemplo);
        $this->assertFalse($persona->fresh()->esDeEjemplo());

        foreach ($deEjemplo as $jugador) {
            $this->assertFalse($jugador->esInvitado(), $jugador->apodo);
            // Sin email ni contraseña no hay con qué ingresar como él.
            $this->assertNull($jugador->email);
            $this->assertNull($jugador->getAuthPassword());
        }
    }

    public function test_ningun_formulario_puede_crear_un_jugador_de_ejemplo(): void
    {
        $this->post(route('registro'), [
            'apodo' => 'Vivo',
            'email' => 'vivo@example.com',
            'password' => 'una-clave-larga-1',
            'password_confirmation' => 'una-clave-larga-1',
            'de_ejemplo' => '1',
        ]);

        $this->assertFalse(Jugador::query()->where('apodo', 'Vivo')->sole()->esDeEjemplo());
    }
}
