<?php

namespace Tests\Feature;

use App\Juego\Nivel;
use App\Models\Jugador;
use App\Models\Partida;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Lo que la base guarda de una partida entre dos personas: quién ocupa cada asiento y el código del link.
 */
class PartidaEntrePersonasTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_partida_contra_el_bot_sigue_siendo_como_antes(): void
    {
        $partida = Partida::create(['jugador_id' => Jugador::factory()->invitado()->create()->id, 'primer_mano' => 1, 'puntos' => 30])->fresh();

        $this->assertFalse($partida->entre_personas);
        $this->assertNull($partida->invitado_id);
        $this->assertNull($partida->codigo);
        $this->assertSame(Partida::EN_CURSO, $partida->estado);
        $this->assertSame(Nivel::Intermedio, $partida->nivel_bot);
    }

    public function test_una_partida_entre_personas_nace_sin_primer_mano_ni_bot(): void
    {
        $partida = $this->esperando(Jugador::factory()->invitado()->create())->fresh();

        $this->assertTrue($partida->entre_personas);
        $this->assertTrue($partida->esperando());
        $this->assertTrue($partida->estaAbierta());
        $this->assertFalse($partida->enCurso());
        $this->assertNull($partida->primer_mano);
        $this->assertNull($partida->nivel_bot);
    }

    public function test_cada_jugador_ocupa_su_asiento_y_un_tercero_no_tiene_ninguno(): void
    {
        $quienCreo = Jugador::factory()->invitado()->create();
        $quienEntro = Jugador::factory()->invitado()->create();
        $otro = Jugador::factory()->invitado()->create();

        $partida = $this->esperando($quienCreo);

        // Mientras nadie se sentó, el asiento 1 no es de nadie.
        $this->assertSame(0, $partida->asientoDe($quienCreo));
        $this->assertNull($partida->asientoDe($quienEntro));

        $partida->update(['invitado_id' => $quienEntro->id]);
        $partida = $partida->fresh();

        $this->assertSame(0, $partida->asientoDe($quienCreo));
        $this->assertSame(1, $partida->asientoDe($quienEntro));
        $this->assertNull($partida->asientoDe($otro));
    }

    public function test_en_una_partida_contra_el_bot_el_asiento_1_no_es_de_ninguna_persona(): void
    {
        $creador = Jugador::factory()->invitado()->create();
        $otro = Jugador::factory()->invitado()->create();
        $partida = Partida::create(['jugador_id' => $creador->id, 'primer_mano' => 0, 'puntos' => 30]);

        $this->assertSame(0, $partida->asientoDe($creador));
        $this->assertNull($partida->asientoDe($otro));
    }

    public function test_el_codigo_es_largo_distinto_cada_vez_y_no_se_repite_en_la_base(): void
    {
        $codigos = array_map(fn () => Partida::codigoNuevo(), range(1, 200));

        $this->assertCount(200, array_unique($codigos));

        foreach ($codigos as $codigo) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{16}$/', $codigo);
        }

        $creador = Jugador::factory()->invitado()->create();
        $this->esperando($creador, 'abcdefghijklmnop');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->esperando($creador, 'abcdefghijklmnop');
    }

    public function test_si_se_borra_la_cuenta_del_rival_la_partida_queda(): void
    {
        $quienCreo = Jugador::factory()->invitado()->create();
        $quienEntro = Jugador::factory()->invitado()->create();
        $partida = $this->esperando($quienCreo);
        $partida->update(['invitado_id' => $quienEntro->id]);

        $quienEntro->delete();

        $this->assertNull($partida->fresh()->invitado_id);
        $this->assertSame(0, $partida->fresh()->asientoDe($quienCreo));
    }

    public function test_los_ids_se_leen_siempre_como_enteros(): void
    {
        $partida = $this->esperando(Jugador::factory()->invitado()->create());
        $partida->update(['invitado_id' => Jugador::factory()->invitado()->create()->id]);
        $partida = Partida::query()->findOrFail($partida->id);

        $this->assertIsInt($partida->jugador_id);
        $this->assertIsInt($partida->invitado_id);
    }

    public function test_las_columnas_nuevas_existen(): void
    {
        foreach (['invitado_id', 'entre_personas', 'codigo'] as $columna) {
            $this->assertTrue(Schema::hasColumn('partidas', $columna), "Falta la columna {$columna}.");
        }
    }

    /**
     * Una partida entre personas recién creada: con su link y sin rival sentado.
     */
    private function esperando(Jugador $quienCrea, ?string $codigo = null): Partida
    {
        $partida = new Partida([
            'jugador_id' => $quienCrea->id,
            'puntos' => 30,
            'entre_personas' => true,
            'codigo' => $codigo ?? Partida::codigoNuevo(),
            'nivel_bot' => null,
        ]);

        // El estado no se asigna en masa: solo lo cambia el código de la mesa.
        $partida->estado = Partida::ESPERANDO;
        $partida->save();

        return $partida;
    }
}
