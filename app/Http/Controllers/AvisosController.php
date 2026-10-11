<?php

namespace App\Http\Controllers;

use App\Avisos\Avisos;
use App\Avisos\Presencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Lo que el navegador le pide al sitio sobre los avisos: anotarse para recibirlos, darse de baja y decir
 * si tiene la mesa a la vista. Siempre sobre quien hace el pedido: nadie anota ni borra el navegador de otro.
 */
class AvisosController extends Controller
{
    public function __construct(private readonly Avisos $avisos, private readonly Presencia $presencia) {}

    /**
     * El navegador ya pidió el permiso y su servicio de avisos le dio una dirección y dos claves: se anotan.
     */
    public function suscribir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'destino' => ['required', 'string', 'max:1000', 'url:https'],
            // Las dos claves vienen en base64 "para direcciones": letras, números, guion y guion bajo.
            'p256dh' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-+\/=]+$/'],
            'auth' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-+\/=]+$/'],
        ]);

        try {
            $this->avisos->suscribir($request->user(), $datos['destino'], $datos['p256dh'], $datos['auth']);
        } catch (InvalidArgumentException $rechazo) {
            return response()->json(['motivo' => $rechazo->getMessage()], 422);
        }

        return response()->json(['avisos' => true], 201);
    }

    public function baja(Request $request): JsonResponse
    {
        $datos = $request->validate(['destino' => ['required', 'string', 'max:1000']]);

        $this->avisos->darDeBaja($request->user(), $datos['destino']);

        return response()->json(['avisos' => false]);
    }

    /**
     * La mesa (o la sala) avisa si está a la vista o si se tapó. A quien la está mirando no se le manda un aviso.
     */
    public function vista(Request $request): JsonResponse
    {
        $datos = $request->validate(['visible' => ['required', 'boolean']]);

        $this->presencia->marcar((int) $request->user()->getKey(), (bool) $datos['visible']);

        return response()->json(['visible' => (bool) $datos['visible']]);
    }
}
