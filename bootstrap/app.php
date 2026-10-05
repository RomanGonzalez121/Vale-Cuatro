<?php

use App\Http\Middleware\ConCuenta;
use App\Http\Middleware\SinCuenta;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'con-cuenta' => ConCuenta::class,
            'sin-cuenta' => SinCuenta::class,
        ]);

        // Quien abre la mesa sin haber entrado vuelve a la portada, donde está el botón de jugar.
        // Si traía un aviso (por ejemplo, que la página había vencido) se conserva
        // un pedido más, para que lo llegue a ver en la portada.
        $middleware->redirectGuestsTo(function (Request $request) {
            $request->session()->reflash();

            return route('portada');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un formulario enviado desde una página que quedó abierta hasta vencer
        // la sesión: en vez del error 419, se vuelve atrás con un aviso.
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 419) {
                return back()->with('aviso', 'La página había vencido. Probá de nuevo.');
            }

            return $response;
        });
    })->create();
