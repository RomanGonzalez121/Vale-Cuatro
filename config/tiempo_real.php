<?php

return [

    /*
    | Adónde se conecta el navegador para el tiempo real.
    |
    | No es lo mismo que la dirección por la que el sitio le habla a Reverb (REVERB_HOST y compañía, en
    | broadcasting.php). En una máquina de desarrollo coinciden: Reverb está en su propio puerto y los dos
    | llegan por ahí. En el sitio publicado no: el sitio le habla por adentro del contenedor, y el navegador
    | entra por la dirección pública, con HTTPS, por la misma puerta que las páginas.
    |
    | Estos datos van escritos en cada página (ver el layout) y no compilados dentro del JavaScript: así
    | la misma imagen sirve en cualquier dirección donde se publique.
    |
    | La clave es pública por diseño: identifica a la aplicación, no autoriza nada. Lo que protege cada
    | canal es el permiso que da el servidor (routes/channels.php).
    */

    'navegador' => [
        'clave' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_PUBLICO_HOST', env('REVERB_HOST')),
        'puerto' => (int) env('REVERB_PUBLICO_PORT', env('REVERB_PORT', 443)),
        'esquema' => env('REVERB_PUBLICO_SCHEME', env('REVERB_SCHEME', 'https')),
    ],

];
