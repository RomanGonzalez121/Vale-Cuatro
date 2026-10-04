# Vale Cuatro

Truco argentino online, mano a mano: contra un bot o contra otra persona por un link de invitación.
La idea es que cualquiera que entre pueda jugar una mano contra el bot en menos de 30 segundos, sin registrarse.

Proyecto de portfolio de Román Gonzalez.

![La mesa de juego, con el canto "Truco" sobre el paño](docs/capturas/mesa.jpeg)

| Portada | Mazo propio |
|---|---|
| ![Portada](docs/capturas/portada.jpeg) | ![Las cartas del mazo](docs/capturas/mazo.jpeg) |

| Ranking | Repetición de una partida |
|---|---|
| ![Ranking](docs/capturas/ranking.jpeg) | ![Repetición jugada por jugada](docs/capturas/repeticion.jpeg) |

| Ingreso | Registro |
|---|---|
| ![Ingreso, con la mano que se da vuelta al completar cada paso](docs/capturas/ingresar.jpeg) | ![Registro, con el apodo en el tanteador](docs/capturas/registro.jpeg) |

## Qué es real y qué es simulado

- **Simulado, y dicho abiertamente en el sitio:** los rivales bot y los jugadores de ejemplo del ranking, que aparecen marcados como bots.
- **Real:** el motor de reglas, el tiempo real por WebSockets, las colas, la API, el historial de partidas y los tests.

## Estado

El proyecto se construye por módulos. Hoy están terminados los dos primeros.

| Módulo | Qué es | Estado |
|---|---|---|
| M0 | Identidad visual y maqueta de las pantallas | Listo |
| M1 | Cuentas y modo invitado | Listo |
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

Las pantallas ya son las definitivas. Las cuentas son reales: se puede registrarse, ingresar, cambiar el apodo o entrar a la mesa como invitado con un solo botón, sin llenar nada.
La mesa, el ranking y el historial todavía muestran datos de ejemplo fijos: la mesa se puede tocar para ver el movimiento, y no tiene el motor de reglas detrás.

## Cuentas e invitados

- **Un solo botón para jugar.** "Jugar contra el bot" crea un jugador invitado en el momento ("Invitado 48213") y lleva a la mesa.
- **El invitado es un jugador de verdad.** Vive en la misma tabla que las cuentas, y si se registra conserva su fila y lo que jugó. Los invitados que no vuelven en 30 días se borran solos con una tarea programada.
- **Ingreso escrito a mano,** sin kits: contraseña cifrada, sesión renovada al entrar, límite de intentos y el mismo error para un email desconocido que para una contraseña errada.
- **Formularios con las piezas del sitio.** Los campos se subrayan con un fósforo, la contraseña se cuenta en fósforos y la mano de cartas se da vuelta a medida que se completa el ingreso.

## Identidad

Todo sale del mundo del truco: la baraja española en tintas planas, el paño de la mesa del club y el tanteo con fósforos.

- **Mazo propio:** las 40 cartas están dibujadas en SVG para este proyecto. No hay 40 archivos: un componente arma cada carta con el símbolo de su palo.
- **Tanteador de fósforos:** los puntos se anotan en grupos de cinco, separando malas y buenas.
- **Cantos tipográficos:** cuando alguien canta, la palabra aparece enorme sobre la mesa.
- **Íconos propios:** dibujados con el mismo trazo que los fósforos. No se usa ninguna librería de íconos.
- **Modo claro y oscuro:** el club de día y de noche. Se cambia con una carta que se da vuelta, y las cartas no cambian nunca.

La página `/identidad` muestra logo, colores con su contraste medido, tipografías, mazo e íconos en uso.

## Stack

- Laravel 13, PHP 8.3 y MySQL
- Blade, Tailwind CSS 4 y Alpine.js
- Animaciones con CSS y la Web Animations API, sin librerías
- PHPUnit y Laravel Pint, corridos por GitHub Actions en cada subida
- Tipografías Chivo y Piazzolla, alojadas en el proyecto

Más adelante se suman Laravel Reverb y Echo para el tiempo real, colas, y Sanctum con OpenAPI para la API.

## Cómo correrlo

Hace falta PHP 8.3 o superior, Composer, Node 22 y MySQL (o MariaDB) con una base vacía llamada `vale_cuatro`.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

El `.env.example` trae los datos de un MySQL local sin contraseña; si el tuyo es distinto, cambiá las líneas `DB_` del `.env` antes de migrar.

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

Los tests corren en SQLite en memoria: no necesitan MySQL prendido.

## Decisiones

Las decisiones técnicas importantes están anotadas en [docs/decisiones.md](docs/decisiones.md): qué problema había, qué se eligió y qué se descartó.
