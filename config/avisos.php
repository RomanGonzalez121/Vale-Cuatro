<?php

return [

    /*
    | Los avisos que llegan al celular cuando no se está mirando la mesa (app/Avisos).
    |
    | Las claves con que se firman no están acá ni en el entorno: se generan solas la primera vez y
    | quedan en la base, con la privada cifrada (App\Avisos\Claves).
    */

    // Cuántos segundos pasan entre que a alguien le toca y que sale su aviso. Si en ese rato juega o
    // vuelve a la mesa, no se manda.
    'gracia' => (int) env('AVISOS_GRACIA', 5),

    // A quién escribirle si este sitio manda avisos de más: el estándar pide una dirección de contacto,
    // que solo ve el servicio de avisos del navegador. Por defecto, la del propio sitio.
    'contacto' => env('AVISOS_CONTACTO', env('APP_URL', 'https://vale-cuatro.onrender.com')),

];
