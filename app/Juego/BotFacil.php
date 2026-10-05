<?php

namespace App\Juego;

use App\Motor\Accion;
use App\Motor\Azar;
use App\Motor\TipoDeAccion;

/**
 * El bot Fácil: juega al azar, con dos límites.
 *
 * No se va al mazo por su cuenta y la mayoría de las veces juega una carta en
 * vez de cantar. Cuando le cantan, contesta cualquier cosa de las que valen.
 * La flor es lo único que no deja al azar: si la tiene, la canta.
 */
final class BotFacil implements Bot
{
    /** De cada diez veces que le toca, cuántas juega una carta sin cantar nada. */
    private const JUEGA_CARTA = 7;

    public function __construct(private readonly Azar $azar) {}

    public function decidir(array $vista): Accion
    {
        if ((new Lectura($vista))->puede(TipoDeAccion::Flor)) {
            return Accion::de(TipoDeAccion::Flor);
        }

        $cartas = [];
        $cantos = [];

        foreach ($vista['acciones'] as $datos) {
            $accion = Accion::desdeArray($datos);

            match ($accion->tipo) {
                TipoDeAccion::Mazo => null,
                TipoDeAccion::Jugar => $cartas[] = $accion,
                default => $cantos[] = $accion,
            };
        }

        // Contestando un canto no hay cartas para elegir: sale una respuesta al azar.
        if ($cartas !== [] && ($cantos === [] || $this->azar->entero(1, 10) <= self::JUEGA_CARTA)) {
            return $this->unaDe($cartas);
        }

        return $this->unaDe($cantos);
    }

    /**
     * @param  non-empty-list<Accion>  $acciones
     */
    private function unaDe(array $acciones): Accion
    {
        return $acciones[$this->azar->entero(0, count($acciones) - 1)];
    }
}
