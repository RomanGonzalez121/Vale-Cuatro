# Decisiones técnicas

Cada entrada dice qué problema había, qué se eligió y qué se descartó.

## M0. Identidad visual y maqueta

### El mazo es un componente, no 40 dibujos

- **Problema:** hacen falta 40 cartas propias y que todas se vean del mismo mazo.
- **Se eligió:** un componente Blade (`App\View\Components\Carta`) que recibe palo y número y arma el SVG. Los cuatro palos, las tres figuras y el dorso se dibujan una sola vez por página en un sprite y cada carta los reutiliza con `<use>`.
- **Se descartó:** 40 archivos SVG sueltos (difíciles de mantener parejos) y una imagen de mazo ya existente (la consigna pide dibujo propio).
- **Detalle:** el marco de cada carta lleva la pinta de la baraja española, los cortes que identifican al palo: oro ninguno, copa uno, espada dos, basto tres.

### Los colores y las fuentes son tokens, y se borró la paleta de Tailwind

- **Problema:** que ninguna pantalla use un color o una tipografía fuera de la identidad.
- **Se eligió:** definir los nueve colores y las dos familias en `@theme` y borrar los valores que trae Tailwind (`--color-*: initial`, `--font-*: initial`). Una clase como `bg-red-500` directamente no existe.
- **Se descartó:** confiar en la disciplina de no usar los colores por defecto.

### El contraste se mide con código

- **Problema:** la dirección visual fija reglas de contraste WCAG y hay que poder demostrar que se cumplen.
- **Se eligió:** `App\Identidad\Paleta` calcula el contraste con la fórmula de WCAG. La página `/identidad` lo muestra y un test falla si una combinación en uso baja de su mínimo.
- **Se descartó:** medir a mano con una herramienta externa y anotar el número.

### Las fuentes van alojadas en el proyecto

- **Problema:** Chivo y Piazzolla están en Google Fonts, pero enlazarlas agrega un tercero y una conexión más antes de mostrar el texto.
- **Se eligió:** instalar los archivos con Fontsource y que Vite los sirva junto con el resto del sitio.
- **Se descartó:** el `<link>` a Google Fonts.

### Las animaciones no usan librerías

- **Problema:** el reparto, la carta que viaja a la baza, los cantos y los fósforos necesitan movimiento cuidado.
- **Se eligió:** transiciones CSS para lo que cambia de estado (cantos, fósforos, botones), animaciones CSS para lo que corre al cargar (el reparto de la portada) y la Web Animations API para lo que depende de posiciones medidas en el momento (el reparto de la mesa, la carta que viaja, el logo). Solo se animan `transform` y `opacity`.
- **Se descartó:** GSAP, Motion y similares. No hacen falta para este alcance y la consigna pide no usar librerías de animación.

### La maqueta usa las vistas definitivas

- **Problema:** hay una semana para todo el proyecto y hacía falta ver cómo queda el sistema antes de programar las reglas.
- **Se eligió:** armar las pantallas reales con datos de ejemplo fijos (`App\Maqueta\DatosDeEjemplo`). Cuando llega el módulo de cada pantalla se le conecta la lógica real y ese archivo se borra.
- **Se descartó:** una maqueta aparte para tirar después.
- **Cuidado:** en la maqueta, la mesa compara cartas en el navegador para que la muestra sea coherente. En M3 eso lo decide el motor del servidor.

### El código es compatible con PHP 8.3 aunque la máquina de desarrollo tenga 8.4

- **Problema:** el stack fija PHP 8.3 y el entorno local usa 8.4. Composer había resuelto dependencias que piden 8.4.
- **Se eligió:** fijar `config.platform.php` en 8.3 en `composer.json` y correr la integración continua en 8.3.
- **Se descartó:** subir el requisito del proyecto a 8.4.

### M0 corre con SQLite

- **Problema:** MySQL es parte del stack, pero M0 no guarda nada.
- **Se eligió:** dejar el SQLite que trae Laravel para sesiones y tests, y pasar a MySQL en M1, cuando aparecen las cuentas.
- **Se descartó:** configurar MySQL antes de necesitarlo.
