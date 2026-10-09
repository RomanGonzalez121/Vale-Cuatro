<?php

use App\Http\Middleware\ConCuenta;
use App\Http\Middleware\SesionDeInvitado;
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
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // El sitio publicado no recibe al navegador directo: delante hay un servidor (el de Render) que
        // atiende el HTTPS y le pasa el pedido. Se le cree lo que dice de ese pedido original; si no,
        // el sitio pensaría que lo visitan por HTTP y armaría mal los links y las cookies.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'con-cuenta' => ConCuenta::class,
            'sin-cuenta' => SinCuenta::class,
        ]);

        // En toda página: la sesión de un invitado recuerda cuándo empezó, que es desde cuándo ve su historial.
        $middleware->web(append: [SesionDeInvitado::class]);

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
        // Los pedidos de la mesa esperan JSON: a esos se les deja el 419, y la mesa avisa.
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 419 && ! request()->expectsJson()) {
                return back()->with('aviso', 'La página había vencido. Probá de nuevo.');
            }

            return $response;
        });
    })->create();
