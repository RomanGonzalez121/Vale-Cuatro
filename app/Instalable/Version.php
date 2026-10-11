<?php

namespace App\Instalable;

/**
 * La versión de lo compilado (estilos, scripts y fuentes). Cambia cada vez que cambia alguno de esos
 * archivos, y nada más que entonces.
 *
 * La usa el service worker (public/sw.js): la página lo registra con esta versión en la dirección, así
 * cuando se publica una versión nueva el navegador lo instala de nuevo y borra lo que tenía guardado.
 */
final class Version
{
    private static ?string $actual = null;

    public static function actual(): string
    {
        return self::$actual ??= self::leer();
    }

    private static function leer(): string
    {
        // Vite deja en este archivo la lista de lo que compiló, con la huella de cada cosa en su nombre.
        $lista = public_path('build/manifest.json');

        // Sin compilar (en desarrollo, con Vite sirviendo los archivos) no hay nada que guardar.
        return is_file($lista) ? substr((string) md5_file($lista), 0, 12) : 'desarrollo';
    }
}
