<?php

namespace Tests\Unit;

use App\Juego\BotDificil;
use App\Juego\BotFacil;
use App\Juego\BotIntermedio;
use App\Juego\Nivel;
use App\Motor\Azar;
use PHPUnit\Framework\TestCase;

class NivelTest extends TestCase
{
    public function test_cada_nivel_tiene_su_nombre_y_su_bot(): void
    {
        $azar = Azar::deSemilla(1);

        $this->assertSame(['Fácil', 'Intermedio', 'Difícil'], array_map(fn (Nivel $nivel) => $nivel->nombre(), Nivel::cases()));
        $this->assertInstanceOf(BotFacil::class, Nivel::Facil->bot($azar));
        $this->assertInstanceOf(BotIntermedio::class, Nivel::Intermedio->bot($azar));
        $this->assertInstanceOf(BotDificil::class, Nivel::Dificil->bot($azar));
    }

    public function test_sin_elegir_se_juega_contra_intermedio(): void
    {
        $this->assertSame(Nivel::Intermedio, Nivel::porDefecto());
        $this->assertSame(Nivel::Intermedio, Nivel::from(2));
    }
}
