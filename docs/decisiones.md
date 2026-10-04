# Decisiones técnicas

Cada entrada dice qué problema había, qué se eligió y qué se descartó.

## M0. Identidad visual y maqueta

### El mazo es un componente, no 40 dibujos

- **Problema:** hacen falta 40 cartas propias y que todas se vean del mismo mazo.
- **Se eligió:** un componente Blade (`App\View\Components\Carta`) que recibe palo y número y arma el SVG. Los cuatro palos, las tres figuras y el dorso se dibujan una sola vez por página en un sprite y cada carta los reutiliza con `<use>`.
- **Se descartó:** 40 archivos SVG sueltos (difíciles de mantener parejos) y una imagen de mazo ya existente (la consigna pide dibujo propio).
- **Las figuras:** la primera versión del 10, el 11 y el 12 eran bustos y no se distinguían en chico. Se rehicieron de cuerpo entero, paradas sobre el marco y con el palo grande en la mano, cada una con una silueta propia: la sota flaca y de pie, el caballo con jinete (la única ancha) y el rey con túnica hasta el piso. La prueba fue distinguirlas a 60 px de ancho sin leer el número.
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

### Modo claro y oscuro sin colores nuevos

- **Problema:** la primera dirección visual decía "dos superficies, no dos temas". Después se decidió sumar modo oscuro, y había que hacerlo sin romper la identidad ni las cartas.
- **Se eligió:** separar los colores en fijos y cambiantes. Las cartas usan siempre `naipe` y `tinta`. Las páginas usan `fondo`, `texto`, `enlace`, `gana` y `pierde`, que cambian de valor con `data-modo="noche"` en el `<html>`. De noche la mesa baja un paso y Espada, Basto y Copa se aclaran, porque sin aclarar quedan cerca de 3 a 1 sobre Tinta. Un test mide las combinaciones de los dos modos.
- **Se descartó:** invertir todos los colores (las cartas quedaban negras) y una paleta nocturna nueva.
- **El cambio:** el botón es una carta que se da vuelta. La página nueva se abre en círculo desde ella con la View Transitions API; donde no existe, cambia de golpe. Un script mínimo en el `<head>` pone el modo antes de pintar, para que no parpadee.
- **Se descartó:** animar el color de cada elemento con transiciones, que se ve desparejo y traba en celulares.

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

## M1. Base y cuentas

### El ingreso está escrito a mano

- **Problema:** hacen falta registro, ingreso y salida, y poder explicar cada línea.
- **Se eligió:** tres controladores chicos (`RegistroController`, `SesionController`, `PerfilController`) sobre lo que ya trae Laravel: `Auth::attempt`, el cifrado de la contraseña y el límite de intentos. Al entrar se renueva el identificador de sesión y el error de ingreso es el mismo para un email desconocido que para una contraseña errada.
- **Se descartó:** Breeze y Fortify. Traen pantallas y estilos ajenos a la identidad, que había que rehacer igual, y más código del que se usa.

### El invitado es un jugador de verdad

- **Problema:** hay que poder jugar sin registrarse, y después las partidas (M3) y el canal en vivo (M5) necesitan saber quién es cada uno.
- **Se eligió:** una sola tabla `jugadores`. El invitado es una fila con un apodo sorteado ("Invitado 48213") y sin email ni contraseña. Si se registra, esa misma fila pasa a ser la cuenta y conserva lo jugado.
- **Se descartó:** guardar al invitado solo en la sesión (no se le puede asociar una partida ni autenticarlo en un canal) y una tabla aparte de invitados (obliga a migrar datos al registrarse).
- **Detalle:** no hay una columna "es invitado" que pueda quedar desactualizada. Es invitado quien no tiene email.

### Entrar a jugar es un POST

- **Problema:** el botón "Jugar contra el bot" puede crear un jugador. Si fuera un link, un buscador o la precarga del navegador fabricarían jugadores con solo visitarlo.
- **Se eligió:** un formulario sin campos que envía un POST a `/jugar`, con el control de seguridad de Laravel y un límite de diez por minuto. La mesa (`/mesa`) exige tener un jugador; sin él, vuelve a la portada.
- **Se descartó:** crear el invitado al abrir `/mesa`.
- **Detalle:** si la página quedó abierta hasta vencer la sesión, en vez del error 419 se vuelve atrás con un aviso.

### Los invitados que no vuelven se borran solos

- **Problema:** cada visita que aprieta "Jugar" deja una fila.
- **Se eligió:** `Jugador` usa `MassPrunable` y una tarea diaria (`model:prune`) borra a los invitados que llevan 30 días sin jugar. Cada vez que un invitado vuelve a jugar se le renueva el plazo.
- **Se descartó:** un comando propio para lo mismo.

### Los tests corren en SQLite y el sitio en MySQL

- **Problema:** el stack fija MySQL, pero atar los tests al MySQL de XAMPP los hace lentos y dependientes de la máquina.
- **Se eligió:** tests en SQLite en memoria (como en la integración continua) y el sitio en MySQL.
- **Cuidado:** MySQL compara los apodos sin distinguir mayúsculas ni acentos y SQLite sí los distingue. En producción "Román" y "roman" chocan, que es lo que se quiere; en los tests no.

### Sin "olvidé mi contraseña" por ahora

- **Problema:** recuperar la cuenta necesita mandar correos de verdad, y el proyecto todavía no tiene con qué.
- **Se eligió:** dejarlo afuera de M1 y, a cambio, que la contraseña se pueda ver mientras se escribe.
- **Queda pendiente:** sumarlo cuando haya hosting con correo (M11).

### Las pantallas de cuenta usan las piezas del sitio

- **Problema:** un formulario de ingreso es lo más genérico que tiene cualquier sitio.
- **Se eligió:** media pantalla es formulario y la otra media es mesa, de borde a borde. Los campos no llevan caja: los subraya un fósforo que se dibuja al entrar, el mismo gesto que los links. La contraseña se muestra u oculta con una carta chica que se da vuelta: de un lado tiene un ojo abierto y del otro un ojo cerrado, dibujados con el trazo de los fósforos. En el ingreso, la mesa muestra tu mano boca abajo y cada carta se da vuelta al completar un paso: el email, la contraseña y el envío. En registro y perfil, la mesa muestra el apodo en el tanteador mientras se escribe.
- **Se descartó:** la caja centrada con el logo arriba, y mostrar el error de ingreso como un canto "No quiero": Piazzolla es solo la voz de los jugadores y un error tiene que leerse sin vueltas. También se probó contar los caracteres de la contraseña con fósforos debajo del campo, y se sacó: no hacía falta.
- **El giro:** es una transición de CSS sobre `transform` (300 ms, la curva del sitio) y no una animación con keyframes, para que se pueda interrumpir: si se borra el email, la carta vuelve desde donde esté. La carta que ya corresponde dada vuelta al cargar la página sale así del servidor, sin animarse. Con movimiento reducido no gira: la cara aparece con un fundido de 150 ms.
