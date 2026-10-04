# Vale Cuatro

Truco argentino online, mano a mano: contra un bot o contra otra persona por un link de invitación.
La idea es que cualquiera que entre pueda jugar una mano contra el bot en menos de 30 segundos, sin registrarse.

Proyecto de portfolio de Román Gonzalez.

## Qué es real y qué es simulado

- **Simulado, y dicho abiertamente en el sitio:** los rivales bot y los jugadores de ejemplo del ranking, que aparecen marcados como bots.
- **Real:** el motor de reglas, el tiempo real por WebSockets, las colas, la API, el historial de partidas y los tests.

## Estado

El proyecto se construye por módulos. Hoy está terminado el primero.

| Módulo | Qué es | Estado |
|---|---|---|
| M0 | Identidad visual y maqueta de las pantallas | Listo |
| M1 | Cuentas y modo invitado | Pendiente |
| M2 | Motor de reglas en PHP puro | Pendiente |
| M3 | Mesa contra el bot | Pendiente |
| M4 | Bot con tres niveles | Pendiente |
| M5 | Dos personas en tiempo real | Pendiente |
| M6 | Tanteador, cantos y movimiento | Pendiente |
| M7 | Historial y repetición | Pendiente |
| M8 | Ranking y estadísticas | Pendiente |
| M9 | API pública | Pendiente |
| M10 | Cómo se juega | Pendiente |
| M11 | Calidad y publicación | En curso desde el primer commit |

En M0 las pantallas ya son las definitivas, pero muestran datos de ejemplo fijos.
La mesa se puede tocar para ver el movimiento, y todavía no tiene el motor de reglas detrás.

## Identidad

Todo sale del mundo del truco: la baraja española en tintas planas, el paño de la mesa del club y el tanteo con fósforos.

- **Mazo propio:** las 40 cartas están dibujadas en SVG para este proyecto. No hay 40 archivos: un componente arma cada carta con el símbolo de su palo.
- **Tanteador de fósforos:** los puntos se anotan en grupos de cinco, separando malas y buenas.
- **Cantos tipográficos:** cuando alguien canta, la palabra aparece enorme sobre la mesa.
- **Íconos propios:** dibujados con el mismo trazo que los fósforos. No se usa ninguna librería de íconos.

La página `/identidad` muestra logo, colores con su contraste medido, tipografías, mazo e íconos en uso.

## Stack

- Laravel 13 y PHP 8.3
- Blade, Tailwind CSS 4 y Alpine.js
- Animaciones con CSS y la Web Animations API, sin librerías
- PHPUnit y Laravel Pint, corridos por GitHub Actions en cada subida
- Tipografías Chivo y Piazzolla, alojadas en el proyecto

Más adelante se suman MySQL, Laravel Reverb y Echo para el tiempo real, colas, y Sanctum con OpenAPI para la API.

## Cómo correrlo

Hace falta PHP 8.3 o superior, Composer y Node 22.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Después, en dos terminales:

```bash
php artisan serve
npm run dev
```

El sitio queda en `http://127.0.0.1:8000`.

## Tests y estilo

```bash
php artisan test
vendor/bin/pint --test
```

## Decisiones

Las decisiones técnicas importantes están anotadas en [docs/decisiones.md](docs/decisiones.md): qué problema había, qué se eligió y qué se descartó.
