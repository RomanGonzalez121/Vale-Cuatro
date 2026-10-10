<?php

namespace App\Administracion;

use App\Juego\Mesa;
use App\Models\AccionDeAdministracion;
use App\Models\Jugador;
use App\Models\Partida;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel as Consola;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el panel de administración puede hacer. Son cinco cosas, y cada una queda anotada en el
 * cuaderno con quién la hizo, qué hizo y cuándo.
 *
 * Ninguna borra lo que se jugó ni cambia un resultado: cerrar una partida le agrega un evento a su
 * lista, y ocultar un apodo lo reemplaza por uno neutro. Todas vuelven a comprobar que quien las pide
 * administra el sitio, aunque la ruta ya lo haya mirado. Y la acción y su anotación van juntas, en
 * una misma transacción: o quedan las dos, o no queda ninguna.
 */
final class Acciones
{
    public function __construct(
        private readonly Mesa $mesa,
        private readonly FailedJobProviderInterface $fallidos,
        private readonly Consola $consola,
    ) {}

    /**
     * Cierra una partida que quedó sin movimiento. Devuelve false si ya no hacía falta: terminó, o se
     * volvió a mover entre que se abrió el panel y se tocó el botón.
     */
    public function cerrarPartida(Jugador $quien, Partida $partida): bool
    {
        return $this->anotando($quien, function (Closure $anotar) use ($partida) {
            $ultimo = $partida->eventos()->max('creado_en') ?? $partida->created_at;

            if (! $this->mesa->cerrarQuieta($partida)) {
                return false;
            }

            $quieta = Carbon::parse($ultimo)->locale('es')->diffForHumans(now(), ['syntax' => Carbon::DIFF_ABSOLUTE]);

            $anotar(AccionDeAdministracion::CERRAR_PARTIDA, "cerró la partida {$partida->id}, que llevaba {$quieta} sin movimiento");

            return true;
        });
    }

    /**
     * Vuelve a poner en la cola un trabajo que había fallado. Devuelve false si ese trabajo ya no está.
     */
    public function reintentarTrabajo(Jugador $quien, string $id): bool
    {
        return $this->anotando($quien, function (Closure $anotar) use ($id) {
            $fallido = $this->fallidos->find($id);

            if ($fallido === null) {
                return false;
            }

            $this->consola->call('queue:retry', ['id' => [$id]]);
            $anotar(AccionDeAdministracion::REINTENTAR_TRABAJO, 'reintentó un trabajo que había fallado ('.Panel::nombreDelTrabajo($fallido).')');

            return true;
        });
    }

    /**
     * Saca de la lista un trabajo fallido, sin volver a intentarlo. Devuelve false si ya no estaba.
     */
    public function descartarTrabajo(Jugador $quien, string $id): bool
    {
        return $this->anotando($quien, function (Closure $anotar) use ($id) {
            $fallido = $this->fallidos->find($id);

            if ($fallido === null || ! $this->fallidos->forget($id)) {
                return false;
            }

            $anotar(AccionDeAdministracion::DESCARTAR_TRABAJO, 'descartó un trabajo que había fallado ('.Panel::nombreDelTrabajo($fallido).')');

            return true;
        });
    }

    /**
     * Corre ahora la limpieza que el sitio hace sola cada tanto: cierra las salas que esperaron rival de
     * más y borra a los invitados que no volvieron. Devuelve qué se llevó, dicho en una frase.
     */
    public function limpiar(Jugador $quien): string
    {
        return $this->anotando($quien, function (Closure $anotar) {
            $invitados = (new Jugador)->prunable()->count();
            $salas = $this->mesa->cerrarSalasVencidas();

            $this->consola->call('model:prune', ['--model' => [Jugador::class]]);

            $resumen = ($salas === 1 ? '1 sala cerrada' : "{$salas} salas cerradas").' y '.($invitados === 1 ? '1 invitado borrado' : "{$invitados} invitados borrados");

            $anotar(AccionDeAdministracion::LIMPIAR, "corrió la limpieza: {$resumen}");

            return $resumen;
        });
    }

    /**
     * Reemplaza el apodo de un jugador por uno neutro. Devuelve el apodo nuevo, o null si no correspondía:
     * los jugadores de ejemplo llevan los apodos que les puso el sitio, y la cuenta de administración no se toca.
     */
    public function ocultarApodo(Jugador $quien, Jugador $jugador): ?string
    {
        return $this->anotando($quien, function (Closure $anotar) use ($jugador) {
            if ($jugador->esDeEjemplo() || $jugador->esAdministrador()) {
                return null;
            }

            $anterior = $jugador->ocultarApodo();

            $anotar(AccionDeAdministracion::OCULTAR_APODO, "ocultó el apodo «{$anterior}» del jugador {$jugador->id}, que pasó a llamarse {$jugador->apodo}");

            return $jugador->apodo;
        });
    }

    /**
     * Corre una acción del panel: comprueba que quien la pide administra el sitio y le pasa con qué
     * anotarla en el cuaderno. Todo va en una transacción, así una acción no queda hecha sin su anotación.
     *
     * @template T
     *
     * @param  Closure(Closure(string, string): void): T  $hacer
     * @return T
     */
    private function anotando(Jugador $quien, Closure $hacer): mixed
    {
        if (! $quien->esAdministrador()) {
            throw new AuthorizationException('Esto lo hace solo quien administra el sitio.');
        }

        return DB::transaction(fn () => $hacer(function (string $accion, string $detalle) use ($quien) {
            AccionDeAdministracion::create([
                'administrador_id' => $quien->getKey(),
                'accion' => $accion,
                'detalle' => mb_substr($detalle, 0, 255),
                'creada_en' => now(),
            ]);
        }));
    }
}
