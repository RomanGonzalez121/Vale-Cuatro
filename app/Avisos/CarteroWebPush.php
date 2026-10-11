<?php

namespace App\Avisos;

use App\Models\SuscripcionDeAviso;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Manda el aviso de verdad, por Web Push: lo cifra para ese navegador, lo firma con las claves del
 * sitio y se lo entrega al servicio de avisos del navegador (el de Chrome, el de Firefox, el de Apple),
 * que es el que lo hace llegar al equipo.
 */
final class CarteroWebPush implements Cartero
{
    /** Un aviso de "te toca" que llega tarde ya no sirve: si no se pudo entregar en este rato, se descarta. */
    private const SEGUNDOS_QUE_VALE = 60;

    private const SEGUNDOS_DE_ESPERA = 10;

    public function __construct(private readonly Claves $claves) {}

    public function entregar(SuscripcionDeAviso $suscripcion, array $aviso): string
    {
        try {
            $envio = new WebPush(
                ['VAPID' => ['subject' => config('avisos.contacto'), 'publicKey' => $this->claves->publica(), 'privateKey' => $this->claves->privada()]],
                ['TTL' => self::SEGUNDOS_QUE_VALE, 'urgency' => 'high'],
                // Con su propio tope de espera, y sin seguir redirecciones: el pedido va a donde se dijo y a ningún otro lado.
                new Client(['timeout' => self::SEGUNDOS_DE_ESPERA, 'connect_timeout' => self::SEGUNDOS_DE_ESPERA, 'allow_redirects' => false]),
            );

            $resultado = $envio->sendOneNotification(
                Subscription::create(['endpoint' => $suscripcion->destino, 'keys' => ['p256dh' => $suscripcion->p256dh, 'auth' => $suscripcion->auth]]),
                json_encode($aviso, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );
        } catch (Throwable $falla) {
            report($falla);

            return self::FALLO;
        }

        if ($resultado->isSuccess()) {
            return self::ENTREGADO;
        }

        if ($resultado->isSubscriptionExpired()) {
            return self::VENCIDA;
        }

        // Queda anotado qué contestó el servicio. Solo el código: el motivo completo trae la dirección del
        // navegador, que es lo único que hace falta para mandarle avisos.
        Log::warning('Un aviso no se pudo entregar.', ['respuesta' => $resultado->getResponse()?->getStatusCode()]);

        return self::FALLO;
    }
}
