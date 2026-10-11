<?php

namespace App\Avisos;

use App\Models\SuscripcionDeAviso;

/**
 * Quien lleva un aviso hasta un navegador. Está aparte para poder probar todo lo demás (a quién se le
 * avisa, cuándo y con qué texto) sin mandar nada de verdad.
 */
interface Cartero
{
    /** Llegó al servicio de avisos del navegador. */
    public const ENTREGADO = 'entregado';

    /** El navegador ya no existe o retiró el permiso: esa suscripción no sirve más. */
    public const VENCIDA = 'vencida';

    /** No se pudo mandar esta vez (el servicio no contestó, o contestó un error). */
    public const FALLO = 'fallo';

    /**
     * @param  array{titulo: string, cuerpo: string, etiqueta: string, url: string}  $aviso
     * @return self::ENTREGADO|self::VENCIDA|self::FALLO
     */
    public function entregar(SuscripcionDeAviso $suscripcion, array $aviso): string;
}
