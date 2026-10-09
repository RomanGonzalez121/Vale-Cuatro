#!/bin/sh
# Las tareas programadas de Laravel: la limpieza de salas (cada cinco minutos) y la de invitados (una vez por día).
#
# Es un bucle y no `schedule:work` para no dejar un proceso de PHP esperando todo el día: en un contenedor
# de 512 MB, esa memoria la necesitan los que sí trabajan. Y corre cada cinco minutos y no cada minuto
# porque ninguna tarea es más frecuente: cada corrida arranca Laravel entero, y el procesador es una décima.
#
# Laravel decide qué toca mirando la hora: por eso se espera hasta el próximo minuto múltiplo de cinco
# (y diez segundos más, para caer adentro de ese minuto y no en el borde).

while true; do
    sleep $(( 300 - $(date +%s) % 300 + 10 ))
    php artisan schedule:run --no-interaction
done
