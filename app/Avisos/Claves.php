<?php

namespace App\Avisos;

use App\Models\ClaveDeAviso;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * El par de claves con que el sitio firma sus avisos (lo que el estándar llama VAPID).
 *
 * La pública se le da a cada navegador que se suscribe: con ella el servicio de avisos comprueba que
 * quien manda es este sitio y no otro. La privada firma cada envío y no sale del servidor.
 *
 * Se generan solas la primera vez que hacen falta y quedan en la base, con la privada cifrada. Tienen
 * que ser siempre las mismas: si cambiaran, todas las suscripciones hechas con las anteriores dejarían
 * de recibir avisos. Por eso no se generan al arrancar (el contenedor se rearma en cada publicación) ni
 * van en un archivo (el disco tampoco sobrevive); y no van en el repositorio, que es público.
 */
final class Claves
{
    /** Hay un solo par: siempre es la fila con este número. */
    private const FILA = 1;

    /** Lo que mide cada número de una clave de esta curva, en bytes. */
    private const BYTES = 32;

    public function publica(): string
    {
        return $this->par()->publica;
    }

    public function privada(): string
    {
        return $this->par()->privada;
    }

    private function par(): ClaveDeAviso
    {
        $guardado = ClaveDeAviso::query()->find(self::FILA);

        if ($guardado !== null) {
            return $guardado;
        }

        [$publica, $privada] = self::generar();

        try {
            return ClaveDeAviso::query()->create(['id' => self::FILA, 'publica' => $publica, 'privada' => $privada]);
        } catch (UniqueConstraintViolationException) {
            // Dos pedidos a la vez la primera vez: ganó el otro, y valen las suyas.
            return ClaveDeAviso::query()->findOrFail(self::FILA);
        }
    }

    /**
     * Genera un par nuevo, en la forma en que lo piden los navegadores: una clave de curva elíptica
     * (P-256), con cada parte escrita en base64 "para direcciones".
     *
     * A OpenSSL se le pasa una configuración mínima propia (openssl.cnf, al lado de este archivo) en vez
     * de dejar que busque la de la máquina: en Windows esa suele faltar o estar mal, y la clave no sale.
     *
     * @return array{0: string, 1: string} La pública y la privada.
     */
    private static function generar(): array
    {
        $clave = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'config' => __DIR__.DIRECTORY_SEPARATOR.'openssl.cnf',
        ]);

        $partes = $clave === false ? null : (openssl_pkey_get_details($clave)['ec'] ?? null);

        if (! isset($partes['x'], $partes['y'], $partes['d'])) {
            throw new RuntimeException('No se pudieron generar las claves de los avisos.');
        }

        // Cada número va en 32 bytes, con ceros adelante si salió más corto.
        $entero = fn (string $numero): string => str_pad($numero, self::BYTES, "\0", STR_PAD_LEFT);
        $paraDirecciones = fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [
            // La pública es un punto de la curva: un 4 adelante y sus dos coordenadas.
            $paraDirecciones("\x04".$entero($partes['x']).$entero($partes['y'])),
            $paraDirecciones($entero($partes['d'])),
        ];
    }
}
