<?php

namespace App\Administracion;

/**
 * El email y la contraseña de la cuenta que administra el sitio, y el apodo con el que nace.
 *
 * Salen del entorno (ADMIN_EMAIL, ADMIN_PASSWORD y ADMIN_APODO) y se leen solo cuando el comando
 * "administrador:crear" los necesita. No pasan por los archivos de configuración a propósito: la
 * configuración se guarda ya resuelta en un archivo del contenedor y se carga en cada pedido, y la
 * contraseña que abre el panel no tiene por qué estar ahí. En la base queda solo su hash.
 *
 * En el sitio publicado esas variables se cargan en el panel del servicio, igual que la contraseña de
 * la base: el repositorio es público y no las lleva escritas en ningún archivo.
 */
final class Credenciales
{
    public const APODO_DE_FABRICA = 'Cantinero';

    public function __construct(
        public readonly ?string $email,
        public readonly ?string $password,
        public readonly string $apodo = self::APODO_DE_FABRICA,
    ) {}

    public static function delEntorno(): self
    {
        return new self(env('ADMIN_EMAIL'), env('ADMIN_PASSWORD'), (string) env('ADMIN_APODO', self::APODO_DE_FABRICA));
    }
}
