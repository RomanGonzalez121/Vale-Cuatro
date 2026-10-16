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

## Decisiones tomadas antes de empezar M2 y M11

### El motor piensa en asientos y equipos

- **Problema:** el proyecto empezó mano a mano, pero se sumaron el truco de a cuatro, los desafíos con manos armadas, los espectadores del torneo y la repetición. Un motor escrito para exactamente dos jugadores habría que reescribirlo.
- **Se eligió:** escribirlo desde el principio con asientos y equipos (el mano a mano es un asiento por equipo), con reparto reproducible (recibe el mazo mezclado o una semilla), con la posibilidad de arrancar desde una situación armada, y con una vista del estado por asiento que no trae las cartas ajenas. Los puntos de la partida son un parámetro.
- **Se descartó:** hacerlo para dos y adaptarlo después. Las señas quedan fuera del motor: son comunicación entre compañeros, no reglamento.
- **Costo:** M2 es más grande que en el plan original.

### Publicación en Render, plan gratis

- **Problema:** el plan gratis de Render se apaga a los 15 minutos sin visitas, no trae procesos de fondo ni tareas programadas, no ofrece MySQL y bloquea los puertos de correo. El proyecto necesita colas (el turno del bot), un programador (la limpieza), WebSockets (Reverb), MySQL y correo (recuperar la contraseña).
- **Se eligió:** un solo contenedor que corre la web, Reverb, el proceso de colas y el programador; MySQL en un proveedor externo con plan gratis; y el correo por la API web de un proveedor, no por SMTP.
- **Se descartó:** pasar el proyecto a Postgres (el stack es MySQL y la base gratis de Render se borra a los 30 días) y repartir las piezas en varios servicios (las horas gratis alcanzan para uno solo).
- **Queda pendiente para M11:** elegir el proveedor de MySQL y el de correo, medir si la memoria del contenedor alcanza y decidir qué se hace con la primera carga lenta.

### Pruebas de navegador en la integración continua

- **Problema:** la lógica de pantalla de la mesa no tiene tests; se comprobó a mano. Con más modos de juego, un cambio puede romper otro sin que se note.
- **Se eligió:** sumar Laravel Dusk en M11, para que la integración continua juegue sola una mano, se registre, ingrese y entre como invitado.
- **Se descartó:** seguir con comprobaciones manuales.

## M6. Tanteador, cantos y movimiento

### Los tantos del envido se cantan

- **Problema:** al quererse el envido, el resultado salía en una línea chica ("Envido: 28 a 25") mientras en el centro se veía el "Quiero" gigante y los puntos ya caían. No se entendía quién había ganado.
- **Se eligió:** cantarlos como en la mesa, con la misma pieza de los cantos y en Oro. Primero el mano dice su número; el otro contesta con uno mayor ("33 son mejores") o con "Son buenas", sin mostrar el suyo. Después el que pierde queda a media tinta, y recién ahí caen los fósforos. Mientras se canta tu tanto, las dos cartas que lo arman se levantan y la otra se apaga.
- **Se descartó:** un contador que sube de cero al número (es lento y es gesto de casino) y mostrar siempre los dos tantos (regala información que en la mesa real no se da).
- **Detalle:** la secuencia dura unos tres segundos y se puede apurar tocando la mesa. La pieza recibe los datos ya resueltos (quién es mano, los tantos, cuánto vale), así en M3 solo cambia de dónde vienen, y sirve igual para real envido, falta envido y flor.

### La mano se cierra antes de repartir

- **Problema:** al terminar una mano aparecía una frase chica y a los dos segundos se repartía de nuevo. No había cierre ni tiempo para leerlo.
- **Se eligió:** un cierre que dice quién ganó la mano, por qué (las bazas, un "no quiero", el mazo) y cuánto sumó cada cosa. Queda a la vista hasta apretar "Repartir", que reemplaza a los cantos en la barra de abajo y responde a Enter. Al repartir, las cartas de la mesa vuelven al mazo con una salida corta (200 ms, menos que la llegada).
- **Se descartó:** un cartel encima de las bazas (tapa justo lo que se quiere ver) y agregar una fila a la mesa (la mesa no hace scroll). El cierre ocupa el lugar de tu mano, que al terminar está vacío.
- **Sobriedad:** una mano termina unas veinte veces por partida, así que el cierre entra con un fundido de 200 ms y nada más. El gesto fuerte queda para el final de la partida.
- **Lo que no se jugó:** vuelve al mazo boca abajo, como en el truco real. La excepción son las cartas con las que el bot ganó el envido, que se dan vuelta: quien gana el tanto lo tiene que mostrar.
- **Queda pendiente:** entre dos personas (M5) no puede depender de un botón de uno solo: habrá que repartir cuando los dos estén listos o después de un tiempo.

### En cada baza, la carta que pierde se apaga

- **Problema:** el resultado de la baza solo se leía en un rótulo chico ("1ª baza, tuya").
- **Se eligió:** la carta que gana queda arriba y la que pierde baja su opacidad, el mismo criterio de las mesitas de "Cómo se juega".

### La mesa vacía tiene que verse calma

- **Problema:** antes de jugar la primera carta, lo que más se veía eran seis rectángulos punteados (dos por baza, encimados), "Sos mano" dicho dos veces, una raya suelta en el tanteador y el mazo pegado a las cartas del bot, como si fuera una cuarta.
- **Se eligió:** un solo lugar marcado por baza, con línea llena y tenue, y más visible el de la baza en juego; la marca se va cuando llega la primera carta. "Sos mano" queda solo en la marca que va junto a las cartas, y el aviso dice qué hacer. El tanteador dibuja muy tenues los fósforos que faltan, así se leen los grupos de cinco y la raya entre malas y buenas tiene sentido. El mazo va contra el borde del campo.
- **Queda pendiente:** en pantallas anchas el juego ocupa una columna angosta en el centro. Se reacomoda después de M3, cuando se sepa cuánto lugar piden la flor y las subidas del envido.

### La mesa no se corre cuando faltan cartas

- **Problema:** cuando tu mano o la del rival quedaban vacías, esas filas se achicaban y todo lo demás saltaba.
- **Se eligió:** cada lugar de tu mano conserva el alto de una carta y la fila del rival conserva el ancho de tres.

### Las pantallas de cuenta usan las piezas del sitio

- **Problema:** un formulario de ingreso es lo más genérico que tiene cualquier sitio.
- **Se eligió:** media pantalla es formulario y la otra media es mesa, de borde a borde. Los campos no llevan caja: los subraya un fósforo que se dibuja al entrar, el mismo gesto que los links. La contraseña se muestra u oculta con una carta chica que se da vuelta: de un lado tiene un ojo abierto y del otro un ojo cerrado, dibujados con el trazo de los fósforos. En el ingreso, la mesa muestra tu mano boca abajo y cada carta se da vuelta al completar un paso: el email, la contraseña y el envío. En registro y perfil, la mesa muestra el apodo en el tanteador mientras se escribe.
- **Se descartó:** la caja centrada con el logo arriba, y mostrar el error de ingreso como un canto "No quiero": Piazzolla es solo la voz de los jugadores y un error tiene que leerse sin vueltas. También se probó contar los caracteres de la contraseña con fósforos debajo del campo, y se sacó: no hacía falta.
- **En el celular:** la mesa quedaba debajo del formulario, fuera de la pantalla, así que la mano no se veía darse vuelta. Ahora va en una franja de paño entre el título y el formulario: las tres cartas en ingreso y el apodo en el tanteador en registro. Es un bloque más de la página: se probó dejarla pegada arriba al bajar y también fija, y Román prefirió que no se mueva. En el celular ya no hay mesa debajo del formulario.
- **En una ventana baja:** una notebook de 1366 x 768 deja unos 640 px de alto, y el formulario pasaba de ahí: la mano de cartas quedaba debajo del borde y no se veía darse vuelta. En escritorio, el tamaño del título, los márgenes y el ancho de las cartas se miden también contra el alto de la ventana, así el formulario y la mesa entran enteros sin bajar. En una ventana alta todo queda del tamaño de siempre. Se probó antes cambiar la estructura (mesa hasta el final de la página, mano fija al borde) y Román lo descartó: alcanzaba con achicar.
- **El giro:** es una transición de CSS sobre `transform` (300 ms, la curva del sitio) y no una animación con keyframes, para que se pueda interrumpir: si se borra el email, la carta vuelve desde donde esté. La carta que ya corresponde dada vuelta al cargar la página sale así del servidor, sin animarse. Con movimiento reducido no gira: la cara aparece con un fundido de 150 ms.

## M2. Motor de reglas

### Una partida no se modifica: cada acción devuelve otra

- **Problema:** la partida se va a guardar como una secuencia de eventos (M3), se va a poder repetir mano por mano (M7) y el bot va a necesitar probar jugadas sin romper nada (M4).
- **Se eligió:** que `Partida` sea inmutable. `repartir()` y `aplicar()` devuelven una partida nueva y dejan intacta la anterior. Reconstruir una partida es volver a aplicar sus repartos y sus acciones en orden; retroceder es aplicar menos. Hay un test que juega una partida entera, guarda los eventos, los pasa por JSON y comprueba que al aplicarlos de nuevo el estado es idéntico.
- **Se descartó:** un objeto que se va modificando, con un "deshacer" aparte. Son dos caminos que hay que mantener iguales.

### Lo que se puede hacer y lo que se rechaza salen de la misma cuenta

- **Problema:** el motor tiene que decir qué acciones son válidas (para dibujar los botones y para el bot) y además rechazar las que no lo son con un motivo. Si son dos pedazos de código distintos, tarde o temprano no coinciden.
- **Se eligió:** una sola función, `motivoDeRechazo()`, que devuelve por qué no se puede o `null` si se puede. `accionesPara()` es la lista de acciones cuyo motivo es `null`, y `aplicar()` tira `AccionInvalida` con ese mismo motivo. Un test prueba las 40 cartas y los 12 cantos desde cada asiento, en cada momento de varias partidas: todo lo que no figura como válido se rechaza con un texto y no deja rastro.
- **Los motivos** están escritos para mostrárselos al jugador ("Solo puede subir el canto quien tiene el quiero.").

### Siempre puede actuar un solo lado

- **Problema:** en la mesa real se puede cantar truco o irse al mazo mientras el otro piensa. En un servidor eso obliga a resolver quién llegó primero y cómo se pausa el tiempo del turno.
- **Se eligió:** en cada momento actúa un solo lado: el equipo que tiene que contestar un canto o, si no hay ninguno pendiente, el asiento al que le toca jugar. El truco se canta en el turno propio (decidido por Román). Irse al mazo sigue la misma regla.
- **Costo:** "irse al mazo en cualquier momento" queda como "en cualquier baza, cuando te toca actuar". No se puede abandonar mientras se espera al rival. Román lo confirmó al cerrar el módulo.
- **El envido está primero** no necesitó un estado propio. El canto pendiente se calcula mirando la contraflor, el envido y el truco en ese orden: mientras haya un envido sin contestar, el truco no aparece como pendiente, y vuelve solo cuando el envido se resuelve.

### Semilla para repetir, azar seguro para jugar

- **Problema:** los tests, los desafíos (M17) y la repetición (M7) necesitan que el mismo reparto salga siempre igual. Pero una semilla de 32 bits se puede adivinar: quien ve sus tres cartas podría probar las cuatro mil millones de semillas hasta encontrar la que las reparte, y con eso conocer las del rival.
- **Se eligió:** una sola clase, `Azar`, con dos orígenes. `Azar::deSemilla()` usa el generador Mt19937 de PHP y es reproducible. `Azar::seguro()` usa el azar del sistema operativo, y es el que tiene que usar una partida real en M3, que además guarda las cartas repartidas en el evento. El motor no elige: recibe el mazo ya mezclado.
- **La mezcla está escrita a mano** (Fisher-Yates) y el número al azar también, en vez de usar `shuffle()` o `Randomizer::shuffleArray()`. Así la misma semilla da el mismo mazo en cualquier versión de PHP. Hay un test con un reparto conocido que lo comprueba entre PHP 8.3 (integración continua) y la versión local.
- **El primer mano** se sortea afuera del motor, con el mismo `Azar`. El motor recibe quién es mano como dato.

### Los tantos los canta el motor

- **Problema:** querido el envido, cada jugador podría elegir qué decir. Eso suma un paso a cada envido y abre la puerta a decir un tanto que no se tiene.
- **Se eligió (decidido por Román):** el motor canta los tantos solo, en orden desde el mano, con el tanto real. Cada uno dice su número si supera al mejor cantado; si no, "son buenas", y ese tanto no sale del servidor. De a cuatro, si va ganando el compañero no se canta.
- **Se descartó:** que el jugador pueda entregar el envido teniendo más para no mostrar cartas.

### La flor se contesta en el turno propio y los puntos caen por posición

- **Problema:** la flor es opcional y se puede callar. Si después de un "Flor" el motor le abriera al rival un momento para contestar solo cuando tiene flor, esa pausa le avisaría a quien cantó que el rival tiene flor aunque la calle. Lo mismo pasaría si los 3 puntos cayeran antes o después según el rival tenga flor o no.
- **Se eligió:** no hay pausa. La contraflor se canta en el turno propio, antes de la primera carta, igual que la flor. Y los 3 puntos de una flor sin contestar caen cuando al rival ya no le queda nadie que pueda contestarla (todos jugaron su primera carta o se fueron), tenga flor o no. Hay un test que comprueba que el momento es el mismo en los dos casos.
- **Costo:** quien contesta con contraflor ya vio la primera carta del que cantó flor. Y si la mano se cierra antes de que le vuelva el turno (por ejemplo: cantó truco teniendo flor, le contestaron "flor" y después "no quiero"), no llega a cantar contraflor. Román lo confirmó: la contraflor que no se llegó a cantar se pierde.

### La vista por asiento es lo único que sale del motor hacia un jugador

- **Problema:** "las cartas del rival nunca llegan al navegador" no se puede sostener escondiéndolas en la pantalla: si viajan, se leen.
- **Se eligió:** `vistaPara($asiento)` arma un arreglo plano con las cartas propias, cuántas le quedan a cada uno, lo jugado, los cantos y las acciones válidas de ese asiento. Sin asiento es la vista de un espectador. `aArray()` es el estado entero y es solo para el servidor y los tests.
- **Cómo se prueba:** se juegan partidas enteras al azar, de a dos y de a cuatro, y después de cada paso se busca cualquier carta nombrada en la vista de cada asiento y del espectador. Solo pueden aparecer las propias, las jugadas y las que se muestran al cerrar la mano.
- **Lo que pasó** (`hechos()`) también es público: nunca nombra una carta que no se jugó ni un tanto que no se cantó.

### Partidas simuladas en lugar de una pantalla

- **Problema:** M2 no tiene nada para ver en el navegador, y la regla es no cerrar un módulo sin verlo funcionar.
- **Se eligió (acordado con Román):** la evidencia de M2 son los tests y un simulador que juega partidas enteras eligiendo al azar entre las acciones válidas, con semilla para poder repetir un fallo. Comprueba que toda partida termina, que gana uno solo y llega justo a los puntos, que el tanteo nunca baja y que siempre puede actuar un solo lado. El mismo simulador le sirve al bot de M4.
- **Un límite del azar:** la contraflor necesita flor en los dos equipos y repartiendo al azar casi nunca sale. Para cubrirla hay manos armadas con flor en todos los asientos, jugadas al azar.

### Lecturas del reglamento que hubo que hacer

El reglamento no decía qué pasa en estos casos y el motor necesitaba una respuesta. Cada una tiene su test. Román confirmó al cerrar el módulo las tres primeras; las otras tres son la lectura más directa de reglas que ya estaban fijadas.

- **Irse al mazo:** solo cuando te toca actuar (ver arriba).
- **Flor después del envido:** si el envido ya se quiso o no se quiso, no se puede cantar flor en esa mano. La flor anula un envido cantado y sin contestar, no uno ya jugado.
- **Flor y truco:** querido el truco no se canta una flor nueva, igual que el envido. Sí se puede contestar con contraflor una flor que el rival cantó antes.
- **Quién muestra cartas por la flor:** al cerrar la mano muestra sus tres cartas quien sumó por una flor sin contestar o ganó una contraflor querida. En una contraflor no querida nadie muestra.
- **Mazo con truco sin contestar en primera:** vale como "no quiero" (1 punto), sin el punto extra del envido.
- **Partida cerrada en la mitad de una mano:** si el envido o la flor llevan a alguien a los puntos de la partida, la mano se corta ahí y el truco no se anota.

### De a cuatro: lo que quedó hecho y lo que no

- **Hecho:** asientos intercalados, ronda desde el mano, tantos en ronda, cualquiera del equipo contesta y vale la primera respuesta, el quiero es del equipo, cada flor suma para su equipo, la contraflor compara la mejor flor cantada de cada equipo, y el mazo es de un jugador (la baza puede cerrarse con tres cartas). Hay una mano jugada de punta a punta y un test por cada una de esas reglas.
- **Lecturas provisorias, para M19:** quien se fue al mazo no canta tanto en el envido; las cartas que jugó antes de irse siguen valiendo en la baza; una flor ya cantada sigue compitiendo en la contraflor aunque su dueño se haya ido; y una contraflor vale lo mismo (6, 4 o la falta) aunque un equipo haya cantado dos flores.

## M3. Mesa contra el bot

### La partida se guarda como una lista de eventos

- **Problema:** hay que poder recargar la página y seguir, volver a ver una partida mano por mano (M7) y sacar estadísticas (M8). Si se guardara solo "cómo va", todo eso habría que guardarlo aparte.
- **Se eligió:** guardar la historia y no el estado. La tabla `eventos_de_partida` tiene cada reparto, con las cartas de cada asiento, y cada acción, en orden. El estado se arma aplicando esos eventos al motor de M2. La fila de `partidas` solo dice de quién es, quién fue mano en la primera mano, a cuántos puntos se juega y en qué quedó.
- **Se descartó:** guardar el estado entero en cada jugada (ocupa más y deja dos verdades que pueden no coincidir) y guardar la semilla del reparto en vez de las cartas (una partida real reparte con el azar seguro, que no tiene semilla).
- **Cómo se prueba:** después de cada pedido, lo que devolvió la mesa tiene que ser idéntico a lo que sale de leer los eventos de la base y aplicarlos de cero.
- **Costo:** cada pedido vuelve a aplicar toda la partida. Una partida entera son unos 200 eventos y el motor los aplica en milisegundos, así que no hizo falta guardar fotos intermedias.

### Un solo lugar por donde cambia una partida

- **Problema:** dos toques seguidos, o dos pestañas, podrían guardar la misma jugada dos veces o abrir dos partidas.
- **Se eligió:** todo lo que cambia una partida pasa por `App\Juego\Mesa`, dentro de una transacción que bloquea su fila. Los eventos van numerados y la base no acepta dos con el mismo número en la misma partida.
- **Una acción inválida no deja rastro:** el motor la rechaza con su motivo antes de guardar nada, y la mesa devuelve ese motivo para mostrarlo.

### El navegador recibe pasos, y solo la vista de su asiento

- **Problema:** una jugada del jugador puede desatar varias del bot (contesta un canto, juega su carta, canta). La pantalla tiene que poder contarlas de a una.
- **Se eligió:** cada pedido devuelve una lista de pasos. Un paso es la vista del asiento del jugador después de una jugada, con los hechos que ocurrieron. La pantalla no tiene reglas: recorre los hechos y los anima con las piezas que ya existían (reparto, carta que cae, canto, tantos, cierre).
- **Las cartas del bot no viajan:** lo único que sale del servidor es `vistaPara` del asiento del jugador. Hay tests que lo comprueban en las respuestas y en la página.
- **Las rutas no reciben un número de partida:** siempre trabajan sobre la partida en curso de quien hace el pedido, así nadie puede tocar la de otro.
- **La carta propia se mueve al tocarla,** sin esperar la respuesta: la jugada ya figuraba entre las válidas. Si igual volviera rechazada, la mesa se deshace sola y dice por qué.

### El bot de M3 es provisional

- **Problema:** los tres niveles del bot son de M4, pero M3 necesita un rival para poder jugar una partida entera.
- **Se eligió (decidido por Román):** un bot sencillo, `BotProvisional`. Con flor, la canta; canta envido con 29 o más y truco solo con una carta brava y una baza ganada; quiere si tiene con qué; gana la baza con la carta más baja que alcance. Responde en el mismo pedido, sin cola. M4 lo reemplaza cambiando una línea, porque la mesa solo conoce la interfaz `Bot`.
- **No hace trampa por construcción:** recibe la vista de su asiento, la misma que recibiría un jugador. Hay un test con un bot espía que lo comprueba.
- **Se descartó:** el azar puro (se iba al mazo o cantaba falta envido sin sentido) y adelantar la cola (sin tiempo real, el navegador tendría que preguntar cada tanto si el bot ya jugó).

### La barra de cantos muestra lo que vale, en una fila

- **Problema:** con el motor real puede haber hasta siete opciones a la vez, por ejemplo cuando te cantan truco en la primera baza y el envido está primero. La mesa no hace scroll y la barra no puede crecer.
- **Se eligió (decidido por Román):** una sola fila con lo que el motor declara válido en ese momento. "Envido" es un botón que cambia la barra por sus niveles y un "Volver". Si igual hay más de cuatro, los que sobran van detrás de "Más". En el celular, con cuatro botones o más se sacan los íconos y "Real envido" y "Falta envido" se escriben "Real" y "Falta", que es como se dicen en la mesa.
- **Contestando un canto no se ofrece el mazo:** no querer y después irse da el mismo resultado, y así entra todo en la fila. La acción sigue siendo válida para el motor.
- **Un detalle de Alpine:** `x-show` y `:style` en el mismo botón se pisan. Si el botón se ve y en qué orden va salen de un solo `:style`.

### "Jugar" retoma la partida sin terminar

- **Se eligió (decidido por Román):** con una partida en curso, "Jugar" vuelve a ella en el mismo punto. "Salir" ofrece seguir después o abandonar; abandonar agrega un evento y cierra la partida como perdida, sin borrar nada.
- **La mesa no se crea con un GET:** abrir `/mesa` sin partida en curso manda a elegir el modo. Las partidas nacen solo del botón "Jugar", que es un POST.

## M17. Modos de juego (adelanto de la pantalla)

### Los juegos están en la mano o en el mazo

- **Problema:** el sitio va a tener varios juegos (mano a mano, de a cuatro, torneo, desafíos y escalera) y hoy se juega uno solo. Hacía falta un lugar para elegir que no fuera una grilla de tarjetas iguales ni una ventana encima de la portada, y que no escondiera lo que falta.
- **Se eligió:** una página propia, `/modos`. Los juegos que ya se juegan están en la mano, cada uno con su carta. Los que faltan están en el mazo, apilado como en la mesa de juego, con sus nombres al lado. Cada módulo que se cierre reparte una carta del mazo a la mano.
- **Primera versión, descartada:** un abanico de seis cartas, con la que se jugaba cara arriba y cinco boca abajo, y una lista al lado. Román la vio y marcó tres cosas: cinco dorsos iguales no dicen nada, el abanico y la lista repetían lo mismo, y la carta elegida subía y bajaba al pasar el mouse. De ahí salió esta.
- **La promesa de los 30 segundos:** el botón grande de la portada sigue entrando directo a la mesa. El que abre esta pantalla es "Jugar" del menú, que por eso dejó de ser un formulario y ahora es un link.

### Se elige el juego y después el rival

- **Problema:** "Contra el bot" e "Invitar a alguien" figuraban como dos modos, pero son el mismo juego con distinto rival. Y de a cuatro y torneo también van a tener las dos versiones.
- **Se eligió:** ordenar por juego y, dentro de cada uno, elegir contra quién: bots o personas. El rival se elige con el mismo gesto que la navegación del sitio: el fósforo subraya la opción elegida. Un rival que todavía no se juega se puede mirar, dice "Todavía no se juega" y no tiene botón.
- **Los juegos son datos:** el catálogo está en `App\Juego\Modos`. Un rival se juega cuando tiene el texto de su botón; un juego está en la mano cuando se juega con al menos un rival. Cuando se termine un módulo alcanza con ponerle el botón: la pantalla reparte la carta sola. Hay un test que arma la pantalla con tres juegos en la mano, para saber que ese día va a andar.

### La carta de modo es un naipe con fósforos

- **Problema:** una carta del mazo no dice a qué se juega: un ancho de espada no significa "contra el bot".
- **Se eligió:** una carta propia para cada juego, con el mismo papel y el mismo marco que el resto del mazo, el ícono del juego en el lugar del palo y el nombre impreso. El ícono va dibujado como fósforos de verdad: el palito en Tinta y la cabeza en Copa, igual que en el tanteador. Mano a mano son dos fósforos; de a cuatro, cuatro.
- **Por qué no un color de palo:** los cuatro colores de palo tienen una función cada uno y no se usan de adorno. El rojo de la cabeza del fósforo no es Copa usado como decoración: es la pieza del tanteador.
- **Los trazos de los íconos** pasaron de la vista a `App\Identidad\Iconos`, porque ahora los dibujan dos piezas: el ícono suelto y la carta de modo.

### Las cartas no se mueven al elegir

- **Se eligió (decidido por Román):** la carta elegida queda a pleno y las demás se apagan, que es lo que ya hace el sitio con la carta que pierde una baza. No suben ni bajan, ni al elegir ni al pasar el mouse. Quedan el reparto al cargar y la carta que se da vuelta al final.
- **Un detalle que apareció al probar con tres cartas:** si las cartas van encimadas, una carta apagada se transparenta sobre la de al lado. Por eso, cuando hay varias, van separadas y el detalle del juego pasa abajo de la mano.

## El sitio en el celular (pasada del 5 de octubre de 2026)

### La portada entra en la primera pantalla

- **Problema:** en el celular la mano de cartas y el canto quedaban abajo de todo el texto: al abrir el sitio se veían un título y dos botones, y lo propio del sitio recién aparecía deslizando.
- **Se eligió:** en el celular la mano va entre el título y el botón, sin los dorsos del rival. La primera pantalla mide justo lo que queda entre el encabezado y la barra de abajo: el título arriba, las cartas al centro y "Jugar contra el bot" apoyado sobre la barra. El escritorio no cambió.
- **Cómo:** el bloque de texto se desarma en el celular (`display: contents`) y cada pieza toma su orden. No hay dos copias de la mano.
- **Se mide contra el alto de la ventana:** un celular con las barras del navegador a la vista deja unos 550 px de alto, y uno grande pasa de 800. El título, el ancho de las cartas y el canto salen de una cuenta sobre ese alto (`.portada-inicio` en `app.css`), así el botón no queda tapado en la pantalla baja ni flotando en la alta. Si la pantalla es muy baja, la frase de abajo del título le deja su lugar a las cartas. Probado a 360 por 520, 360 por 640, 390 por 760 y 412 por 860.
- **"Invitar a alguien" mentía:** era un segundo botón del mismo formulario y entraba a la mesa contra el bot. Ahora es un link a `/modos`, donde se elige el rival. Hay un test que cuenta un solo botón que entra a la mesa.

### Nada acompaña el scroll

- **Problema:** en el ranking, "Tu puesto" iba pegado al borde de abajo y tapaba la tabla mientras se recorría.
- **Se eligió (decidido por Román):** queda quieto al final de la tabla, en el celular y en el escritorio. Un test revisa que ninguna página de lectura tenga algo pegado o fijo. La única excepción es la barra de abajo del celular, que también decidió él.

### Las listas, compactas

- **Las cuatro que mandan** (portada, ranking y cómo se juega) van en un solo renglón también en el celular: el orden se lee de izquierda a derecha y ocupa la mitad. Debajo de cada carta, el número arriba y el nombre abajo.
- **Historial:** en el celular cada partida son dos renglones (resultado y "Ver de nuevo"; contra quién y cuándo). El tanteo en fósforos repetía el resultado y entra recién desde el ancho de tablet.
- **Repetición:** los tres controles van en un renglón, de igual ancho, para que no salten cuando el del medio cambia de texto.
- **Tabla del envido:** tenía un ancho mínimo y en el celular obligaba a deslizar de costado para ver cuánto vale cada canto. Ahora entra: el canto largo baja de renglón.

### El menú del celular es una barra abajo

- **Problema:** el menú era un botón arriba a la derecha que desplegaba la lista. Costaba un toque más para moverse, quedaba lejos del pulgar y no mostraba dónde estabas hasta abrirlo. Además, a 768 px el menú de escritorio no entraba y "Cómo se juega" se partía en tres renglones.
- **Se eligió (decidido por Román):** una barra fija abajo con cuatro lugares (Jugar, Reglas, Ranking e Historial), cada uno con su ícono propio. El lugar actual lleva el fósforo que subraya, igual que el menú de escritorio. Va hasta 1024 px; desde ahí manda el menú de arriba.
- **La cuenta queda arriba,** junto al modo de día y de noche, donde estaba el botón "Menú": con cinco lugares a 360 px un apodo largo no entraba.
- **Dónde no aparece:** en la mesa (ocupa toda la pantalla y no hace scroll) y en ingreso y registro, donde se escribe.
- **Lo que se sacó:** el botón de tres fósforos, el panel desplegable y sus estilos. En la portada del celular también salieron los dos links debajo del botón, porque repetían "Jugar" y "Reglas" de la barra.
- **La barra quedaba corta en el celular:** en el reparto de la portada las cartas llegan volando desde la derecha, y eso ensanchaba la página (472 px en una pantalla de 390). El celular achicaba todo para mostrarla entera y la barra, que mide el ancho real de la pantalla, no llegaba al borde. Ahora el contenido recorta lo que se sale por los costados (`overflow-x: clip` en `main`).
- **Sin barra de scroll donde hay barra de abajo:** en una ventana angosta de escritorio la barra de scroll de la página ocupa lugar y bajaba hasta el fondo, cortándole el borde derecho a la barra. Primero se probó pintar el último tramo del carril con el color de la barra; andaba en un navegador y en otro no, y se descartó. Ahora, hasta 1024 px y solo en las páginas con barra de abajo, la página no dibuja su barra de scroll. Se baja igual con el dedo, la rueda o el teclado, que es lo que pasa en cualquier celular. Desde 1024 px vuelve la píldora de siempre.

## La portada en una ventana baja de escritorio (5 de octubre de 2026)

- **Problema:** en escritorio la primera pantalla de la portada necesitaba 811 px de alto. En una notebook de 1366 x 768 el navegador deja unos 640: los botones quedaban cortados al borde y "Jugar contra el bot" no se veía entero sin bajar. La promesa del sitio es jugar en menos de 30 segundos, y el botón tiene que estar a la vista al abrir.
- **Se eligió:** por debajo de 820 px de alto, los márgenes, el título y la mano de cartas se miden contra el alto de la ventana (bloque `@media (min-width: 64rem) and (height < 51.25rem)` en `app.css`, con la cuenta explicada ahí). El título queda en tres renglones, sin partir "mano a mano.", y baja de 88 a 84 px; los dorsos, el canto y las cartas se achican juntos. Desde 820 px no cambia nada.
- **El bloque ocupa la primera pantalla entera (pedido de Román):** al entrar justo, la sección de los fósforos asomaba al pie como una franja de 25 px. Ahora el bloque mide lo que queda debajo del encabezado, con un tope de 960 px para que en un monitor muy alto no quede un paño vacío.
- **Se descartó:** achicar todo con un `transform: scale`, porque deja el texto borroso y los botones más chicos que el mínimo cómodo; y sacar el renglón de "Mirá cómo se juega", porque no hacía falta.
- **Medido** en un Chromium real, de 1024 x 700 a 2560 x 1300: hasta 1036 px de alto el bloque termina en el borde de abajo. Con menos de 600 px de alto hay que bajar un poco. Captura en `docs/capturas/portada-ventana-baja.jpeg`.

## M4. Bot

### Tres niveles que se distinguen jugando

- **Problema:** el bot de M3 era uno solo y muy callado. M4 pide tres rivales que se sientan distintos, y que el de arriba le gane al de abajo.
- **Se eligió:** tres clases que cumplen la misma interfaz, `Bot` (recibe la vista de su asiento y devuelve una acción). El enum `App\Juego\Nivel` dice cuál es cada una, cómo se llama y cómo se arma.
  - **Fácil** juega al azar con dos límites: no se va al mazo por su cuenta y siete de cada diez veces juega una carta en vez de cantar.
  - **Intermedio** juega bien y de frente (decidido por Román). Usa toda la escala cuando tiene con qué: envido con 27 o más, real envido con 31, truco con una brava bien acompañada, retruco y vale cuatro con cartas mejores. Nunca miente.
  - **Difícil** calcula probabilidades y miente cada tanto.
- **Sumar un nivel es un agregado:** un caso más en `Nivel` y su clase. Así va a entrar el nivel 4.
- **Se descartó:** un solo bot con un número de "habilidad" que mueve umbrales. Los tres no se diferencian por cuánto arriesgan sino por cómo deciden, y eso se explica mejor con tres clases cortas.

### Cómo saca las cuentas el Difícil

- **Problema:** no ve las cartas del rival, pero tiene que estimar si le conviene querer, cantar o guardar una carta.
- **Se eligió:** recorrer las manos que el rival puede tener. Las cartas que no vio son las 40 menos las suyas y las que el rival ya tiró; con esas se arman todas las manos posibles (7.770 al empezar) y se cuenta en cuántas gana. De ahí salen la probabilidad de ganar el envido y la de ganar la mano (`App\Juego\Probabilidades`).
- **La mano se juega hasta el final en cada caso:** para cada mano posible del rival, se juega lo que queda con las cartas a la vista y cada lado eligiendo su mejor carta. La carta que el bot tira es la que gana en más casos; si dos dan lo mismo, la más baja.
- **Un atajo que lo hace rápido:** para saber quién gana una baza solo importa si la carta del rival es más alta, igual o más baja que cada una de las propias. Con tres cartas propias hay siete lugares donde puede caer, así que las miles de manos se agrupan en unas ochenta, cada una con su cantidad. Además, una situación ya resuelta se guarda y no se vuelve a jugar. Una decisión tarda milisegundos.
- **Le cree al que canta:** si el rival cantó envido, las manos con buen tanto pesan más que las otras; si cantó truco, pesan más las manos en las que viene ganando. Sin eso querría cualquier canto con cartas medianas.
- **Decide por puntos esperados:** quiere cuando lo que espera ganar queriendo es más que lo que pierde seguro no queriendo. Si no querer ya le da la partida al rival, quiere siempre; si perder la mano ya es perder la partida, canta siempre.
- **Miente en dos lugares:** canta envido con poco tanto y canta truco con cartas flojas. Una de cada seis veces que podría, y una de cada tres cuando al rival le faltan cinco puntos o menos y va ganando. La frecuencia sale del `Azar`, así que con semilla se puede repetir.
- **La flor (decidido por Román):** Fácil e Intermedio la cantan siempre. Difícil la calla en un solo caso: le cantaron un envido que querido vale más de 3 y con sus dos mejores cartas tiene 32 o 33.
- **Se descartó:** tablas de fuerza hechas a mano (no se pueden defender con números) y mirar el estado completo de la partida (sería trampa: ver el punto siguiente).

### Ningún bot ve las cartas del rival

- **Problema:** un bot que corre en el servidor podría leer el estado entero. Tiene que jugar limpio y hay que poder demostrarlo.
- **Se eligió:** el bot recibe solo `vistaPara` de su asiento, lo mismo que recibe un jugador, y sus clases no importan nada de Laravel ni de la base.
- **Cómo se prueba:** se arman dos mesas iguales en todo lo que el bot puede ver y distintas en las cartas que el rival no mostró. Con la misma semilla, cada nivel decide lo mismo en las dos. Si alguno mirara esas cartas, en alguna decidiría otra cosa.

### Cuánto le gana cada nivel al de abajo

- **Medido el 5 de octubre de 2026,** bot contra bot sobre el motor, 400 partidas por cruce con semilla fija: Difícil le gana a Fácil el 92 %, Intermedio a Fácil el 74 % y Difícil a Intermedio el 82 %.
- **En los tests** se juegan 60 partidas por cruce (57, 47 y 53 ganadas) y los pisos son 48, 36 y 40: dejan margen para ajustar a los bots sin que el test se rompa por una partida.
- **Ninguna jugada inválida:** en cada partida simulada, cada decisión se compara contra las acciones que el motor declara válidas. Se cruzan todos los niveles, también cada uno contra sí mismo.

### El turno del bot sale de una cola

- **Problema:** en M3 el bot contestaba dentro del pedido del jugador. M4 pide que su turno salga de un job, con una demora corta para que parezca que piensa.
- **Se eligió:** cuando una jugada guardada deja el turno del lado del bot, la mesa encola `TurnoDelBot` con un segundo de demora, y recién después de confirmar la transacción. El job lleva solo el número de la partida. Al correr bloquea la fila, reconstruye la partida, comprueba que siga en curso y que le toque al bot, juega una sola acción y la guarda. Si le sigue tocando, se encola otro.
- **Un job repetido o tardío no hace nada:** como vuelve a mirar la partida antes de jugar, no importa si llega dos veces o después de tiempo.
- **La demora es de un segundo** porque la cola en base de datos cuenta segundos enteros. El proceso que la atiende corre con `--sleep=0.2` para no sumarle espera.
- **Se descartó:** dormir dentro del pedido (ocupa un proceso de PHP por cada jugador que espera) y encolar todas las jugadas del bot en un solo job (la mesa las recibiría de golpe).
- **En local hace falta un cuarto proceso:** `php artisan queue:work --sleep=0.2`, además de MySQL, `php artisan serve` y `npm run dev`.

### La mesa pregunta qué jugó el bot

- **Problema:** el bot ya no juega dentro del pedido, así que la respuesta a una jugada no trae las suyas. El tiempo real (Reverb) llega recién en M5.
- **Se eligió (decidido por Román):** una consulta corta. Cada paso lleva el número de su evento; mientras le toca al bot, la mesa pide cada 600 ms `GET /mesa/estado?desde=N` y recibe los pasos posteriores, que son la vista del jugador después de cada evento. Los cuenta con las mismas piezas de siempre: sigue sin haber reglas en el navegador.
- **La consulta mira la última partida, no solo la que está en curso:** la última jugada del bot puede ser la que cierra la partida, y la mesa tiene que enterarse.
- **Cuando llegue Reverb,** la consulta queda como plan B para cuando el WebSocket se corte.

### Una red de seguridad por si la cola no anda

- **Problema:** si el proceso que atiende la cola se cae, el bot no juega nunca y la mesa queda esperando. En Render va todo en un solo contenedor justo de memoria, así que puede pasar.
- **Se eligió (decidido por Román):** si pasan cinco segundos sin jugada, la mesa manda `POST /mesa/bot` y el servidor hace jugar al bot en el momento todo lo que le toque. Usa el mismo método que el job, así que si el job llega después no encuentra nada que hacer.
- **El camino normal sigue siendo la cola.** La red solo se nota como una espera más larga.

### El nivel se guarda en la partida y se elige en los modos

- **Se eligió (decidido por Román):** `partidas` tiene una columna `nivel_bot`. Se elige en `/modos`, con el mismo gesto que el rival; el botón de la portada no cambia y entra contra Intermedio. `POST /jugar` valida el nivel y rechaza uno que no existe sin crear nada.
- **Con una partida sin terminar** se retoma esa, con su nivel, aunque se pida otro. `/modos` lo avisa, apaga los niveles y el botón pasa a "Seguir la partida". Para cambiar de nivel hay que abandonarla desde la mesa.
- **En la mesa,** el nivel va escrito junto al rival ("Bot difícil") y mientras piensa aparece un reloj quieto al lado, que entra y sale con un fundido. No agrega una fila, así que la mesa sigue sin scroll.

### La dificultad se cuenta con fósforos

- **Problema:** el selector de nivel eran tres palabras, debajo de otras dos filas de texto. Román lo vio monótono.
- **Se eligió (decidido por Román):** contar la dificultad como se cuentan los puntos. Cada nivel lleva sus fósforos arriba del nombre: uno, dos y tres. Los del nivel elegido están puestos y caen de a uno al elegirlo, con el mismo movimiento que un punto del tanteador; los de los otros muestran solo su lugar. Es un componente, `<x-nivel-fosforos>`, y el mismo va en la mesa junto al nombre del rival.
- **No hay color ni pieza nueva:** el palito va en Fósforo y la cabeza en Copa, como en el tanteador, y son gruesos como los de la carta de modo. La marca de los que no están puestos es Naipe al 42 %, más visible que en el tanteador porque acá esa marca es la que dice cuántos tiene cada nivel.
- **Deja lugar para el nivel 4:** el cuarto fósforo cierra el cuadrado del logo.
- **Se descartó:** tres naipes chicos, uno por nivel (competían con la carta grande del modo y sumaban 70 px de alto) y cambiar solo tamaños de letra (seguía siendo todo texto).

### Los niveles no llevan el subrayado de los links

- **Problema:** cada nivel tenía dos gestos de fósforo a la vez. Arriba del nombre caían los fósforos que cuentan la dificultad y abajo se dibujaba otro, acostado: el subrayado de los links del menú. Decían lo mismo dos veces. Román lo marcó el 7 de octubre de 2026.
- **Se eligió (decidido por Román):** sacar el subrayado solo en los niveles (sigue en el menú y en "contra quién") y que el gesto sea uno. Al elegir, los fósforos caen de a uno y la cabeza de cada uno se enciende cuando el palito llega, igual que la cabeza del subrayado de los links. Los del nivel que se deja se apagan enseguida. Como todo botón lleva su hover, al pasar el mouse los fósforos del nivel se levantan apenas; en el celular, al apretar.
- **Cómo está hecho:** son transiciones de CSS sobre `transform` y `opacity`, no animaciones con cuadros, para que cambiar de nivel rápido no reinicie nada: cada fósforo sigue desde donde estaba. Con movimiento reducido aparecen todos juntos con un fundido y no se levantan.
- **Se descartó:** dejar los palitos siempre puestos y encender solo las cabezas (perdía el parecido con el punto que cae en el tanteador) y sacar la línea sin tocar la caída.
- **Medido** en un Chromium real: con tres fósforos, los palitos están puestos a los 280 ms y la última cabeza termina de encenderse antes de los 400 ms; no queda subrayado y a 360 px la página no se ensancha.

### Los modos en una ventana baja de escritorio

- **Problema:** al sumar los niveles, en una notebook de 1366 x 768 (unos 640 px libres) el botón de jugar quedaba debajo del borde.
- **Se eligió (decidido por Román):** lo mismo que en la portada. Por debajo de 820 px de alto, el margen de arriba, el título y el aire sobre la mano de `/modos` se miden contra el alto de la ventana. Desde 820 px no cambia nada.
- **Medido** en un Chromium real: en 1355 x 638 el botón termina a 577 px; en 1440 x 900, a 713; en un celular de 360 x 740, a 660, por encima de la barra de abajo.

### Lo que se vio en el navegador

- **Una partida entera contra cada nivel,** en un Chromium real a 360 x 740, tocando los botones de la mesa y con la cola corriendo: las tres llegaron a 30 (25, 31 y 20 manos; 178, 209 y 144 eventos), sin errores de JavaScript y sin scroll.
- **Lo que tarda el bot:** desde que el jugador tira una carta hasta que puede volver a jugar pasan unos 2 segundos (mediana de las tres partidas), contando el segundo de demora, la consulta y las cartas que caen.
- **La red de seguridad, probada:** con el proceso de la cola detenido, el bot igual jugó; la espera fue de unos 5 segundos más.
- Capturas en `docs/capturas/modos.jpeg`, `modos-celular.jpeg` y `mesa-bot-piensa.jpeg`.

## Nivel 4, "Ultra difícil" (después de M4)

### Los cuatro niveles salen del mismo molde

- **Problema:** al sumar el cuarto nivel a la fila de `/modos`, quedó desordenada. Cada nombre medía otro ancho ("Fácil" 37 px, "Ultra difícil" 87) con la misma separación entre todos, y los dibujos eran cuatro formas distintas: uno, dos y tres fósforos parados y un cuadrado. Para que entraran a 360 px hubo que achicar la letra y quedaron apretados. Román lo marcó el 7 de octubre de 2026.
- **Se eligió (decidido por Román):** que los cuatro salgan del mismo molde. Todos usan el mismo cuadrado, el grupo de cuatro del tanteador, con uno, dos, tres o cuatro fósforos puestos, que es como se anotan los puntos; el último nivel es el que lo cierra. Van en cuatro columnas iguales, con el nombre en una palabra (Fácil, Medio, Difícil, Ultra) y la letra en su tamaño de antes. El nombre completo va debajo, dicho igual que en la mesa ("Bot ultra difícil"), arriba de la frase.
- **Cómo se lee un nivel sin elegir:** la marca de los fósforos que tiene va en Naipe al 42 %, y la de los que le faltan para cerrar el cuadrado al 13 %, como en el tanteador. Así se cuenta cuántos tiene cada uno aunque no esté puesto.
- **Se descartó:** una lista de cuatro renglones (más prolija, pero sumaba unos 50 px de alto y en el celular el botón de jugar quedaba debajo de la barra) y emparejar solo los dibujos dejando los nombres completos (los anchos seguían desparejos).
- **Medido** en un Chromium real: a 360 px cada columna mide 80 px y la fila entra en un renglón, también a 320 px; el botón de jugar termina a 671 px en un celular de 360 x 740 (la barra de abajo empieza a los 676) y a 588 en 1355 x 638. En la mesa, "Bot ultra difícil" ocupa dos renglones a 360 px y la mesa sigue sin scroll.

### El bot que recuerda recibe las manos anteriores

- **Problema:** el nivel 4 tiene que llevar la cuenta de cómo juega el rival en la partida, pero el bot no tiene memoria: cada turno suyo es un job que reconstruye la partida, y la vista solo trae la mano en juego.
- **Se eligió (decidido por Román el 7 de octubre de 2026):** antes de decidir, la mesa le pasa al bot lo que su asiento vio al cerrarse cada mano anterior. Son las mismas vistas que recibe un jugador, armadas desde los eventos al reconstruir: no se guarda nada nuevo en la base y el motor no se toca.
- **Es una interfaz aparte, `Recuerda`,** con un solo método, `recordar()`. La mesa arma la historia solo para el bot que la implementa. Fácil, Intermedio y Difícil no la implementan, así que siguen decidiendo igual que antes y no pagan ese recorrido.
- **La garantía de las cartas ocultas se hereda:** una vista por asiento nunca trae cartas ajenas que no se jugaron ni se mostraron, y eso ya estaba testeado en el motor. Igual hay un test propio que juega partidas con un bot espía y revisa cada mano que se le recuerda contra el reparto guardado.
- **Se descartó:** que el motor arme un resumen público por mano (más prolijo de leer, pero obligaba a tocar el motor cerrado en M2 y a demostrar de nuevo que no se filtra nada) y guardar la memoria del bot en la base (sería estado duplicado: todo sale de los eventos).
- **En las tandas de bot contra bot** de los tests se hace lo mismo, para que lo que se mide sea lo que después juega en la mesa.

### La mesa contra el Ultra difícil baja un paso

- **Problema:** Román quería que jugar contra el nivel más alto se viera distinto, y la dirección visual no permite inventar colores, brillos ni nada en loop.
- **Se eligió (decidido por Román el 7 de octubre de 2026):** bajar el paño un paso con los tonos que ya existían. De día la mesa usa los de noche (paño `#153F32`, barras `#0E2B21`). De noche el paño toma el tono de las barras y las barras quedan igual, porque no hay uno más oscuro en la paleta: el campo se lee por su línea de borde.
- **Cómo está hecho:** dos reglas de CSS que cambian las variables del paño cuando la página tiene la mesa del Ultra (`body:has(.mesa-ultra)`). Nada más de la mesa se tocó.
- **Contraste, medido y testeado** (`Paleta::combinacionesDeLaMesaUltra()`): al oscurecer, todo el texto mejora. Naipe pasa de 7,7 a 11,2 de día y a 14,5 de noche; Oro, de 4,3 a 6,3 y a 8,1.
- **Se descartó:** un tono nuevo más oscuro para las barras de noche (era un color fuera de la paleta y el escalón que daba era de 1,2 a 1), un dorso propio para el rival, un color de paño nuevo y dejar solo los cuatro fósforos.
- **Lo que cuesta, medido:** la sombra de las cartas, que sigue siendo `#07180F` de noche, queda con contraste 1,21 sobre el paño de la mesa del Ultra de noche. En el resto de la mesa es 1,56 de noche y 1,88 de día. Más oscura no puede ser sin un color nuevo, así que se acepta: las cartas son de Naipe y se leen igual por su borde.
- **La barra del navegador acompaña sola:** al cargar la página, `modo.js` copia el fondo real de la página a `theme-color`, así que en la mesa del Ultra toma el paño nuevo sin cambiar nada más.

### El Ultra difícil es el Difícil con otros números

- **Problema:** el nivel 4 tiene que jugar mejor que el 3 sin hacer trampa. Copiar al Difícil y retocarlo dejaba dos bots casi iguales para mantener.
- **Se eligió:** que herede. `BotUltraDificil` extiende a `BotDificil` y no cambia ninguna decisión: cambia de dónde salen los números con los que el Difícil decide. Son cuatro métodos: la probabilidad de ganar el envido, la de ganar la mano, cuánto le cree al truco del rival y cuándo miente, más uno que dice cuánto se cuida según el tanteo. En el Difícil devuelven lo de siempre, así que sigue jugando exactamente igual (sus tests no se tocaron).
- **Se descartó:** un bot aparte con sus propias reglas (duplicaba casi todo el Difícil) y pasarle al Difícil un objeto de configuración (más piezas para lo mismo).

### Qué lee del rival, y de dónde lo saca

- **Los tantos que se dijeron** (`Deducciones::manosPosibles`). Si el rival dijo "28", sus tres cartas dan 28; si dijo "son buenas", su tanto no pasa del que se cantó antes. Al calcular quién gana la mano se cuentan solo las manos del rival que cierran con eso. Ejemplo del test: el rival dice 33 y el bot tiene el 7 de oro y el 6 de espada, así que ese 33 es de copa o de basto, dos cartas bajas; el Ultra contesta el truco subiendo y el Difícil no.
- **Lo que no se cantó** (`Deducciones::seCalloElEnvido`). Si el rival ya tiró su primera carta sin cantar envido, las manos con tanto para cantar pesan menos, y el bot canta envido con menos.
- **Cómo viene jugando en la partida** (`Perfil`), con las manos que la mesa le recuerda. Son cuatro números: de los envidos que abre, cuántos canta sin tanto; con tanto, cuántas veces se lo calla; de los trucos que cantó y se jugaron hasta el final, cuántos ganó; y de los cantos que le hizo el bot, cuántos no quiso.
- **Una partida tiene pocas manos,** así que cada número arranca en lo que supone el Difícil y esa suposición pesa como cuatro manos vistas. Con dos o tres manos casi no se mueve; recién manda lo visto cuando hay bastantes más. Al truco nunca se le cree menos de la mitad ni más del doble que a un rival cualquiera.
- **El tanto del rival solo se usa cuando se supo:** porque lo dijo o porque jugó sus tres cartas. Un envido no querido no enseña nada (test).
- **La mentira deja de ser fija.** El Difícil miente una de cada seis veces que podría. El Ultra, una de cada tres contra un rival que se va mucho y una de cada diez contra uno que quiere todo.
- **El tanteo:** ganando por seis o más pide cinco puntos más de probabilidad antes de cantar; perdiendo por seis o más, cinco menos.
- **Las cuentas de la mano tienen ahora dos caminos:** el rápido, que agrupa las cartas sin ver, y el que recibe las manos posibles una por una. Un test comprueba que con todas las manos dan lo mismo.

### Revisión de código antes de subir (8 de octubre de 2026)

- **Corregido:** el perfil contaba como "se fue" cualquier envido del bot con estado distinto de `null`, incluidos `anulado` (por una flor) y `pendiente`. Ahora solo cuentan `querido` y `no_querido`. De paso, un truco que el rival rechaza yéndose al mazo también cuenta como que se fue (antes solo el "no quiero"). Dos tests nuevos.
- **Verificado:** el Difícil decide igual que antes del refactor. Se jugaron 60 partidas del Difícil contra el Intermedio con semilla fija, con el código del commit anterior (`29efcb5`) y con el actual: las dos dieron 8.471 jugadas con la misma huella. No se dejó como test permanente: fijar sus decisiones obligaría a romper el test cada vez que se quiera ajustar al bot, y los tests de niveles ya dejan margen para eso.
- **Descartado como error:** que la barra del navegador no acompañe a la mesa del Ultra (ver arriba).
- **Remedido con el código final:** 416 de 800 en el test del margen, el 52,0 %. Antes de corregir el perfil había dado 417 (52,1 %). Pasa, pero con el piso exacto: una partida menos y el test fallaría. Es lo que hay que saber del margen del 52 %: es el que Román fijó y el Ultra lo alcanza raspando en esas semillas; las 2.000 partidas con otras semillas dieron 53,3 %.
- **Dejado así, a propósito:** (1) `Mesa::manosCerradas()` vuelve a leer los eventos en cada turno del Ultra, un recorrido de unos 185 eventos al lado de las cuentas del bot, que tardan segundos. (2) En `Deducciones::tantoDicho`, un "son buenas" sin número antes no se da en el mano a mano (el que habla primero siempre dice su número); importa recién en el de a cuatro (M19). (3) El test del margen del 52 % tiene un margen de unas pocas partidas y no corre en la integración continua (tarda 11 minutos): es una medición para correr a mano antes de tocar al bot.

### Cuánto le gana al Difícil, medido

- **Cómo se midió:** bot contra bot sobre el motor, 400 partidas con semilla fija (semillas 1000 a 1399), turnándose el asiento.
- **Solo con las deducciones de los tantos:** 215 de 400, el 53,8 %.
- **Con todo (deducciones, perfil del rival, tanteo y mentira variable):** 213 de 400, el 53,2 %.
- **Qué dice ese número:** con 400 partidas el margen de error es de unos 5 puntos, así que un 53 % todavía no se distingue de un empate. Y el perfil del rival no sumó nada medible contra el Difícil. Es esperable por dos motivos: entre dos bots fuertes pesan mucho las cartas, y el Difícil juega parecido a lo que el Ultra ya suponía de un rival cualquiera, así que aprenderlo cambia poco.
- **Con más partidas (8 de octubre de 2026):** 1.600 partidas nuevas, en dos tandas de 800 con semillas desde 2000 y desde 3000: 425 y 428 ganadas, el 53,3 % entre las dos. Sumadas a las 400 de arriba son 1.066 de 2.000, el **53,3 %**, con un intervalo del 95 % de 51,1 a 55,5. Tres tandas con semillas distintas dieron lo mismo (53,2, 53,1 y 53,5).
- **Qué dice ese número:** es real, no es suerte: le gana más de la mitad de las veces y el intervalo no llega a 50. Pero es chico: más o menos una partida de cada treinta que el Difícil no ganaría. Para comparar, el Difícil le gana al Intermedio el 82 %, y entre niveles de tanta distancia el salto se nota jugando; este no.
- **Qué lectura aporta (8 de octubre de 2026):** para no depender de la suerte de las cartas, cada reparto se jugó dos veces con los asientos cambiados (partidas en espejo). Eso baja el error de 1,8 a unos 0,9 puntos con 800 partidas, pero no a cero. Resultados, todos contra el Difícil con las mismas semillas:

  | Variante | Le gana | Error |
  |---|---|---|
  | Control: Difícil con otro azar, sin ninguna lectura | 48,4 % | 0,8 |
  | Solo los tantos dichos | 48,9 % | 0,9 |
  | Solo el silencio (no cantó envido) | 49,8 % | 0,8 |
  | Solo el perfil del rival | 49,9 % | 1,0 |
  | Solo el tanteo | 47,8 % | 0,8 |
  | Ultra completo | 52,1 % | 1,0 |

- **Cómo se lee:** el control, que juega igual que el Difícil, dio 48,4 y no 50: ese es el piso de ruido, unos 1,6 puntos. Contra su propio control el Ultra completo suma 3,7, parecido al 3,3 de las 2.000 partidas sin espejo. Ninguna lectura suelta se distingue del ruido: el silencio y el perfil suman 1,4 y 1,5, los tantos dichos 0,5 y el tanteo solo da -0,6. La suma de las partes (2,8) se acerca al total, así que la ventaja viene repartida y no de una sola lectura.
- **Para separarlas con certeza** hacen falta unas 3.000 partidas por variante (cerca de 40 minutos cada una). No se hizo: lo que se vería es una diferencia de uno o dos puntos entre piezas.
- **Cerrado:** Román fijó el margen en 52 % y el nivel se subió a GitHub el 8 de octubre de 2026 (ver "Revisión de código antes de subir", más arriba, y el test `MedicionDelUltraDificilTest`).

## M5. Dos personas en tiempo real

### Reverb en la rama principal, porque la estable obliga a una dependencia con avisos de seguridad

- **Problema:** `composer require laravel/reverb` instaló `dev-main` y no una versión estable. La última estable (v1.12.0) exige `guzzlehttp/psr7 ^2.6`, y Laravel 13 ya había traído `guzzlehttp/guzzle` 8, que pide psr7 3.x.
- **Se verificó:** bajar a psr7 2.x no es una opción, porque composer bloquea todas sus versiones por avisos de seguridad (y las de Guzzle 7 que Laravel también admite). Para usar la estable había que silenciar esos avisos.
- **Se eligió:** `laravel/reverb: dev-main`, que admite psr7 2.x y 3.x. `composer.lock` fija el commit exacto (`74c8c4082c`), así que cada instalación trae lo mismo, y `composer audit` no encuentra nada.
- **Se descartó:** silenciar los avisos de seguridad y forzar versiones viejas de Guzzle.
- **A revisar:** cuando Reverb publique una versión estable compatible con psr7 3, pasar a esa (`composer require laravel/reverb:^1.13`).

### Echo se conecta solo donde se necesita

- **Problema:** el instalador de Laravel carga Echo en `app.js`, o sea en todas las páginas. Cada visita a la portada o al ranking abriría un WebSocket, y fallaría con errores en la consola si Reverb no está prendido.
- **Se eligió:** `resources/js/echo.js` exporta `conectarEcho()`, que crea la conexión la primera vez que alguien la pide. La mesa y la sala la piden; el resto del sitio no. El paquete principal no cambió de tamaño (77 kB).

### La mesa sabe en qué asiento está cada persona

- **Problema:** `Mesa` daba por hecho que quien juega es el asiento 0 y el rival el 1, que es el bot. Para dos personas, cada una tiene que mirar y mover desde su asiento.
- **Se eligió:** los métodos de `Mesa` (`vista`, `pasosDesde`, `actuar`, `repartir`, `abandonar`) reciben el asiento. Contra el bot se puede omitir y vale el 0, así que ningún llamador ni test de antes cambió. **Entre personas, omitirlo falla con un error** en vez de suponer un asiento: suponer uno dejaría a una persona mirando o moviendo las cartas de la otra por un descuido. Contra el bot, pedir el asiento 1 también falla: es del bot.
- **El asiento sale del servidor.** El controlador lo calcula con `Partida::asientoDe($request->user())` y nunca lo lee del pedido. Un test manda `asiento` del otro jugador en el cuerpo y comprueba que se ignora; otro comprueba que un tercero recibe 409 en todo.
- **El bot no se activa entre personas:** `despuesDeJugar` no encola turnos y `turnoDelBot` devuelve `false`, aunque alguien lo pida. Un test lo comprueba con la cola simulada.
- **Probado una partida entera,** jugada al azar por los dos asientos y repetida hasta revisar 400 pasos: ningún paso que recibe un asiento nombra una carta del otro que no se haya jugado ni mostrado, ni lo que recibe quien juega ni lo que se entera el otro. Es el mismo criterio del test de M3, ahora en las dos direcciones.
- **Se descartó:** guardar el asiento en la sesión o mandarlo desde el navegador (se puede falsificar), y dos clases de mesa, una por tipo de partida (duplicaba toda la lógica de eventos).

### El WebSocket avisa, no cuenta

- **Problema:** el criterio del módulo es que las cartas del rival nunca lleguen al navegador del otro, ni por HTTP ni por los eventos emitidos. Mandar la vista de cada asiento por el WebSocket habría obligado a probar y vigilar dos caminos por donde pueden filtrarse cartas.
- **Se eligió:** el evento `PartidaActualizada` lleva solo el número del último evento, por un canal privado `partida.{id}`. Quien lo recibe pide lo que pasó por la consulta HTTP de siempre, que ya arma la vista de su asiento. Las cartas viajan por un solo camino, el que ya estaba probado.
- **Un canal por partida y no por asiento** (cambio respecto de lo que se le contó a Román al empezar): como el aviso no lleva nada que un jugador no deba ver, un canal para los dos alcanza y es más simple. Entran los dos asientos (quien abrió la sala, desde que espera, y quien se sentó) y nadie más: se prueba por la ruta de autorización con quien creó, quien se sentó, un tercero, sin sesión, una partida contra el bot y una que no existe. Para las señas de M19 se va a necesitar un canal por equipo, pero eso es otra cosa.
- **Medido en un navegador real:** por el WebSocket pasaron solo `pusher:subscribe` (con la firma de autorización) y `partida.actualizada` con `{"evento":1}`. Quien espera en la sala ve llegar al rival a los 1,2 segundos; sin el aviso, la consulta cada 4 segundos tardaba cerca de 4.
- **Un fallo del tiempo real no puede romper una jugada.** Sin cuidado, si Reverb está caído el aviso falla después de guardar la jugada y quien jugó ve un error aunque su jugada entró. `PartidaActualizada::avisar()` lo manda cuando se confirma la transacción y dentro de un `try`: si falla, se anota con `report()` y la jugada sigue. Test con un emisor que siempre falla. También se prueba que un aviso de una transacción que se deshizo nunca sale.
- **Un aviso por evento guardado,** y solo entre personas: contra el bot no se emite nada. Test: en partidas enteras, la cantidad de avisos es igual a la de eventos guardados, y ninguno trae una carta.
- **La sala sigue preguntando por su cuenta:** cada 15 segundos con el WebSocket andando y cada 4 si no, y apenas se conecta o vuelve a estar a la vista. Así funciona también sin Reverb.
- **Se descartó:** mandar la vista de cada asiento por el WebSocket, y la cola para los avisos (sumaba la demora del proceso de la cola a algo que tiene que ser inmediato; con `ShouldBroadcastNow` sale en el momento).
- **En los tests,** el emisor de avisos es el nulo (`phpunit.xml`). Las pruebas de autorización cambian a Reverb con claves de mentira y vuelven a registrar los canales, que se asocian al emisor que está activo al arrancar la aplicación.

### Los plazos los resuelve el servidor

- **Decidido por Román el 8 de octubre de 2026:** el turno dura 45 segundos; al vencer, quien tenía el turno se va al mazo solo; con 3 vencimientos seguidos pierde la partida. Con la mano cerrada, la siguiente se reparte sola a los 6 segundos, y tocar la mesa lo apura. La regla de los 3 vencimientos es del sitio: no sale del reglamento del truco.
- **Qué se hace al vencer:** se manda al mazo, que es una jugada que el reglamento ya tiene (con un canto sin contestar vale como no querer y perder la mano). No se inventa regla. Si en ese momento el mazo no estuviera entre las acciones se prueba "no quiero", y si tampoco, lo primero que el motor permita: la partida no puede quedar trabada.
- **Una columna dice cuándo vence lo que se espera:** `partidas.plazo_vence_en` (el turno o el reparto, o nulo si no hay nada que esperar). El trabajo en cola `ResolverPlazo`, la cuenta regresiva de la mesa (`restan` en cada paso) y la red de seguridad leen esa misma columna. Se descartó calcular el plazo desde la hora del último evento: con el redondeo de las horas en la base y la cola, el trabajo podía llegar un segundo antes del plazo, no encontrar nada que resolver y dejar la partida esperando para siempre. Por eso el plazo se guarda en segundos enteros y el trabajo sale exactamente a esa hora.
- **Es seguro llamarlo de más.** `Mesa::resolverPlazo()` no hace nada antes del plazo, ni si desde que se encoló el trabajo pasó algo (alguien jugó o apuró el reparto: lleva el número del último evento que había). Por eso con la cola sincrónica de los tests los trabajos son inofensivos.
- **Un vencimiento es un evento propio** (`vencimiento`), con la jugada que hizo el servidor. Para el motor es una acción más, y en el historial se distingue de lo que eligió la persona. Con 3 seguidos se guarda un `abandono` con el motivo `vencimientos`. Los vencimientos seguidos se cuentan por asiento, desde su última jugada propia hacia atrás: lo que hace el otro no corta la cuenta, y una jugada propia sí.
- **Red de seguridad:** `POST /mesa/plazo`. La mesa lo pide cuando su cuenta llegó a cero y el servidor no resolvió la espera (cola caída o atrasada). Lo puede pedir cualquiera de los dos y el servidor decide; un tercero no puede.
- **A quien vuelve se le cuenta qué pasó:** "Tu rival abandonó la partida: ganaste.", "Tu rival dejó de jugar y perdió la partida." y, a quien perdió por vencimientos, "Perdiste la partida: se te venció el turno 3 veces seguidas.". Quien abandonó a propósito no recibe aviso, ni una sala cancelada.
- **Probado con la cola real y el MySQL real:** reparto a las 07:53:39; a las 07:54:24, 45 segundos después, el servidor mandó al mazo al asiento 0; a las 07:54:30, 6 segundos más tarde, repartió la siguiente mano y quedó corriendo el plazo nuevo.

### La mesa del navegador para dos personas

- **Problema:** `mesa.js` daba por hecho que "vos" sos el asiento 0 y que enfrente hay un bot: unos 25 lugares con el asiento fijo, 15 textos con "el bot" y una espera que le pregunta al servidor cada 600 ms hasta que juegue. A una persona no se la puede esperar así: puede tardar todo su turno.
- **Vos y el rival, no asientos.** La pantalla sigue hablando de "vos" y "rival"; qué asiento es cada uno sale de `vista.asiento`. Contra el bot da el 0 de siempre y no cambió nada. Es lo mismo que va a necesitar M19.
- **Los textos nombran al rival:** "el bot" o el apodo de la otra persona. Dos excepciones medidas a 360 px con el apodo más largo que acepta el sitio (20 letras): el renglón de cada baza dice "del rival" y el título del cierre dice "Perdiste la mano", porque con el apodo ocupaban tres renglones. En el tanteador el apodo se corta con puntos suspensivos y junto a las cartas ocupa dos renglones como mucho.
- **La mesa vigila en vez de preguntar sin parar.** Con otra persona, `vigilar()` deja programada la próxima pregunta y `ponerseAlDia()` la hace. Se pregunta por tres motivos: llegó un aviso por el WebSocket, se cumplió el plazo de lo que se espera, o pasó el tiempo de respaldo (15 segundos con el WebSocket andando, 3 sin él). El aviso trae el número del último evento: si la mesa todavía no lo mostró, pregunta apenas queda libre.
- **Las carreras, pensadas una por una.** Mientras se cuenta una jugada propia no se pregunta (ese pedido trae lo suyo), y si en el medio de una pregunta la persona juega, la respuesta se descarta. Si la jugada propia no entra porque el servidor llegó antes (se le venció el turno, o ya se había repartido), la mesa devuelve a la mano la carta que se había adelantado y cuenta lo que pasó de verdad, en vez de mostrar un error.
- **El paso dice si hubo un vencimiento** (`vencio`, el asiento al que se le acabó el tiempo). Sin eso la mesa contaba "Te fuiste al mazo", que no es lo que pasó: ahora dice "Se te venció el turno." Sale del tipo del evento, así que al recargar la página se lee igual. No lleva cartas. Test en `PlazosTest`.
- **La cuenta regresiva es un fósforo acostado en el borde del campo,** contra la barra de quien tiene que jugar: arriba, bajo el tanteador, cuando le toca al rival; abajo, sobre los cantos, cuando te toca a vos o cuando falta para el reparto automático. El palito se consume con `scaleX` y la cabeza viaja con la punta sin achicarse; los dos con la Web Animations API y en línea recta, porque es un tiempo que corre y no una entrada. No ocupa lugar, así que la cuenta de `--b` no cambió. Primero se probó sobre la línea fina del campo: en el celular quedaba bien, pero en una ventana baja de escritorio (1355 x 638) las cartas de la mano llegan hasta esa línea y lo tapaban. Con movimiento reducido da un salto cada cinco segundos. Cuando al turno propio le quedan 10 segundos, el aviso lo dice: el dibujo no es la única señal.
- **El plazo se mide desde que llega el paso,** no desde que se termina de contar en pantalla: lo que tarda una animación no le regala tiempo a nadie. Si la cuenta llega a cero y pasan 2 segundos sin novedades, la mesa pide `POST /mesa/plazo` (como mucho una vez cada 5 segundos).
- **El aviso mide siempre un renglón.** Con un apodo largo algunos avisos ocupan dos, y cada vez que pasaba las bazas se movían. Ahora el renglón de más crece hacia arriba, sobre el paño libre. De paso dejó de correrse la mesa al cerrar cada mano, que ya pasaba contra el bot.
- **Se descartó:** dos archivos de mesa, uno por tipo de partida (duplicaba todas las piezas visuales); esperar al rival trabando la mesa como con el bot (no dejaba apurar el reparto ni enterarse de un vencimiento propio); y un canal o un pedido distinto para la cuenta regresiva (alcanza con el `restan` que ya traía cada paso).

### El reloj espera a quien abrió la sala

- **Problema:** al sentarse el invitado se reparte y corre el primer turno. Pero quien abrió la sala puede no estar mirando: en el celular lo normal es copiar el link, pasar a otra aplicación a mandarlo y que la otra persona entre enseguida. Con 45 segundos por turno perdía una mano, y en dos minutos y medio la partida, sin haberla visto.
- **Decidido por Román el 8 de octubre de 2026,** entre tres opciones: mientras quien abrió la sala no tiene la mesa a la vista, su turno espera 3 minutos (`SEGUNDOS_DE_LLEGADA`) en vez de 45 segundos. Cuando su mesa está a la vista, avisa (`POST /mesa/presente`) y, si le toca, el turno arranca de cero con el plazo de siempre. El invitado lee "Esperando que Fulano llegue a la mesa". Descartó alargar solo el primer turno de la partida (no cubre el caso de que empiece el invitado) y dejarlo como estaba.
- **La espera larga es una sola.** Cuenta como haber llegado avisarlo, haber jugado o que ya se le haya vencido un turno: después de eso el plazo es el de siempre. Así el invitado espera como mucho 3 minutos una vez, y no 3 minutos por turno.
- **La llegada es un evento de la partida** (`llegada`) y no una columna. Tres motivos: el rival se entera por el mismo camino que de una jugada (el aviso del WebSocket y la consulta), los números de evento siguen diciendo qué mostró cada mesa, y queda en la historia de la partida. Para el motor no es nada: el paso de una llegada no trae hechos, solo el plazo nuevo. La mesa de quien llega recibe ese paso en la misma respuesta, así queda al día antes de poder jugar.
- **Solo cuenta con la pestaña a la vista.** En el escritorio la sala puede pasar sola a la mesa en una pestaña tapada: ahí no avisa, y lo hace cuando la persona vuelve.
- **Al invitado no se le regala tiempo:** si quien abrió la sala llega cuando le toca al otro, ese plazo no cambia de hora. Como el trabajo en cola lleva el número del último evento, se deja otro a la misma hora y el anterior ya no hace nada.
- **Se descartó** medir la presencia con la conexión del WebSocket (un canal de presencia): en el celular la conexión queda viva unos segundos con la pantalla ya tapada, y habría sumado otra cosa que viaja por ahí.

### Lo que encontró la revisión de código de M5

La revisión no encontró ningún camino por el que las cartas del rival lleguen al otro navegador. Encontró esto, y se corrigió todo:

- **La mesa seguía la partida equivocada.** La consulta buscaba "la última partida del jugador" por número. Una sala recibe su número al abrirse y se juega después: quien se sentaba teniendo una partida más nueva contra el bot, ya abandonada, recibía un "no tenés partida" en cada consulta y su mesa se recargaba sin parar. Ahora se busca la partida que la mesa dice estar mostrando, y solo entre las del jugador.
- **Una jugada sobre una pantalla atrasada entraba igual.** Si el servidor mandaba al mazo a alguien y repartía mientras su pantalla seguía mostrando la mano anterior, un "Truco" o un "Al mazo" tocado ahí valía en la mano nueva, que esa persona no había visto. Ahora toda jugada lleva el número del último evento que mostró la mesa (`desde`) y el servidor la rechaza, con la fila bloqueada, si no es el último. La mesa cuenta entonces lo que pasó de verdad.
- **El cartel de "Salir" prometía algo falso entre personas:** decía que la partida quedaba guardada, y con otra persona el turno sigue venciendo. Ahora lo dice.
- **Si el rival apuraba el reparto, el cierre de la mano no se llegaba a leer.** Queda a la vista dos segundos y medio como mínimo; tocar la mesa lo saltea.
- **El plazo se leía en un momento y los eventos en otro.** Si alguien jugaba en el medio, la mesa recibía el turno nuevo con lo que le quedaba al plazo viejo. La fila y los eventos se leen ahora de una misma foto de la base (una transacción de lectura).
- **Otras:** la mesa podía quedarse sin programar su próxima consulta en un cruce angosto; la sala y la mesa tenían copiado el código que escucha los avisos y el del reparto (ahora están en `echo.js` y `cartas.js`); y cada jugada hacía dos consultas de más.

### La sala de espera es la mesa servida

- **Problema:** la sala era la pantalla más floja del sitio. Lo que más pesaba era un campo con una dirección, los tres rectángulos vacíos del rival no se leían como un lugar en la mesa, y la llegada del rival eran tres dorsos que aparecían un segundo antes de cambiar de página.
- **Elegido por Román el 8 de octubre de 2026,** entre tres opciones: la mitad del rival pasa a ser una mesa chica hecha con las piezas de la de juego (el tanteador con "Vos" y "Tu rival", el mazo contra el borde, la línea del campo, el aviso en el medio y los dos lugares vacíos). Descartó dejar la disposición y solo sumar movimiento, y hacer protagonista a la carta de modo. La pantalla que ve quien recibe el link no se tocó.
- **Mientras se espera no se mueve nada,** también elegido por Román: es la regla de "nada en loop". Descartó un fósforo que se consumiera durante los 30 minutos de la sala (avanza tan lento que no se ve) y un indicador en loop.
- **La llegada es el único momento coreografiado:** el apodo cae en el tanteador y el mazo reparte tres cartas a cada uno, una para cada uno y con 70 ms entre carta y carta, igual que en la mesa (Web Animations API, solo `transform` y `opacity`). Se reparten boca abajo: la sala no conoce las cartas, que recién llegan en la mesa. Con movimiento reducido todo aparece con un fundido corto.
- **El botón de copiar confirma él mismo:** por tres segundos pasa a Basto y dice "Link copiado". Antes la confirmación era una ficha aparte, que en el celular caía a otro renglón y empujaba la mesa hacia abajo cada vez.
- **Nada salta:** el aviso guarda siempre dos renglones (al llegar el rival el texto es más largo), y las cartas de la mesa chica se miden contra el ancho y el alto de la ventana, así en 1355 x 638 entra entera al lado del link. En el celular, si la mesa quedó debajo del borde cuando llega el rival, la página baja hasta mostrarla.

### Los modos con una sala abierta

- **Problema:** Román abrió una sala, volvió a los modos y creyó que la pantalla estaba rota: los niveles del bot estaban apagados y el motivo se leía poco.
- **Se eligió:** decirlo de entrada ("Tenés una sala abierta esperando rival. Para jugar contra el bot o en otro modo, primero cancelala.") y poner "Cancelar sala" ahí mismo, al lado de "Volver a la sala". Antes había que entrar a la sala para cerrarla.

### Detalles del arranque

- **Sin claves, ningún comando arranca.** Con `BROADCAST_CONNECTION=reverb` y las claves vacías, Laravel falla al crear el emisor y también falla el comando que genera las claves. Se resuelve corriendo `reverb:install` con la conexión en `null` solo para ese comando. Está explicado en `.env.example`, que deja la conexión en `log` para que la integración continua y quien clone el proyecto sin Reverb sigan arrancando.
- **Quinto proceso local:** `php artisan reverb:start`, en el puerto 8080.
- **Probado de punta a punta:** un cliente de Node se conecta al WebSocket como lo haría un navegador, Laravel manda un aviso y llega.
- **Pendiente que no es de M5:** `npm audit` marca dos vulnerabilidades críticas en `shell-quote`, que viene de `concurrently`, una herramienta de desarrollo que ya estaba antes.

## M6. Tanteador, cantos y movimiento

Las tres piezas propias ya existían desde M0 y M3. Este módulo no construyó nada nuevo: revisó el movimiento de todo el sitio, corrigió lo que encontró y dejó las reglas de la casa escritas como tests.

### Las reglas de movimiento se comprueban solas

- **Problema:** "solo se animan `transform` y `opacity`", "nada en loop" y "con movimiento reducido todo es un fundido" eran reglas que se cumplían porque alguien se acordaba. Una animación nueva podía romperlas sin que nadie lo notara.
- **Se eligió:** `tests/Unit/MovimientoTest.php` lee los estilos, las vistas y el JavaScript y falla si una transición o una animación mueve otra propiedad, si algo se repite, si aparece una curva que arranca lenta (`ease-in`), si hay un hover fuera del puntero fino o si una regla que mueve algo no tiene su versión de movimiento reducido. No mira cómo se ve una animación: eso se revisa en el navegador.
- **La única excepción** está nombrada en el test: el recorte en círculo del cambio de modo (`clip-path`, en `modo.js`), que ya estaba aprobado.
- **El tanteador tiene el suyo:** `tests/Feature/TanteadorTest.php` lo dibuja en los 31 tanteos posibles, de 0 a 30, y comprueba un fósforo por punto, en orden, con las malas separadas de las buenas.
- **Se descartó** una herramienta de análisis de estilos: son reglas simples y un test que lee texto alcanza, sin sumar nada al stack.

### Lo que encontró la revisión del movimiento

La base estaba bien: una sola curva en todo el sitio, duraciones cortas y nada en loop. Se corrigió esto, elegido por Román el 8 de octubre de 2026 mirando la lista:

- **Tocar una carta mientras la mesa cuenta una jugada no hacía nada.** Ahora apura, igual que tocar el paño. No juega la carta: quien la tocó todavía no terminó de ver lo que hizo el rival. Román descartó que además quedara anotada para jugarse sola.
- **Los cantos largos quedaban chicos en el celular.** Medido a 360 px: "Truco" 93 px, "Real envido" 47, "Falta envido" 43 y "Contraflor al resto" 28, y el canto es el gesto fuerte de la mesa. Ahora un canto de varias palabras se parte en dos renglones cuando así se lee bastante más grande (80, 80 y 51 px). En una pantalla ancha entra entero y no se parte; "No quiero" va siempre en uno.
- **Anotar puntos no tenía tope:** 150 ms por fósforo, así que un falta envido de 15 eran más de dos segundos mirando caer fósforos. Una suma entera tarda ahora un segundo como mucho.
- **El reparto le daba siempre la primera carta al jugador.** Empieza por el mano, como en la mesa de verdad.
- **La cabeza del fósforo no se encendía en el tanteador,** y sí en el logo, los links y los niveles. Es el mismo gesto en los cuatro lugares, con una sola regla de estilos.
- **Con movimiento reducido se seguían moviendo** los duelos de "Cómo se juega", las pestañas y la barra de avance del historial; y la mesa leía esa preferencia una sola vez, al cargar. Ahora la pregunta cada vez.
- **Detalles:** los carteles entran con algo de cuerpo y se van más rápido de lo que entran; la carta del botón de modo pasó de 320 a 300 ms; la llama del logo y tres vistas usaban una curva de fábrica en vez de la del sitio.

### El ritmo lo elige quien juega

- **Problema:** entre tirar una carta y poder volver a jugar pasaban unos 2 segundos, y nunca se había ajustado con Román mirando. La idea era dejarle dos velocidades para que eligiera una y quedara fija para todos.
- **Lo que propuso Román el 8 de octubre de 2026,** y es mejor: un botón en la mesa. No todos juegan igual: quien está aprendiendo necesita leer qué pasó y quien sabe quiere que la mesa no lo frene. Con el botón no hay que elegir por todos.
- **Qué cambia el ritmo ágil:** las esperas, no el juego. Las reglas, las cartas y cómo juega el bot son los mismos. Las pausas para leer cada carta, baza o canto duran un 30 % menos, la jugada del bot se ve apenas llega en vez de esperar un segundo, y la mesa le pregunta al servidor cada 0,3 segundos en vez de cada 0,6. Medido contra el bot, de tirar una carta a poder jugar de nuevo: 2,5 segundos en tranquilo y 1,5 en ágil (mediana de 14 turnos cada uno).
- **La pausa del bot pasó del servidor a la pantalla.** Antes el trabajo en cola salía con un segundo de demora; ahora el bot juega apenas le toca y es la mesa la que espera lo que haga falta para que parezca que piensa. Así el ritmo es una preferencia de quien mira y el servidor no necesita saberla: no hay columna, ni sesión, ni caché. El turno del bot sigue saliendo de la cola.
- **Se guarda en el navegador,** como el modo de día y de noche, y vale también para quien juega sin cuenta. Entre dos personas cada uno elige el suyo y solo cambia lo que ve él.
- **El botón** va en la barra de arriba, junto al de día y noche, con un ícono propio (dos puntas hacia adelante, con el trazo de los fósforos). Lleno es ágil. Como un ícono solo no explica qué hace, al tocarlo la mesa lo dice en el renglón de avisos ("Ritmo ágil." o "Ritmo tranquilo."), que también lee el lector de pantalla. A 360 px entra con 22 px de sobra.
- **Las pausas de lectura y las esperas atadas a una animación son cosas distintas** (`leer()` y `esperar()` en `mesa.js`): el ritmo acorta las primeras; el reparto o juntar las cartas duran lo que dura su animación.
- **Se descartó** ponerlo dentro del cartel de "Salir" (no ocupa lugar, pero nadie lo encuentra) y una sola velocidad para todos.

### El final de la partida lo cuenta el tanteador

- **Problema:** la regla dice que el gesto fuerte de la mesa se guarda para el final de la partida, pero el final era un cartel chico con un fundido. Y los festejos están fuera del módulo.
- **Elegido por Román:** un cierre con peso y sin festejo. El paño hondo ocupa toda la mesa, el resultado entra grande y los dos tanteadores se cuentan desde cero, de corrido y al mismo paso: el que perdió se queda en su tanteo y el que ganó sigue hasta completar el último grupo. Lo que se mueve es el tanteo, que es de lo que se trata.
- **No traba nada:** corre una sola vez, dura cerca de un segundo y los botones se usan desde que aparecen. Con movimiento reducido los dos tanteos están completos de entrada. El resultado va escrito aparte para el lector de pantalla, porque lo que se cuenta es dibujo.
- **Se descartó** confeti o cualquier cosa en loop, y dejar el cartel como estaba.

### Lo que encontró la revisión de código de M6

Nueve observaciones, ninguna rompía el juego. Se corrigió esto:

- **El test de reglas dejaba pasar más de lo que decía cuidar.** No veía una clase de transición con una variante adelante (`hover:transition-all`), daba por cubierta una regla si el bloque de movimiento reducido nombraba a su contenedor y no a la pieza que se mueve, y fallaba con una curva escrita con paréntesis. Ahora revisa la clase la lleve quien la lleve, exige que esté nombrada la pieza, y entiende las curvas. Lo que sigue sin poder comprobar está dicho en el propio test: que una regla esté nombrada no dice cómo queda, y eso se mira en el navegador.
- **El final de la partida se cortaba en una ventana muy baja** (un celular acostado): el contenido centrado no se alcanzaba bajando. Se centra con márgenes y lo que no entra se recorre.
- **El final tapaba la mesa pero no la apagaba:** con el teclado, el foco podía irse a "Salir", que quedaba detrás. Lo de atrás queda inerte mientras el final está a la vista, y el tanteo se anuncia junto con el título.
- **El reparto de la sala de espera** seguía dándole la primera carta a quien abrió la sala. La consulta de la sala dice ahora quién es mano, y el reparto empieza por ahí, igual que en la mesa.
- **Queda para M7:** la repetición del historial tiene su propia cuenta para el tamaño de los cantos y todavía los muestra en un renglón. Cuando esa pantalla se conecte a las partidas reales, tiene que usar la misma función que la mesa.

## M7. Historial y repetición

El historial dejó de ser una maqueta: la lista y la repetición salen de las partidas que se jugaron. Los datos de ejemplo se borraron.

### La repetición no guarda nada: se arma con los eventos

- **Problema:** volver a ver una partida jugada por jugada sin guardar una segunda copia de lo que pasó.
- **Elegido:** `App\Juego\Repeticion` vuelve a aplicarle los eventos de la partida al motor, uno por uno (`Mesa::pasoAPaso()`), y de cada cosa que el motor cuenta (una carta, un canto, una respuesta, los tantos, un punto, el cierre de la mano) saca un "cuadro": cómo se ve la mesa en ese momento y una frase que lo dice. La pantalla solo muestra el cuadro que toca; no tiene reglas.
- **Por qué en el servidor:** el navegador recibe cuadros ya filtrados por asiento. Si recibiera los eventos crudos, recibiría las cartas que el rival nunca mostró.
- **Ir a una mano** es ir al cuadro donde se reparte: ahí el tanteo es el que tuvo la partida al empezar esa mano. Hay un test que juega una partida entera, anota el tanteo de cada reparto mientras se juega y lo compara con el de la repetición. Otro comprueba que armarla no escribe nada en la base y que da siempre lo mismo.
- **El final:** el motor avisa que la partida terminó apenas se anota el punto que la cierra, antes de cerrar la mano. Al contarla, eso va último: la repetición termina diciendo quién ganó la partida.
- **Se descartó** guardar los cuadros (sería una segunda verdad que puede desacomodarse) y mandar los eventos al navegador para que los interprete (habría que repetir el reglamento en JavaScript y filtrar cartas del lado equivocado).

### Qué cartas se ven en una repetición

- **Problema:** el criterio del módulo dice que en la repetición "se ven las cartas de los dos". Pero entre dos personas, las cartas que alguien no jugó ni mostró no las vio nadie, y el reglamento dice que vuelven al mazo boca abajo.
- **Elegido por Román:** contra el bot se ven todas, desde el reparto. Entre personas se ven las propias y, del rival, solo lo que se vio en la mesa: lo que jugó y lo que mostró al cerrar la mano por el envido o la flor. La pantalla lo dice en una frase.
- **Test:** recorre todos los cuadros de partidas enteras entre personas, desde los dos asientos, y comprueba que del rival no aparece ninguna carta que no haya jugado o mostrado. Otro lo comprueba sobre lo que de verdad recibe el navegador, por las dos puertas (la página y los datos del cartel).

### El historial de quien juega sin cuenta dura lo que dura la sesión

- **Pedido por Román:** un invitado ve las partidas de esa sesión; quien tiene cuenta las tiene todas; y al crear la cuenta se recuperan también las que jugó como invitado.
- **Cómo:** un middleware (`SesionDeInvitado`) anota en la sesión cuándo empezó. A un invitado se le muestran las partidas terminadas desde ese momento. Las anteriores no se borran: se dejan de mostrar, y tampoco se abren escribiendo la dirección a mano.
- **Recuperar todo al registrarse no necesitó código:** un invitado es una fila de `jugadores` sin correo, y crear la cuenta le completa los datos a esa misma fila. Las partidas ya eran suyas. Hay un test.
- **La lista muestra solo partidas cerradas.** Una sin terminar se avisa aparte, con el link a la mesa, y no tiene repetición: pedirla manda a la mesa a quien la está jugando y a cualquier otro le responde que no existe.
- **Sin partidas no se inventa nada:** un aviso corto, el tanteador en cero y el botón para jugar.
- **Para los módulos que vienen:** cada renglón dice de qué juego fue ("Mano a mano"). El torneo, los desafíos, la escalera y el de a cuatro tienen que guardar sus partidas como eventos, igual que estas, y decir ahí de qué juego son.

### La repetición se abre en un cartel que tiene dirección propia

- **Problema:** Román pidió que la repetición se abra en un cartel sobre la lista, como el de "Salir" en la mesa, en vez de cambiar de página. Un cartel común tiene costos: no se puede compartir ni guardar el link, "atrás" se va del historial en vez de cerrarlo y al recargar se pierde.
- **Elegido por Román entre tres opciones:** el cartel con dirección propia. "Ver de nuevo" es un link de verdad a `/historial/{partida}`. Con JavaScript, el clic abre el cartel y cambia la dirección del navegador a la de esa partida (History API). "Atrás" lo cierra, "adelante" lo vuelve a abrir, y recargar o entrar directo muestra la misma repetición como página.
- **Una sola pieza para los dos lugares:** `<x-repeticion>` se usa en el cartel y en la página. El cartel pide los datos a `/historial/{partida}/cuadros`; la página los trae escritos. Un test comprueba que las dos puertas entregan lo mismo y que las dos están cerradas para quien no jugó esa partida.
- **El cartel es el `<dialog>` del navegador:** se ocupa del foco, de la tecla Esc y de que la lista de atrás no se pueda tocar. Cerrar (el botón, Esc o un clic en el fondo) es volver atrás en el navegador, así el botón y la flecha hacen lo mismo.
- **Una trampa que apareció probando:** si se recarga con el cartel abierto, para el navegador esa página y la lista siguen siendo "la misma": con "atrás" cambia la dirección y no carga nada. La página de la repetición escucha ese aviso y pide la carga.
- **Si algo falla** (sin conexión, o el servidor no contesta), el link hace lo de siempre y abre la página.
- **Medidas:** en escritorio el cartel entra entero, sin deslizar (probado en 1355 x 638, 1024 x 768 y 1920 x 1080). En el celular ocupa la pantalla, y el resultado, la mesa y los controles entran juntos a 360 x 740; el detalle queda debajo. La mesa de la repetición sale de la misma medida que la de juego, `--b`, con su propia cuenta.
- **Movimiento:** el cartel entra apenas más chico y con un fundido, 220 ms. Lo que se abre con "adelante" o se cierra con el teclado no se anima. Avanzar de a una jugada mueve las cartas como en la mesa; saltar de mano o retroceder las pone en su lugar sin moverlas. Las flechas del teclado pasan de jugada y, con Mayús, de mano.
- **Se descartó** el cartel sin dirección (los costos de arriba) y dejar solo la página (era lo que había, y a Román le resultó un salto innecesario).

### Qué no se hizo

- **No pasó por la revisión de código antes de subir:** Román pidió subirlo directo.
- **No hay tests de navegador automáticos** del cartel ni de la repetición: se probaron a mano con un navegador real (abrir, cerrar por las cuatro puertas, atrás y adelante, recargar, teclado) y quedan para M11.
- **Capturas** en `docs/capturas/`: `historial.png`, `historial-celular.png`, `repeticion.jpeg` y `repeticion-celular.png`.

## M8. Ranking y estadísticas

El ranking dejó de ser una maqueta: la tabla sale de las partidas que se jugaron. Con esto se borró el último archivo de datos de ejemplo escritos a mano.

### Las estadísticas salen de los eventos, y se guarda un resumen por partida

- **Problema:** el ranking necesita saber, de cada partida, quién la ganó y cómo le fue a cada uno en los envidos. Eso está en los eventos, pero sacarlo es volver a pasar la partida por el motor. Hacerlo con todas las partidas cada vez que alguien abre la tabla no escala.
- **Elegido:** cuando una partida se cierra, `App\Juego\Estadisticas` la recorre con el motor (igual que la repetición) y guarda un renglón por persona en la tabla `resultados`: si ganó, cuántos envidos se quisieron y cuántos de esos ganó. La tabla del ranking se arma sumando esos renglones.
- **No es una segunda verdad:** el renglón no agrega nada que no esté en los eventos. `php artisan ranking:recalcular` borra todos y los escribe de nuevo desde ahí, y un test rompe a propósito lo guardado y comprueba que recalcular lo deja igual que antes. Sirve también para las partidas jugadas antes de que existiera el ranking: después de migrar hay que correrlo una vez.
- **Qué es un envido jugado:** el que se quiso y se compararon los tantos. El que no se quiso no cuenta. El ganador de la partida también se lee de los eventos (quién llegó a los puntos, o quién abandonó), no de la columna de la partida.
- **Si anotar fallara, la partida se cierra igual.** El ranking se puede rehacer; una partida no puede quedar abierta por un error de la tabla. Hay un test.
- **Se descartó** recorrer todas las partidas en cada visita, y guardar contadores por jugador (ganadas, racha) que se van sumando: un contador no se puede comprobar contra nada, y si un día se desacomoda no hay cómo saberlo.

### Qué partidas cuentan y cómo se ordena

- **Decidido por Román:** se ordena por partidas ganadas; si empatan, mejor porcentaje y después racha más larga. Si todo es igual, va antes quien llegó antes al sitio, para que el orden no cambie de una visita a otra.
- **Contra el bot cuenta desde Intermedio.** Sin las partidas contra el bot el ranking quedaría vacío (un sitio de portfolio no tiene gente conectada), y dejar afuera al Fácil evita subir ganándole cien veces. Entre personas cuentan todas. Abandonar es perder.
- **La racha** son las ganadas después de la última perdida, leídas por la fecha en que se cerró cada partida. Se calcula en la misma consulta que las sumas.
- **Límite conocido:** la tabla trae un renglón por jugador y ordena en PHP, sin caché. Alcanza de sobra para este sitio; con miles de cuentas habría que paginar en la base.
- **Ganar por abandono cuenta solo desde las buenas.** Problema: como abandonar es perder, una persona con dos ventanas podía regalarse partidas (abre una sala, se sienta desde la otra y abandona), a una victoria cada pocos segundos. Elegido por Román: a quien abandona le cuenta siempre como perdida; al otro le cuenta como ganada solo si ya iba en las buenas (15 de 30). Si no, no se le anota nada. Quien iba ganando bien conserva su victoria aunque el rival se vaya. Se descartó dejarlo como estaba y que ganar por abandono no contara nunca (castiga a quien le abandonan una partida casi ganada). No cierra el hueco del todo: dos ventanas pueden jugar hasta 15 y recién ahí abandonar, pero ya es jugar media partida por cada victoria.

### Los jugadores de ejemplo juegan de verdad

- **Problema:** la tabla no puede arrancar vacía, y el proyecto dice que se simula el mundo, no la tecnología. Once jugadores con números escritos a mano serían justo lo contrario.
- **Elegido por Román:** los jugadores de ejemplo son filas de `jugadores` marcadas (`de_ejemplo`), cada una con un nivel de bot. `php artisan ranking:ejemplo` los hace jugar todos contra todos, tres vueltas (30 partidas cada uno, 165 en total), con `App\Juego\Simulacion`: el mismo motor y los mismos bots que una partida real, cada bot mirando solo su asiento. Las partidas quedan guardadas como eventos y sus estadísticas se calculan igual que las de cualquier persona.
- **Todo sale de una semilla:** la misma semilla da la misma partida, carta por carta (test). Por eso la tabla de ejemplo es siempre la misma, en cualquier máquina.
- **El orden no está puesto a mano:** quedan arriba los que juegan con bots más fuertes porque ganan más. Un test comprueba que entre todos ganaron tantas partidas como se jugaron.
- **No pasa por la mesa.** La mesa es para personas: lleva plazos, trabajos en cola y avisos en vivo. La simulación juega en memoria y guarda al final. Un test comprueba que los eventos guardados, aplicados por el camino de siempre, llegan al mismo resultado.
- **Lo simulado va marcado también en la base:** las partidas simuladas llevan `simulada`, además de que sus dos jugadores son de ejemplo. Así lo que venga después (la API, la administración, las categorías) puede dejarlas afuera sin adivinar.
- **Cuidados:** un jugador de ejemplo no tiene correo ni contraseña, así que nadie ingresa como él; ningún formulario puede crear uno; la limpieza diaria de invitados no se los lleva; si una persona ya usa uno de esos apodos, ese jugador de ejemplo no se crea. La siembra es todo o nada y no repite lo ya jugado: se puede correr en cada publicación.
- **Se descartó** dejar números fijos, y hacerlos jugar por la mesa (habría dejado miles de trabajos en la cola y avisos a nadie).

### Quién aparece y qué ve cada uno

- **Decidido por Román:** en la tabla están las cuentas y los jugadores de ejemplo. Quien juega sin cuenta no aparece (la tabla se llenaría de "Invitado 48213"), pero en "Tu puesto" lee en qué lugar estaría, con el link para crear la cuenta. Como al registrarse conserva su fila, entra a la tabla con todo lo que ya jugó.
- **La pantalla es la de la maqueta, conectada.** Se sumaron los casos que la maqueta no tenía: la tabla vacía, no tener partidas que cuenten, ir primero, estar entre los cuatro de arriba (lleva la marca "vos") y tener una partida sin terminar (el botón la retoma).

### Lo que encontró la revisión de código de M8

Diez observaciones. Se corrigió esto:

- **La limpieza de invitados se podía llevar partidas ajenas.** Si un invitado abría una sala y se sentaba alguien con cuenta, al borrarse el invitado (30 días sin volver) se iba la partida entera, y con ella la victoria del otro. Ahora ese invitado no se borra. El mismo problema lo tiene borrar una cuenta, que se resuelve en M11.
- **Una siembra cortada por la mitad quedaba como si estuviera completa.** Ahora es una sola transacción.
- **La racha se leía por el orden en que se escribieron los renglones,** y volver a anotar una partida vieja la cambiaba. Se lee por la fecha de cierre.
- **Un error al anotar deshacía el cierre de la partida.** Ahora se anota el error y la partida se cierra.
- **Para no contar dos veces un envido se salteaban dos tipos de evento por nombre.** Ahora se mira si el motor avanzó: vale para cualquier evento nuevo que no pase por el motor.
- **Las partidas simuladas no se distinguían en la base** de una entre dos personas. Llevan su marca.

Las partidas regaladas entre dos sesiones se le consultaron a Román y quedó la regla de las buenas (arriba). Quedó anotado como límite que la tabla no tiene caché. Y como paso de publicación (M11): después de migrar, `ranking:ejemplo` y `ranking:recalcular`.

- **Capturas** en `docs/capturas/`: `ranking.jpeg`, `ranking-celular.jpeg`, `ranking-tu-puesto.png` y `ranking-tu-puesto-celular.png`.

## El orden de los módulos (9 de octubre de 2026)

- **Problema:** la API (M9), la página de reglas (M10) y la calidad y publicación (M11) eran los tres últimos módulos del plan original. Cuando se sumaron M12 a M20 quedaron en el medio. Román planteó que así no tenían sentido: una API o una página de reglas hechas ahora habría que rehacerlas con cada modo nuevo.
- **Elegido por Román:** M9 y M10 pasan al final, después de M20. La API muestra partidas, ranking y perfil, y los tres cambian con lo que falta (series, torneos, categorías, de a cuatro). La página de reglas ya explica el mano a mano; se completa en una sola pasada cuando existan los demás modos.
- **M11 se parte en dos, sin sumar módulos.** La publicación y las pruebas automáticas de navegador (M11a) se adelantan y son lo próximo. El pulido final (M11b: accesibilidad, rendimiento medido y manejo de la cuenta) cierra el proyecto.
- **Por qué publicar antes:** es el mayor riesgo técnico que queda. Todo el sitio tiene que entrar en un solo contenedor gratis (la web, el tiempo real, la cola y las tareas programadas) y todavía no se midió si entra en memoria; conviene saberlo ahora y no después de nueve módulos. Además el módulo de instalación en el celular necesita el sitio publicado con HTTPS, y un portfolio sin link no se puede mostrar.
- **Por qué las pruebas de navegador antes:** la pantalla de la mesa no tiene ningún test automático, y la revancha y el juego de a cuatro la van a tocar.
- **Mientras no exista la API, el sitio no dice que es real.** Se sacó del pie, de la portada y del README; vuelve cuando esté hecha.
- **Se descartó** mover M11 entero al final (se descubriría tarde si el hosting gratis alcanza) y dejar todo como estaba.

## M11a. Publicación

El sitio está publicado en Render, en el plan gratuito, con la base MySQL en Aiven. Lo que sigue es cómo se hizo entrar todo ahí y qué se midió. Al final están las pruebas automáticas de navegador, la otra mitad de M11a.

### Todo el sitio en un solo contenedor

- **Problema:** el plan gratuito de Render da un solo contenedor, con 512 MB de memoria y una décima de procesador, sin procesos de fondo ni tareas programadas aparte. Y el sitio necesita cuatro cosas corriendo a la vez: la web, el tiempo real, la cola y las tareas programadas.
- **Elegido:** una imagen de Docker con nginx, PHP y supervisor, que es el programa que arranca los procesos y los vuelve a levantar si se caen. No son agregados al código del sitio: son herramientas del contenedor.
- **El tiempo real entra por la misma puerta que las páginas.** Render publica un solo puerto. Reverb escucha solo adentro del contenedor y nginx le pasa los navegadores que piden `/app/`. La otra puerta de Reverb, por donde el sitio le avisa qué pasó, no se publica: desde afuera responde que no existe.
- **Las tareas programadas son un bucle** que corre cada cinco minutos, alineado al reloj, y no el proceso de Laravel que se queda esperando. Ninguna tarea es más frecuente que eso, y un proceso de PHP esperando todo el día son 40 MB que le faltan a otro.
- **Se descartó** repartir el sitio en varios servicios (el plan gratuito alcanza para uno) y pasar la cola o las tareas a otro proveedor (más cuentas y más partes que pueden fallar).

### El navegador lee de la página adónde conectarse

- **Problema:** la dirección del tiempo real iba compilada adentro del JavaScript. Para publicar había que saber la dirección final del sitio antes de armar la imagen, y pasarla como dato al compilar.
- **Elegido:** va escrita en cada página, en una etiqueta del encabezado que arma el servidor. El JavaScript la lee de ahí. La misma imagen sirve en cualquier dirección donde se publique, y la dirección la da Render al arrancar.
- **Dos direcciones distintas:** el sitio le habla a Reverb por adentro del contenedor, sin cifrar; el navegador entra por la dirección pública, con HTTPS. En una máquina de desarrollo las dos coinciden.

### Qué carga a mano quien publica: cuatro datos

- **Problema:** el repositorio es público y Román publica por primera vez. Cuanto menos haya que generar y pegar a mano, menos se puede equivocar.
- **Elegido:** en el panel de Render se cargan solo los cuatro datos de la base (servidor, puerto, usuario y contraseña). La clave del sitio la sortea Render. Las tres claves del tiempo real salen de la clave del sitio, cada una con una mezcla distinta: no cambian entre un arranque y otro, y conocer la pública no dice nada de la secreta. La dirección del sitio la toma de lo que informa Render.
- **El certificado de la base va en el repositorio.** Aiven exige conexión cifrada, y ese certificado es público: sirve para comprobar que el servidor es el de Aiven, no para entrar.
- **La base queda abierta a cualquier dirección,** porque el plan gratuito de Render no da una dirección fija para autorizar. La protegen la contraseña y el cifrado obligatorio.
- **Lo que pasó la primera vez:** el servicio no arrancó porque en dos casillas quedaron escritas las palabras "Host" y "Port" en vez de sus valores (la tabla de instrucciones era ambigua). El registro lo decía con todas las letras, se corrigieron y arrancó. El arranque está escrito para eso: si la base no responde, el contenedor no se prende y el motivo queda en el registro.

### El arranque con una décima de procesador

- **Problema:** probando la imagen con los límites del plan gratuito, el sitio tardaba 182 segundos en contestar su primer pedido. Eso es lo que esperaría alguien cada vez que el servicio se despierta.
- **Qué se encontró:** cada comando de Laravel arranca de cero y vuelve a leer y compilar todo el código. Al arrancar corrían cuatro seguidos, y después cuatro procesos de fondo a la vez, repartiéndose la décima de procesador con la web.
- **Elegido, tres cosas:** las rutas y las vistas se dejan resueltas al armar la imagen y no en cada arranque; el código compilado se guarda también en disco y viene en la imagen, así cada comando lo levanta ya hecho; y los procesos de fondo arrancan de a uno, después de la web. Quedó en 52 segundos.
- **Los jugadores de ejemplo del ranking se siembran de fondo,** al final, para que el sitio no los espere.

### Detrás del servidor de Render

- **Problema:** el navegador no le habla al sitio directo: delante está el servidor de Render, que atiende el HTTPS. Si el sitio no le cree lo que dice del pedido original, piensa que lo visitan por HTTP y arma mal los links y las cookies.
- **Elegido:** se le cree que el pedido entró por HTTPS y desde qué dirección. No se le cree el nombre del sitio.
- **Lo que encontró la revisión de seguridad:** en la primera versión también se le creía el nombre, y esa cabecera la puede escribir cualquiera en su pedido: con ella los links del sitio salían apuntando a donde esa persona quisiera. Hoy no hacía daño, pero cuando exista el correo para recuperar la contraseña, ese link habría salido con el nombre del atacante. Hay un test.

### Lo que se midió

- **En una máquina, con los límites del plan gratuito y un MySQL 8.4 igual al de producción:** las migraciones corren sin cambios (en desarrollo se usa MariaDB 10.4); unos 150 MB de los 512; 52 segundos de arranque; páginas entre 0,1 y 0,5 segundos.
- **En el sitio publicado, desde Buenos Aires:** las páginas contestan entre 0,3 y 0,8 segundos; se jugó una partida entera contra el bot en un celular de 360 px (32 manos, sin errores); dos navegadores jugaron entre sí con el tiempo real conectado en los dos; el ranking se sembró solo y quedó con la misma tabla que en desarrollo, porque sale de una semilla.
- **Seguridad, comprobado en el sitio publicado:** HTTP redirige a HTTPS; las cookies van marcadas como seguras y la de sesión no es legible desde JavaScript; un nombre de sitio falso en el pedido no aparece en los links; los archivos que empiezan con punto y los de configuración no se entregan.
- **Queda por medir,** y Román eligió dejarlo para más adelante: la memoria real en el panel de Render y que la limpieza programada corre en el sitio publicado. Las dos cosas se midieron en la máquina con los mismos límites.

### El servicio se duerme, y se deja así

- **Problema:** en el plan gratuito, el servicio se apaga a los 15 minutos sin visitas y tarda cerca de un minuto en volver. Quien abre el link después de un rato espera ese minuto.
- **Elegido por Román:** dejarlo dormir. Es un sitio de portfolio: alcanza con que el README avise que la primera carga puede tardar.
- **Se descartó** un monitor externo que lo visite cada diez minutos para mantenerlo despierto. Funciona y entra en las horas gratuitas del mes, pero Render no lo garantiza y es una cuenta más que mantener.
- **Qué pasa mientras duerme:** no corre nada (ni la cola ni la limpieza), pero tampoco hay nadie jugando. Al despertar, el contenedor arranca de cero: migra si hace falta, y la cola retoma lo que haya quedado pendiente en la base.

### La base se apaga sola, y una tarea la mantiene en uso (10 de octubre de 2026)

- **Problema:** al día de publicado, el sitio dejó de cargar. La base gratuita de Aiven se había apagado sola: su proveedor apaga las que pasan un tiempo sin uso (avisa por correo, pero no publica cuánto tiempo es), y cuando el servicio de Render duerme nadie la toca. Apagada no vuelve sola. El contenedor migra al arrancar, no encontraba la base, se cerraba y Render lo volvía a prender, sin fin. Se arregló prendiéndola a mano en el panel de Aiven y volviendo a publicar.
- **Cómo se reconoce:** el nombre del servidor de la base deja de existir (no resuelve), y el registro de Render muestra el error de conexión en la migración.
- **Elegido por Román:** una tarea programada de GitHub (`.github/workflows/visita.yml`) entra al ranking del sitio cada tres horas. Despierta el servicio, que al arrancar migra y deja la cola y las tareas leyendo la base un cuarto de hora. Si el sitio no contesta, la tarea falla y GitHub avisa por correo: sirve además de alarma.
- **Límites, dichos de entrada:** no hay garantía de que ese uso le alcance a Aiven, porque no dice cuánto pide. GitHub puede demorar una tarea programada, y las desactiva en un repositorio que pasa 60 días sin cambios. Son unas dos horas por día de servicio prendido, que entran en las horas gratuitas del mes.
- **No cambia lo decidido sobre el sueño del servicio:** el sitio sigue durmiendo entre visita y visita, y la primera carga puede tardar. Esto no es un monitor para tenerlo despierto: es para que la base no se quede sin uso.
- **Se descartó:** mudar la base a TiDB Cloud (se despierta sola, pero es "compatible con MySQL" y no MySQL: habría que probar las migraciones y los tests contra ella), pagar el plan de Aiven que no se apaga, y dejarlo así y prenderla a mano. Si la base se vuelve a apagar con la tarea andando, lo siguiente a probar es TiDB Cloud.

### Pruebas en un navegador real

- **Problema:** los tests comprueban el reglamento y el servidor, pero ninguno miraba la pantalla. La mesa es casi todo JavaScript, y los módulos que vienen (revancha, de a cuatro) la van a tocar.
- **Elegido:** Laravel Dusk, que maneja un Chrome sin ventana. Tres pruebas: entrar sin cuenta y llegar a la mesa con un solo botón; crear una cuenta, salir y volver a ingresar; y jugar una mano entera contra el bot tocando la pantalla, hasta repartir la siguiente.
- **Cómo juega la prueba:** tira cartas y a cada canto contesta "no quiero". Con cartas al azar no se puede saber qué va a pasar, pero así la mano se cierra siempre y la partida no puede terminarse en la primera mano. Lo que comprueba es que la pantalla responde: hay tres cartas, al tocarlas se juegan, la mano se cierra, alguien suma y se puede repartir.
- **No tocan la base por su cuenta:** todo lo hacen por la pantalla. El sitio contra el que corren se levanta aparte, con un archivo SQLite vacío y la cola en el momento (el bot juega apenas le toca). En la máquina de desarrollo eso va por variables de entorno, que le ganan al `.env`: el `.env` y la base de desarrollo no se tocan.
- **Están en la integración continua, como un trabajo aparte,** y el sitio se publica recién cuando pasan los dos. Si un cambio rompe la mesa, no llega al sitio publicado. Si fallan, quedan guardadas las capturas de lo que el navegador tenía en pantalla.
- **Se descartó** usar el reemplazo de `.env` que trae Dusk (pisa el `.env` mientras corre y, si se corta, lo deja cambiado) y probar contra el sitio publicado (las pruebas crean cuentas y partidas).

## Una pasada por los tests (9 de octubre de 2026)

- **Problema:** Román preguntó dos veces si no había demasiados tests. La cantidad no era el costo: de casi 700, la gran mayoría tarda milisegundos. El costo eran dos cosas. Unos pocos tests jugaban partidas enteras por la mesa, jugada por jugada contra la base, solo para tener una partida terminada con la que probar otra cosa (el historial, la repetición, el ranking): por ellos la corrida tardaba unos tres minutos y medio. Y como esas partidas se repartían al azar, tres veces un test falló sin que hubiera nada roto.
- **Partidas con semilla para los tests que solo necesitan una partida cerrada.** Se juegan en memoria, con el motor y dos bots, y se guardan de una vez con sus eventos, igual que queda una partida real. Tardan milisegundos y salen siempre iguales. Los archivos del historial, la repetición y el ranking pasaron de unos 125 segundos a 9.
- **Que la mesa juega bien una partida entera se sigue probando por el camino real,** en los tests de la mesa contra el bot, de la mesa entre personas y del tiempo real. Esos no se tocaron, salvo acortar uno.
- **Tandas más cortas donde el resultado es siempre el mismo.** Los tests que enfrentan a los niveles del bot jugaban 60 partidas por cruce. Son con semilla: jugar 24 da el mismo veredicto todas las veces. Los pisos se volvieron a medir y quedaron anotados en el test.
- **El tanteador se dibuja una vez.** Sus 62 casos (un test por cada tanteo, del 0 al 30) compilaban una plantilla cada uno. Ahora son dos tests que dibujan los 31 tanteos en una sola plantilla y revisan lo mismo: de 20 segundos a menos de 2.
- **Se sacaron doce tests repetidos:** lo mismo comprobado dos veces, una llamando a la clase y otra por la pantalla (se dejó el de la pantalla, que recorre también la clase), y un par que no decían nada que otro test no dijera.
- **Lo que no se sacó, y por qué:** los 246 tests del motor. Son los que cumplen el criterio del módulo ("un test por cada fila de las tablas de pardas y de envido, por cada regla de flor y truco y por cada caso borde"), tardan menos de diez segundos entre todos y son lo que permite cambiar el motor sin miedo. Tampoco los que nacieron de un error encontrado: cada uno cuida que ese error no vuelva.
- **Resultado:** de 694 tests a 622, y la corrida completa de unos tres minutos y medio a dos. Ningún test depende ya de la suerte del reparto para tener una partida con qué trabajar.

## M14. Sonido

La mesa suena: cartas, fósforos y cantos. Arranca apagada, y con este módulo la barra de la mesa pasó de cuatro botones a dos.

### Los sonidos los fabrica el navegador

- **Problema:** hacer sonar la mesa sin molestar a quien no lo quiere, y sin sumarle peso ni licencias al sitio.
- **Elegido por Román:** sin archivos de audio. Cada sonido se arma en el momento (Web Audio) con dos ingredientes: un soplo de ruido filtrado, para lo que roza o golpea, y un tono corto que baja, para lo que tiene cuerpo.
- **Con el sonido apagado no existe nada de audio:** ni un contexto abierto, ni una descarga. Lo prende quien juega y la elección queda en su navegador. Una partida que sigue en otra pestaña no suena.
- **El sonido acompaña, nunca avisa solo:** todo lo que suena también se ve en la mesa y se lee en el renglón de avisos.
- **Se descartó usar grabaciones.** Hay un paquete libre de sonidos de cartas (Kenney, licencia CC0, unos 100 KB) que suena más real; Román prefirió seguir sin archivos y mejorar los fabricados.

### Qué hace agradable a un sonido corto

La primera versión cumplía pero sonaba plana. Román pidió investigar qué hace satisfactorio a un sonido, y de ahí salió la segunda:

- **Capas.** Una carta que se apoya son cuatro cosas: el chasquido, el cartón, el golpe sordo en el paño y un rebote chico.
- **Nunca dos veces igual.** Cada vez que suena, cambia apenas de tono y de volumen, y el ruido arranca en otro punto. Una carta se juega tres veces por mano: idéntica, cansa.
- **Los fósforos suben.** En una suma, cada fósforo suena un semitono más arriba que el anterior, y el que completa un grupo de cinco lleva un golpe aparte. Se oye que el tanteo sube.
- **Cada canto tiene su golpe de nudillos:** el truco pega dos veces, "quiero" suena más claro y "no quiero" más grave y apagado. No hay voces: la palabra está en la pantalla.
- **El final son tres golpes,** el último más grave y más largo. Sobrio, como el cierre de la partida.
- **Límite:** cómo suenan lo juzgó Román escuchando. Lo que se comprueba solo es que se disparen donde corresponde y que cumplan las reglas.

### Los ajustes de la mesa, en una ventanita

- **Problema:** con el sonido, la barra de arriba iba a tener cuatro botones (modo, sonido, ritmo y salir). A 360 px quedaban 22 px libres, y Román marcó que ya se veía apretada: cuatro controles con cuatro aspectos distintos, tres de ellos preferencias que se eligen una vez y uno solo que se usa durante la partida.
- **Elegido por Román:** un botón "Ajustes" al lado de "Salir". Despliega debajo una ventanita con las tres preferencias, cada una dicha con palabras y con su estado ("Sonido, apagado").
- **Ventanita y no cartel:** la primera versión era un cartel como el de "Salir". Román propuso la ventanita y es mejor: no tapa la mesa (entre dos personas el reloj del turno sigue corriendo), y un cartel a pantalla completa es mucho gesto para cambiar el sonido. Se cierra tocando afuera, con Esc o con el mismo botón; la mesa de atrás sigue viva.
- **La llave de cada ajuste es un fósforo en su carril:** apagado descansa a un lado, a media tinta; prendido cruza y se le enciende la cabeza, como a los del logo y el tanteador. El modo usa la carta que se da vuelta, la misma del resto del sitio.
- **Sin sombra:** la única sombra del sitio es la de las cartas. A la ventanita la recorta un contorno.
- **En el celular, el logo de la mesa va sin el nombre:** quedan los cuatro fósforos.
- **Una trampa que apareció probando:** una carta, al jugarse, frena su propio toque para que no apure además a la mesa. Por eso tocar una carta no cerraba la ventanita. El toque de afuera se escucha ahora antes que nadie.
- **Ícono nuevo, propio:** tres fósforos acostados de distinto largo, como tres perillas.
- **Queda lugar para lo que viene:** los mazos y las mesas para elegir (M20) entran ahí como un renglón más.

### Qué se comprueba y qué no

- **Con tests:** ningún sonido llega al segundo y medio, nada queda en loop, no hay archivos de audio ni etiquetas que los pidan, y el audio se abre en un solo lugar, al prenderlo. Las reglas de movimiento alcanzan a la ventanita y a sus llaves.
- **En un navegador real, a 360 px:** arranca apagado y sin abrir audio; jugando apagado no suena nada; al prenderlo suena la muestra; suenan las jugadas y un roce por carta al repartir; los fósforos de una suma suben de tono; la elección sobrevive a recargar; todo se maneja con el teclado y el foco vuelve al botón.
- **Falta:** pasar esas comprobaciones de navegador a las pruebas automáticas de la integración continua. Captura en `docs/capturas/mesa-ajustes-celular.png`.

## M12. Revancha y mejor de tres

Dos jugadores siguen jugando entre sí sin volver a invitarse: al terminar una partida se pide la revancha, y al armar la partida se puede elegir una serie al mejor de tres. Vale contra el bot y entre dos personas.

### Lo que decidió Román

- **Quién es mano:** se alterna. En la revancha, y en cada partida de una serie, arranca siendo mano quien no lo fue al empezar la anterior. Cada uno conserva su asiento.
- **Cuánto se espera la respuesta:** un minuto. Después el pedido se vence solo.
- **Para el ranking cuenta cada partida por separado,** como hasta ahora. Ganar la serie no suma nada aparte.
- **Quien abandona una partida de una serie pierde la serie entera.** Si se fue, no va a estar para la siguiente, y el otro no se queda esperando una partida que no empieza. Vale igual para quien pierde por dejar vencer sus turnos.

### La serie solo agrupa partidas

- **Problema:** jugar al mejor de tres sin que una serie sea "una partida más larga". Cada partida tiene que seguir siendo su propia lista de eventos, con su repetición y su renglón en el ranking.
- **Elegido:** una tabla `series` con casi nada adentro (a cuántas partidas se juega, quién la ganó y cuándo), y cada partida apunta a su serie. Cuántas ganó cada uno no está guardado: se cuenta mirando las partidas.
- **La partida siguiente se reparte sola,** en el mismo momento en que se cierra la anterior, y no cuando alguien la pide. Así no existe una serie "a medias sin partida": o alguien ganó dos y está cerrada, o hay una partida en juego. Si los dos se van, esa partida se resuelve como cualquier otra (turnos vencidos) y la serie se cierra. Y como cada persona tiene una sola partida abierta a la vez, mientras dura una serie no se puede jugar otra cosa: sus partidas quedan una atrás de la otra en el historial.
- **Se descartó** crear la siguiente recién cuando alguno toca un botón: dejaba series colgadas sin fecha de cierre, y había que inventar reglas para cuándo dejar de esperar.
- **La espera de llegada vale para los dos.** La partida siguiente nace mientras los dos miran el final de la anterior. A quien tenga el turno y todavía no llegó a la mesa se lo espera tres minutos, una sola vez, igual que a quien abre una sala (M5). El otro lee "Esperando que Fulano llegue a la mesa".
- **Una sala al mejor de tres que nadie ocupa** no deja una serie guardada: se borra al cancelarla, y la limpieza de salas se lleva las que quedaron sin ninguna partida.

### La revancha es un pedido, no una partida

- **Problema:** "rechazarla o irse no crea nada". El pedido no puede ser un evento de la partida vieja (ya terminó) ni una partida nueva a medio crear.
- **Elegido:** una tabla `revanchas` con quién la pidió, sobre qué partida y en qué quedó. La partida nueva nace recién con el "quiero", ya repartida. Si la partida vieja cerró una serie, la revancha es otra serie igual.
- **Contra el bot no hay pedido:** el bot quiere siempre y la partida se crea al tocar el botón, contra el mismo nivel.
- **El minuto se mide al leer, no con un reloj del servidor:** un pedido pendiente con la hora cumplida está vencido. Nadie tiene que marcarlo ni hay un trabajo en cola para eso.
- **Cada jugador la pide una sola vez por partida,** y un "no quiero" la cierra para los dos. Sin esa regla, pedir y cancelar en fila le hace parpadear la pantalla al otro. Si los dos la piden, la segunda vale como aceptar.
- **Quien la pidió y se va de la pantalla la retira:** la mesa manda la cancelación al cerrarse. Si no llegara, el pedido se vence al minuto.
- **Si al contestar "quiero" alguno ya está en otra partida,** no se crea nada y el pedido deja de estar a la vista. A quien lo pidió no se le dice que lo canceló ni que no le contestaron, porque no pasó ninguna de las dos: lee "Ya no se puede jugar la revancha".
- **De una partida que alguien abandonó no hay revancha.**
- **Dos toques a la vez no crean dos partidas:** todo corre con la fila de la partida vieja y las de los dos jugadores bloqueadas, igual que abrir una partida o una sala.
- **Se contesta como un canto:** los botones son "Quiero la revancha" (Basto) y "No quiero" (Copa), con los mismos íconos que en la mesa.

### El aviso en vivo no lleva datos

- Cuando cambia algo de la revancha, el servidor manda un aviso por el canal privado de la partida, el mismo de M5. No dice quién pidió ni qué se contestó: la mesa pregunta por HTTP, donde el servidor arma la respuesta para ese jugador.
- Sin tiempo real anda igual: la mesa pregunta cada pocos segundos. Medido con dos navegadores: el otro ve el pedido en medio segundo, y los dos están en la partida nueva unos dos segundos y medio después del "quiero".
- **La mesa no pregunta para siempre:** con un pedido a la vista pregunta hasta que se vence; sin ninguno, tres minutos; con la pestaña tapada, nada. Al volver a mirarla pregunta de nuevo.

### Dónde se elige y dónde se ve

- **Se elige en la pantalla de modos,** con la casilla propia del sitio, "Al mejor de tres", para los dos rivales. En el plan se había dicho que entre personas se elegía en la sala; quedó en los modos porque ahí no hay carrera: quien abre el link de invitación lee de entrada si es una partida o una serie, y eso no puede cambiar mientras lo lee.
- **La casilla contra el bot va en el renglón del nombre del nivel,** a la derecha. Esa pantalla tiene 5 px libres a 360 x 740: en un renglón propio empujaba el botón de jugar debajo de la barra. Medido: el botón termina donde terminaba (671 px en el celular, 588 px en 1355 x 638). Con otra persona no hay niveles y la casilla va sola, arriba del botón.
- **El marcador de la serie va en la barra de la mesa, junto al logo** ("Serie 1 a 0"). No agrega una fila, así que la mesa mide lo mismo. A 360 px quedan 19 px entre el marcador y el botón de ajustes.
- **El final de la partida dice cómo quedó la serie** y ofrece lo que corresponde: la partida siguiente si la serie sigue, la revancha si no.
- **El historial agrupa las partidas de una serie** bajo un renglón que dice cómo quedó, con una línea al costado. Una serie que se cortó porque alguno se fue dice quién la ganó, sin marcador: ese marcador no se jugó. La revancha de una partida suelta lo dice en su renglón.

### Dos arreglos que salieron probando

- **Los límites de pedidos se pisaban entre sí.** Laravel cuenta los pedidos por minuto de una persona en una sola cuenta, sea cual sea la ruta, salvo que se le ponga nombre al límite. Los que hace la mesa mientras se juega (más de uno por segundo contra el bot) gastaban los diez por minuto de "Jugar" e "Invitar": tocar esos botones justo después de una partida contestaba "demasiados pedidos". Venía de M5 y apareció acá, al invitar de nuevo después de una revancha. Ahora cada grupo de rutas lleva su nombre y su propia cuenta, con un test.
- **La llegada del otro hacía rebotar una jugada.** Cada jugada dice cuál fue el último evento que la mesa mostró, y el servidor la rechaza si después pasó algo. Que el otro llegara a la mesa contaba como "algo", aunque no cambia nada de lo que se ve. Con la revancha y las series los dos llegan casi juntos, así que pasaba seguido. Una llegada ya no cuenta.
- De paso se arregló un test que fallaba una vez cada tanto según las cartas (quería el primer canto del bot, y si era un falta envido la partida terminaba ahí). Puede ser el que puso en rojo la integración continua en M6.

### Lo que encontró la revisión de código de M12

Diez observaciones. Corregidas:

- **La mesa no decía de qué partida hablaba.** Sus pedidos iban sobre "la partida en curso del jugador", que en una serie pasa a ser la siguiente apenas termina la anterior. Una pantalla atrasada podía pedirle algo a la partida equivocada, o saltar a la nueva sin mostrar el final de la vieja. Ahora cada pedido lleva el número de la partida que la mesa está mostrando, y si esa ya no se juega el servidor contesta que no y la mesa se recarga.
- **El historial mostraba "Ganaste la serie 2 a 0"** cuando el otro había abandonado la segunda. Una serie cortada no muestra marcador, la haya abandonado quien iba ganando o quien iba perdiendo.
- **"Cancelaste el pedido" a quien no canceló nada** (el caso de la otra partida, más arriba).
- **Una respuesta vieja podía tapar a una más nueva** al preguntar por la revancha: las respuestas se toman en el orden en que salieron las preguntas.
- **Las preguntas sin fin** de una pestaña olvidada en el final de una partida.
- **Las series de un invitado que se borra** quedaban guardadas sin partidas: se las lleva la limpieza.
- **Consultas de más al pedir la revancha:** el estado se leía cuatro veces con todo bloqueado; ahora dos.
- **El marcador visto desde un asiento** estaba escrito en tres lugares: quedó en la serie.

Anotado como límite: la mesa del navegador calcula cómo queda la serie sumándole la partida que se acaba de ganar a lo que traía al cargar la página. El servidor es quien decide qué partida sigue; esa cuenta solo elige qué botón mostrar.

### Qué se probó

- **Con tests (679 en total):** una revancha aceptada crea una partida nueva con los mismos dos jugadores, en sus asientos y con el mano alternado; rechazarla, cancelarla, dejarla vencer o irse no crea nada; la serie se cierra cuando alguien gana dos y no admite una tercera si va 2 a 0; con 1 a 1 se juega la tercera; abandonar cierra la serie; cada partida cuenta por separado para el ranking; nadie pide ni contesta la revancha de una partida ajena; el canal de una partida terminada sigue siendo solo de sus dos jugadores.
- **Para probar una serie entera sin jugar 90 manos,** los tests usan partidas a un punto: el motor recibe los puntos como dato y la partida siguiente copia los de la anterior. Se cierran por la mesa de verdad, en una o dos jugadas, y gana quien el test diga.
- **Con dos navegadores reales,** a 360 x 740, 360 x 640 y 1355 x 638: pedir y querer; pedir y no querer; cancelar; irse de la pantalla; dejar vencer el minuto; una serie entera a tres partidas y su revancha. Sin errores ni scroll en la mesa.
- **En la integración continua,** una prueba de navegador nueva elige la serie en los modos, juega la primera partida hasta el final y pasa a la segunda desde el botón del final.
- Capturas en `docs/capturas/`: `modos-mejor-de-tres-celular.png`, `mesa-serie-celular.png`, `final-de-partida-en-serie-celular.png`, `final-de-serie-celular.png`, `final-con-revancha-celular.png`, `revancha-pedida-celular.png`, `revancha-te-piden-celular.png`, `historial-serie.png` y `historial-serie-celular.png`.

## M16. Panel de administración

Una pantalla, `/administracion`, para ver cómo está el sitio y mantenerlo sin tocar la base a mano. No muestra cartas ni deja cambiar un resultado.

### Lo que decidió Román

- **A quien no administra el sitio, el panel le contesta "no existe"** (404), con o sin sesión.
- **Una partida quedó sin movimiento** cuando contra el bot pasaron 24 horas sin jugadas, o 10 minutos entre dos personas. Entre personas los turnos vencen solos, así que diez minutos quieta ya es una falla.
- **Cerrarla no le da la partida a nadie:** queda cerrada sin ganador y no cuenta para el ranking.
- **Un apodo ocultado pasa a ser "Jugador" y un número,** y su dueño puede elegir otro desde el perfil.
- **La administración va en una cuenta aparte,** nueva, la misma en la máquina de desarrollo y en el sitio publicado. Al ingresar con ella se entra directo al panel, y le aparece "Administración" en el menú. Para cualquier otra cuenta todo sigue igual.

### Quién administra: una cuenta que sale del entorno

- **Problema:** hace falta una cuenta con poder en un sitio cuyo código es público, publicado en un servicio gratuito que no da una consola donde correr comandos. Y el registro no verifica el email: cualquiera puede registrarse con el que quiera.
- **Elegido:** el email y la contraseña de esa cuenta son dos variables del entorno (`ADMIN_EMAIL` y `ADMIN_PASSWORD`). Un comando de consola, `administrador:crear`, deja la cuenta como digan, y el contenedor lo corre en cada arranque. En el sitio publicado las dos variables se cargan en el panel de Render, igual que la contraseña de la base: no están en ningún archivo del repositorio.
- **Lo que diga el entorno es lo que vale.** La cuenta de ese email administra el sitio y ninguna otra: si se cambia el email, la anterior pierde el rol y se le cierran las sesiones. Con las variables vacías, nadie administra y el panel no existe para nadie. Así, ante la sospecha de que se filtró la contraseña alcanza con cambiar las variables.
- **Ninguna pantalla otorga el rol.** No se asigna en masa, y hay un test que manda el dato a mano al registrarse y al cambiar el apodo.
- **Si alguien se había registrado antes con ese email,** la cuenta pasa a ser la de administración con la contraseña del entorno, y a quien la había abierto se le cierran las sesiones y el "recordarme": no hereda el panel. Eso solo se puede garantizar con las sesiones guardadas en la base, como en el sitio publicado. Si están en otro lado, el comando se niega y pide otro email.
- **La contraseña no pasa por los archivos de configuración.** La configuración se guarda ya resuelta en un archivo del contenedor y se carga en cada pedido; la contraseña se lee del entorno solo cuando corre el comando. En la base queda su hash. Tampoco se pasa por la línea de comandos, donde quedaría a la vista en la lista de procesos.
- **Se descartó** una pantalla para crear la cuenta (sería una pantalla que otorga el rol) y sembrarla con una contraseña fija (el repositorio es público).

### Lo que muestra

- **De una partida en curso: quiénes juegan, el tanteo, la mano y cuándo se movió.** Cada renglón se arma eligiendo esos datos uno por uno; la vista de un asiento y el estado del motor no salen del servidor hacia el panel. Hay un test que busca las doce cartas repartidas en todo lo que recibe la pantalla.
- **De un trabajo fallido: qué era, cuándo falló y la clase del error, sin su mensaje.** El mensaje de un error de la base trae la consulta entera, y ahí puede venir el reparto de una partida que todavía se juega. El detalle queda en el registro del servidor.
- **De un jugador: el apodo, que ya es público, y si tiene cuenta.** El email no.
- **Las listas muestran veinte partidas de cada clase y los totales cuentan todas:** las quietas, empezando por la que hace más que está quieta; las que se juegan, por la que se movió último.
- **Si una partida no se puede volver a pasar por el motor** (un evento dañado, una regla que cambió), su renglón sale igual, sin tanteo. Es justo la que hay que poder cerrar.

### Lo que deja hacer

- **Cerrar una partida que quedó sin movimiento.** Le agrega un evento al final de su lista ("cierre") y no borra nada. Queda en un estado propio, "cerrada": no es "terminada" (nadie llegó a los puntos) ni "abandonada" (nadie la dejó). Por eso no entra en el ranking ni en el historial, y deja de ser la partida en curso de sus jugadores, que al volver a la mesa leen qué pasó. Si era de una serie, la serie se cierra con ella, sin ganador.
- **Reintentar o descartar un trabajo fallido,** con los mismos comandos que trae Laravel.
- **Correr la limpieza ahora:** la misma que el sitio hace sola (las salas cada cinco minutos, los invitados una vez por día).
- **Ocultar un apodo.** Como el apodo vive en un solo lugar, deja de verse en todas las pantallas. No se oculta el de un jugador de ejemplo ni el de la administración.
- **Cada acción queda anotada** con quién, qué y cuándo, en la misma transacción que la acción: o quedan las dos o no queda ninguna.
- **Las acciones vuelven a comprobar el rol** aunque la ruta ya lo haya mirado.
- **Las que no tienen vuelta fácil se confirman en el lugar:** el botón se abre en dos, uno que dice exactamente lo que va a pasar y otro para arrepentirse.

### La pantalla

Román pidió que quedara linda y no pareciera un panel genérico. Sale de las piezas del sitio:

- **El pizarrón del club:** arriba, un bloque de paño con el estado dicho en cuatro frases, cada una contada con fósforos como el tanteo. La que pide que alguien haga algo lleva una ficha en Copa (sobre el paño, Copa va como ficha llena y nunca como texto). Cada frase lleva a su parte del panel.
- **Cada partida con su tanteo dibujado,** el mismo tanteador de la mesa, desde escritorio. En el celular va en palabras.
- **El cuaderno,** con el margen de una libreta.
- No hay gráficos, tarjetas en grilla ni nada que se mueva solo.

### Lo que encontraron la revisión de código y la de seguridad

Diez observaciones, corregidas salvo las dos del final:

- **El rol no se quitaba nunca:** cambiar el email dejaba a la cuenta vieja con el panel. Ahora la cuenta del entorno es la única.
- **Las sesiones solo se cerraban si estaban en la base,** y el comando decía que sí en cualquier caso. Ahora se niega a tomar una cuenta ajena si no las puede cerrar.
- **La contraseña quedaba en la configuración guardada del contenedor.**
- **Una acción podía quedar hecha sin anotarse** si fallaba la anotación.
- **Con muchas partidas, los números del pizarrón salían de una lista cortada:** una partida trabada entre personas podía no aparecer detrás de cuarenta partidas viejas contra el bot.
- **Una sola partida ilegible tiraba abajo el panel entero.**
- **El cierre usaba el estado "abandonada" sin ganador,** que quería decir "sala que nadie ocupó": cada parte del sitio lo distinguía con un parche distinto. Pasó a tener su estado.
- **Cuentas repetidas en tres lugares** (qué es una sala vencida, qué es una partida quieta, cómo se dice un día).
- **Anotado, sin cambiar:** el panel vuelve a pasar por el motor hasta cuarenta partidas en cada carga. Para un sitio de este tamaño alcanza.
- **Anotado, sin cambiar:** el 404 del panel no es idéntico al de una página que no existe (el segundo no abre sesión). La seguridad del panel no depende de que nadie sepa que existe: sus rutas están en este repositorio, que es público.

### Qué se probó y qué no

- **Con tests (711 en total):** cada ruta con visitante sin sesión, invitado y jugador con cuenta (404 en todas) y con la administración; el panel no recibe ninguna carta; cerrar una partida agrega un evento y no borra nada; toda acción deja registro; ninguna pantalla otorga el rol; un apodo ocultado deja de verse en el ranking, el historial y la mesa del rival; y el comando que crea la cuenta, con cada caso de más arriba.
- **En un navegador real,** a 360 x 740 y en 1355 x 638: sin sesión el panel contesta 404; con la cuenta se entra directo; se cerraron partidas quietas confirmando en el lugar, también con teclado (el foco va al botón que confirma y Esc lo deshace), y se corrió la limpieza. Sin scroll horizontal ni errores.
- **Falta:** una prueba de navegador automática del panel en la integración continua. No se sumó para no alargar esa corrida.
- **Pendiente para la API (M9):** que un apodo ocultado tampoco se vea ahí. Hoy la API no existe.
- Capturas en `docs/capturas/`: `administracion.png`, `administracion-celular.png`, `administracion-cuaderno.png` y `administracion-cuaderno-celular.png`.

## M13a. Torneo relámpago contra bots

Un torneo corto de eliminación directa que se juega solo: una persona y, en los demás lugares, bots. La versión entre personas (M13b) viene después.

### Lo que decidió Román

- **De cuatro o de ocho,** a elegir al armarlo. Entra elegido el de cuatro, que es el más corto.
- **Cada partida va a 15 puntos.** Un torneo de ocho son tres partidas si se llega a la final: a 30 no se termina en una sentada.
- **Los bots son los jugadores de ejemplo del ranking,** cada uno con su nivel. Ya tienen nombre y el sitio ya los marca como bots.
- **De las partidas entre bots se ve solo el resultado.**
- **Para el ranking cuentan las partidas de la persona,** con la regla de siempre (contra un bot Intermedio o más). Las de bot contra bot no cuentan.

### Cómo se guarda

- **Problema:** un torneo es una persona, unos bots sorteados, unas llaves que se van llenando y varias partidas, de las que solo algunas las juega alguien.
- **El torneo guarda quién ocupa cada lugar.** Es una lista fija que se decide al armarlo: de cada bot, el apodo y el nivel; el lugar de la persona no guarda nada, porque es la dueña del torneo. Las llaves, los ganadores y el campeón se dicen por número de lugar.
- **Los bots no necesitan su fila de jugador.** Alcanza con el apodo y el nivel, que salen de la misma lista que arma el ranking de ejemplo. Así un torneo no depende de que el ranking esté sembrado, y si esa siembra se rehace las llaves viejas no pierden los nombres.
- **Cada cruce es una fila:** qué dos lugares se cruzan, cuánto hizo cada uno y quién pasó. Se crean todos al armar el torneo; los de las rondas que vienen esperan vacíos. El ganador del cruce 0 y el del 1 se encuentran en el cruce 0 de la ronda siguiente, y así.
- **La partida de la persona es una partida común contra el bot,** guardada como eventos, con el número de su torneo. Por eso aparece sola en el historial y cuenta para el ranking sin ningún código aparte.
- **Las partidas entre bots no se guardan: se guarda su resultado.** Se juegan sobre el motor con los mismos bots que una partida de verdad (el simulador que ya armaba el ranking de ejemplo), y todo su azar sale de la semilla del torneo y del lugar del cruce en las llaves. La misma semilla da siempre el mismo sorteo y los mismos resultados. No se guardan como partidas porque no son de nadie: ocuparían la base, y habría que sacarlas a mano del ranking y del panel de administración.
- **Se descartó** guardar los inscriptos en una tabla aparte con una fila por lugar: es una lista que no cambia nunca y siempre se lee entera.

### Cómo avanzan las llaves

- **Una sola función las pone al día** (`Torneos::avanzar`). Ronda por ronda: si la persona todavía tiene su partida por jugar o jugándose, se detiene. Si ya la cerró, anota cómo le fue, juega las partidas entre bots de esa ronda y arma la siguiente. Con la final resuelta hay campeón.
- **Los resultados de una ronda aparecen cuando la persona termina la suya.** Las demás partidas "se juegan a la par": hasta entonces nadie sabe contra quién le va a tocar después.
- **Si la persona queda afuera, lo que falta se juega de corrido** hasta que hay campeón: las llaves nunca quedan a medio llenar.
- **Se puede llamar de más.** Bloquea la fila del torneo, así dos pedidos a la vez no juegan dos veces la misma partida; con las llaves al día no hace nada. La llama un trabajo de la cola cuando se cierra una partida del torneo (jugar tres partidas entre bots no puede demorar la última carta de la persona) y también la pantalla de las llaves, por si ese trabajo todavía no corrió.
- **Pasa solo quien gana su partida.** Abandonarla es quedar afuera. Si quedó tanto tiempo sin jugarse que la cerró la administración, también pasa el bot. Y dejar el torneo entre dos rondas hace pasar al rival sin jugar.
- **Una persona juega un torneo a la vez,** y la partida del torneo es su única partida abierta: con otra sin terminar no empieza, y la pantalla dice por qué.

### La partida de torneo en la mesa

Es la mesa de siempre, con cuatro diferencias:

- **El bot va con su apodo.** Que es un bot lo dicen su ícono y los fósforos de su nivel, igual que en las llaves.
- **El tanteador va a 15:** tres grupos de cinco y sin la raya del medio. Las malas y las buenas son de la partida a 30.
- **En la barra, donde iría el marcador de una serie, dice qué partido es** ("Cuartos", "Semifinal", "Final"). Va en una palabra porque "Cuartos de final" no entra en un celular.
- **Al terminar no hay revancha.** El final dice si se pasa de ronda, si se es campeón o si se quedó afuera, y lleva a las llaves.

### La pantalla de las llaves

- **Problema:** la primera versión era prolija y podía ser de cualquier sitio: nombres en gris sobre verde, unidos por líneas. Román la vio y dijo que se podía ver mil veces mejor. Tenía además un defecto de lectura: sin una caja por cruce, la llave parecía agrupar la mitad de un cruce con la mitad del siguiente. No usaba ninguna de las piezas propias del sitio.
- **Elegido por Román, entre tres opciones:** las llaves con naipes. Cada jugador se ve como una carta del mazo propio.
- **La carta de un bot dice su nivel con la jerarquía del truco:** el más difícil es el ancho de espada; los difíciles, el ancho de basto y los dos sietes bravos; los intermedios, los tres; los fáciles, cuatros. Está en `JugadoresDeEjemplo::cartas()` y hay un test que comprueba que ningún bot le gana con la carta a uno de un nivel más alto.
- **La persona es el 4 de copas** (elegido por Román). M18 ya decía que la carta de perfil empieza por los cuatros: queda encaminado sin adelantar nada más.
- **Cada cruce es una baza:** las dos cartas pisándose en diagonal, con los mismos estilos que la baza de la mesa, y al lado quiénes son y cuánto hicieron. Quien pasa queda arriba y con su tanteo en Oro; **quien pierde queda boca abajo**. Un cruce que todavía espera jugadores muestra el lugar marcado, como una baza vacía.
- **Arriba, la partida propia puesta sobre la mesa:** la carta del rival y la de la persona, en grande, con cada nombre a la altura de su carta, y la frase que dice qué toca ("Jugás la semifinal contra Don Anselmo") con su botón.
- **Las llaves siguen dibujadas con el ícono del torneo:** un fósforo que une cada par de cruces y enciende la cabeza en Oro cuando llegaron los dos, como la del logo sobre el paño.
- **La partida propia lleva la misma marca que "Sos mano"** en la mesa, con "Te toca".
- **En el celular las rondas van una debajo de la otra** y no se dibujan llaves: el orden ya dice quién se cruza con quién.
- **Contraste:** el texto a media tinta es Naipe al 70 % sobre Paño (4,7:1). El tanteo en Oro va en 20 px y negro, que es texto grande (Oro sobre Paño da 4,3:1 y solo vale para eso). Las cartas no cambian con el modo de día y de noche.
- **Se descartó:** la planilla del club (las llaves en una hoja color naipe, con el tanteo en fósforos), más sobria; y arreglar solo los defectos de la primera versión.

### El movimiento de las llaves

Hecho con la skill de animación y el criterio de `emil-design-eng`. La pantalla se abre pocas veces por torneo, así que admite movimiento; cada cosa que se mueve tiene un motivo.

- **Al entrar, las dos cartas de arriba se reparten.** Es el reparto del resto del sitio, con su misma clase: el único movimiento que no responde a una acción.
- **Al volver de jugar, los resultados nuevos se cuentan** en vez de aparecer de golpe: la carta de quien perdió se da vuelta, aparece el tanteo, se enciende la llave y las cartas de quienes pasaron llegan al cruce siguiente. Sirve para que se entienda qué cambió mientras uno no miraba. Si quedó afuera, se cuenta el resto del torneo, ronda por ronda, hasta la carta del campeón.
- **Cada cruce cuenta lo suyo cuando está a la vista.** Las llaves empiezan debajo de la primera pantalla y en el celular las rondas van apiladas: si el relato arrancara al cargar, pasaría sin que nadie lo viera. Lo que está a la vista a la vez se cuenta en orden.
- **Qué resultados ya se mostraron lo recuerda el navegador.** La primera vez que se abre un torneo no se cuenta nada, y recargar no lo repite.
- **Ingredientes:** solo `transform` y `opacity`, con la curva del sitio; cada movimiento dura entre 160 y 300 ms; la carta que se da vuelta usa la pieza que ya existía para eso. La llave encendida va en piezas aparte de la tenue, para poder dibujarla sin animar colores.
- **Se corta con un toque o con Escape.** Se escucha el toque entero y no el dedo que apoya: en el celular, bajar hasta las llaves no puede cortarlo.
- **Con movimiento reducido no se cuenta nada:** la página ya dice cómo quedó, y las cartas de arriba aparecen con un fundido.
- **Lo que no se puede juzgar leyendo el código** es si el ritmo se siente bien. Se miró cuadro por cuadro en el navegador, pero cómo se siente lo dice quien lo usa.

### La pantalla de modos, con dos cartas en la mano

- **Problema:** con el torneo ya son dos los juegos que se juegan. La pantalla tenía previsto ese caso (la mano arriba y el detalle debajo), pero así el botón de jugar quedaba a 896 px en una ventana de 638 de alto.
- **Elegido:** en pantallas anchas la mano va al lado del detalle, en una grilla; en el celular las cartas son chicas y van en una fila, y el resumen del juego no se muestra (la carta y el título ya lo dicen). Medido: el botón termina a 588 px en 1355 x 638, igual que antes, y a 656 px en 360 x 740, donde la barra de abajo empieza a los 676.
- **El tamaño del torneo se elige con el mismo molde que el nivel del bot:** el cuadrado de cuatro fósforos, uno para el torneo de cuatro y dos para el de ocho.
- **Queda para cuando haya tres cartas o más** (el de a cuatro, los desafíos): la grilla ya lo contempla, en dos filas y más chicas, pero no se midió en el navegador.
- **Al elegir, la carta se levanta** (decidido por Román el 10 de octubre de 2026, y cambia su pedido anterior de que las cartas no se movieran al elegir). La elegida queda un poco más arriba, como una carta de la mano que se va a jugar; la otra baja y se apaga; al apretarla se hunde apenas, y con mouse la que no está elegida se asoma. Ninguna cambia de lugar. El nombre y las opciones del juego elegido entran con un fundido corto en vez de cambiar de golpe, solo después de elegir. Es una transición de `transform` y `opacity` de 220 ms: si se elige otra a mitad de camino, vuelve desde donde esté. Con movimiento reducido la carta queda levantada sin viaje y el detalle aparece con un fundido. El botón de jugar no se mueve de lugar.

### Lo que encontró la revisión

- **La cabeza de los fósforos de las llaves no se veía.** Su clase se llamaba igual que la cabeza de la llave de los ajustes de la mesa, que arranca transparente. Se le cambió el nombre.
- **"Cuartos de final" se montaba sobre el botón de ajustes** en la barra de la mesa, a 360 px. Va en una palabra.
- **Quien ganaba el torneo podía leer dos veces el mismo nombre** ("Pasó Doña Elvira. El campeón es Doña Elvira."). Se dice una sola vez.
- **Dejar el torneo justo después de ganar una partida** podía encontrar las llaves sin anotar. Ahora primero se ponen al día.
- **Un tanteador de una partida a menos de cinco puntos no dibujaba ningún fósforo.** Solo pasa en las pruebas, pero se corrigió.

### Qué se probó y qué no

- **Con tests (751 en total):** las llaves no repiten jugador; cada ronda empareja a los ganadores de la anterior; un torneo de ocho termina en siete partidas con un solo campeón (y uno de cuatro, en tres); la misma semilla da el mismo sorteo y los mismos resultados entre bots; quien abandona su partida queda eliminado; todo bot se ve marcado como bot, con su nivel; las partidas entre bots no dejan nada en el ranking; el torneo de otro no existe; y cada pantalla en cada estado.
- **En un navegador real,** a 360 x 740, 360 x 640 y 1355 x 638: se armó un torneo desde los modos, se jugó en la mesa (sin scroll en ningún tamaño), se quedó afuera y se salió campeón; de día y de noche; sin errores ni scroll horizontal. El turno del bot y el avance de las llaves salieron de la cola.
- **En la integración continua** se sumó una prueba de navegador corta: arma un torneo desde los modos, ve las llaves y entra a la mesa de la semifinal.
- **No se jugó un torneo entero a mano, carta por carta:** las partidas de las pruebas se cerraron con un bot que se va al mazo o se armaron con partidas simuladas a 15.
- **Límites:** la repetición del historial y el panel de administración dibujan el tanteador a 30 también para una partida de torneo. Un torneo que su dueño no vuelve a abrir queda "en curso" para siempre: no molesta, y al volver puede seguirlo o dejarlo. No hay una lista de torneos jugados: se llega a las llaves de uno desde el final de su partida o mientras está en curso.
- Capturas en `docs/capturas/`: `torneo-llaves.png`, `torneo-llaves-celular.png`, `torneo-campeon.png`, `torneo-afuera-celular.png`, `torneo-llaves-de-noche.png`, `mesa-torneo-celular.png`, `modos-torneo.png` y `modos-torneo-celular.png`.

## M19a. Truco de a cuatro contra bots

Dos contra dos: una persona con un bot de compañero contra dos bots. La versión entre personas (M19b) viene después.

### Lo que decidió Román

- **El reglamento que faltaba.** El tanto de quien se fue al mazo no cuenta para el envido de su pareja. Una contraflor vale lo de siempre (6 querida, 4 no querida) aunque una pareja tenga dos flores. Una carta ya jugada sigue valiendo en la baza aunque su dueño se vaya al mazo después, y una flor ya cantada se anota igual. Cada regla tiene su test en el motor.
- **Las señas son nueve y fijas:** una por cada carta brava (ancho de espada, ancho de basto, siete de espada, siete de oro), una para "un tres", "un dos" y "un ancho falso", "no tengo nada" y "tengo buen envido". No hay texto libre.
- **Cada seña se muestra con el gesto, lo que quiere decir y un dibujo,** para quien no las sabe todas. Y la persona solo puede hacer las de lo que tiene: no se le miente al compañero.
- **Cuando le cantan a la pareja, contesta la persona.** Su compañero bot espera; si la persona ya se fue al mazo, contesta él.
- **Un solo nivel para los tres bots,** el que se elige en los modos.
- **Para el ranking cuenta como cualquier partida contra bots,** con la regla de siempre.
- **La mesa va en cruz** (elegida entre tres disposiciones): el compañero enfrente, un rival a cada lado y la persona abajo.
- **Los rivales de los costados tienen las cartas acostadas, mirando al centro, y más grandes.** La primera versión las tenía paradas y chicas; Román las vio y pidió que se vieran mejor. Las cartas que se juegan al centro van derechas, para leerlas de un vistazo.

### Cómo se guarda y quién juega

- **Es una partida más, con cuatro asientos.** La persona es el 0, su compañero el 2 y los rivales el 1 y el 3. Se guarda como eventos, igual que todas, así que el historial, el ranking y el panel de administración la encuentran sin código aparte. El ganador de la partida es una pareja: la 0 es la de la persona.
- **Los tres bots juegan desde la cola,** de a una jugada por trabajo, como el bot del mano a mano. Cada uno decide con lo que vería un jugador sentado en su asiento, más las señas de su compañero. Hay un test que revisa que a ninguno le lleguen cartas ajenas.
- **Una seña no es una jugada.** No pasa por el motor (que no sabe nada de señas): queda guardada como un evento aparte, no espera el turno y no hace rebotar una jugada que venía en camino. Le llega solo al compañero: las de los rivales no viajan nunca al navegador de la persona, y la de la persona no la recibe ningún bot rival (test).
- **El compañero bot hace todas sus señas al repartir.** Salen de sus cartas, así que no puede señalar algo que no tiene.
- **Sin series ni torneo.** La revancha sí: es otra partida de a cuatro, y el mano pasa al asiento siguiente.

### Los bots que juegan en pareja

- **Problema:** con reglas fijas, la pareja de nivel Difícil le ganaba a la de Intermedio 48 de 100 partidas. No eran escalones. Román eligió hacer un Difícil de verdad antes que dejar los nombres como estaban.
- **Elegido:** el Difícil y el Ultra sacan cuentas. Antes de tirar una carta o de contestar un truco imaginan 36 veces las cartas que no ven (respetando lo que ya se jugó y lo que dijeron las señas del compañero), juegan cada mano imaginada hasta el final y se quedan con la jugada con la que más ganan. El Ultra, además, supone que un rival que cantó truco tiene con qué, se acuerda de cómo vienen contestando y arriesga según el tanteo.
- **Medido,** con 300 partidas por cruce desde una semilla fija: Difícil le gana a Intermedio 184 (61 %), Ultra a Difícil 176 (59 %) y Ultra a Intermedio 188 (63 %). La medición es un test aparte (`--group medicion`, unos seis minutos) con pisos por debajo de lo medido y por encima del empate.
- **El Fácil es el mismo del mano a mano:** tira cualquier cosa válida.

### La mesa en cruz

- **Sale de la mesa de siempre.** El JavaScript de la mesa de a cuatro toma el de la mesa de a dos y le cambia lo que depende de cuántos son: dónde está cada asiento, cómo se nombra, la baza y las señas. Hablar con el servidor, esperar a los bots, la barra de cantos, el ritmo, el sonido, el tanteador y el final son los mismos. De la plantilla se separaron en dos piezas los ajustes y la barra de cantos, que usan las dos mesas.
- **"Nosotros" y "ellos".** El tanteo es de las parejas y se anota como en el club. A los rivales se los nombra por el lado ("el de la derecha"), que es lo que se dice jugando y entra en un renglón del celular.
- **En el centro se ve una sola baza,** la que se está jugando, con cada carta delante de quien la tiró. Cuando se cierra queda arriba la que ganó y las demás se apagan; si la mano sigue, las cuatro vuelven al mazo. De las bazas anteriores queda escrito de quién fue cada una.
- **Se marca un solo lugar: el de quien tiene que tirar.** La marca va dando la vuelta a la mesa con el turno. Junto al nombre de cada bot, su ícono pasa a ser un reloj mientras piensa.
- **Quién canta:** la palabra va grande al centro, como siempre, y el nombre de quien la dijo queda marcado como una ficha de naipe mientras dura.
- **Los tantos del envido se dicen desde el lugar de cada uno,** en ronda. Van sobre una ficha de paño hondo porque pueden caer encima de una carta, y Oro sobre Naipe no se lee (sobre Paño hondo da 6,3:1). El que pierde queda a media tinta sin que la ficha se transparente.
- **Quién es mano** lo dice una ficha redonda apoyada en la esquina de sus cartas. No ocupa lugar, así nada se corre de una mano a la otra.
- **Las cartas que alguien muestra al cerrar la mano** (ganó el envido, sumó por una flor) van en el cierre, dibujadas en chico con su nombre: a ese tamaño, de una carta se leen el número y el palo, y eso es lo que se dibuja.
- **La medida:** todas las cartas salen de una sola variable, igual que en la mesa de a dos, con otra cuenta. Medido sin scroll a 360 x 740, 360 x 640, 1355 x 638 y 1920 x 1080.

### Las señas en la pantalla

- **Lo que dice el compañero se ve siempre,** dibujado al lado suyo: la carta, si la seña la dice entera; el número solo, si no dice el palo; un lugar vacío para "no tengo nada"; y el ícono del envido. Al repartir, la mesa lo dice además con palabras.
- **El botón "Señas" abre una ventanita** como la de los ajustes: arriba, cada seña del compañero con el gesto y lo que quiere decir; abajo, las que puede hacer la persona, cada una un renglón que se toca. No traba la mesa y se usa también mientras juegan los bots. La que ya se hizo queda marcada.
- **Se descartó** mostrar el gesto y el significado siempre a la vista: con tres o cuatro señas no entra en un celular sin sumar filas a la mesa.

### El historial y la repetición

- **En la lista** una partida de a cuatro dice que lo fue, con quién se jugó, y el tanteo va como "nosotros" y "ellos".
- **La repetición tiene su propia mesa en cruz.** El relato se arma igual que siempre, volviendo a pasar los eventos por el motor, pero cada cuadro lleva cuatro manos y la baza de ese momento. Como los otros tres son bots, se ven las cartas de todos: las de ellos van dibujadas en chico (número y palo). Una seña se cuenta con palabras, sin mover nada.
- **Cuál de las dos mesas se dibuja lo dicen los datos,** porque el cartel del historial sirve para cualquier partida de la lista.

### Los modos, con tres cartas en la mano

- La carta del de a cuatro se reparte sola al ponerle el botón a su rival en el catálogo. Con tres cartas la mano va en dos filas en pantallas anchas. Medido: el botón de jugar termina a 587 px en 1355 x 638 y a 656 px en 360 x 740, donde la barra de abajo empieza a los 676.
- De a cuatro no se ofrece la serie al mejor de tres, y el nivel se describe en plural: son tres bots.

### Lo que encontró la revisión

- **Las cuatro fichas de los tantos se pisaban** en una mesa baja: su letra se medía contra la ventana y no contra la carta. Ahora sale de la misma medida que la cruz, y "son buenas" va en dos renglones.
- **Los tres dorsos de cada bot parecían dos:** la última tapaba a la del medio. Van en abanico, con la del medio arriba.
- **Los dorsos saltaban al terminar el reparto:** la inclinación del abanico estaba en el mismo elemento que se mueve. Ahora el lugar lleva la inclinación y la carta, adentro, es lo que viaja.
- **Una seña recién hecha podía volver a ofrecerse** si justo llegaba un paso armado un instante antes. La mesa recuerda las que hizo en la mano.
- **La prueba de navegador del torneo elegía "la segunda carta" de los modos,** que pasó a ser la del de a cuatro. Las dos pruebas eligen ahora la carta por su dibujo.

### Qué se probó y qué no

- **Con tests (779 en total):** el reglamento de a cuatro que faltaba, un test por regla; partidas enteras contra cada nivel sin que ningún bot falle; que a la persona no le llega ninguna carta ajena sin jugar ni mostrar, tampoco en la página; las señas (solo de lo que se tiene, una vez por mano, llegan solo al compañero, no existen en el mano a mano); quién contesta un canto; el compañero que sigue solo; el ranking; la revancha; el historial y la repetición con el mismo tanteo que tuvo la partida.
- **En un navegador real:** una partida entera contra bots difíciles a 360 x 740 (30 a 24, 206 eventos), sin errores ni scroll; manos sueltas a 360 x 640 y 1355 x 638; las señas, los tantos de a cuatro y el cierre; el historial y la repetición en cinco tamaños.
- **En la integración continua** se sumó una prueba de navegador: una mano de a cuatro con una seña, y el reparto de la siguiente.
- **No se probó** con lector de pantalla (queda para el pulido final) ni la mesa de noche más allá de abrirla. Cómo se siente el ritmo con tres bots lo dice quien juega.
- **Límites:** la mesa ancha de escritorio sigue pendiente para las dos mesas; la repetición no marca quién es mano; el panel de administración muestra la partida en un renglón, sin la mesa.
- Capturas en `docs/capturas/`: `mesa-de-a-cuatro.png`, `mesa-de-a-cuatro-celular.png`, `mesa-de-a-cuatro-senas-celular.png`, `mesa-de-a-cuatro-tantos-celular.png`, `mesa-de-a-cuatro-cierre-celular.png`, `modos-de-a-cuatro.png`, `modos-de-a-cuatro-celular.png`, `repeticion-de-a-cuatro.png` y `repeticion-de-a-cuatro-celular.png`.

## M15. Instalable en el celular

Que el sitio se instale como una aplicación y avise cuando te toca aunque no lo estés mirando. Sigue siendo el mismo sitio: no hay una aplicación aparte ni pasa por ninguna tienda.

### Lo que decidió Román

- **La librería de avisos se usa directo** (`minishlink/web-push`, que hace solo el envío). La tabla de suscripciones y el trabajo en cola son propios, con nombres en español como el resto. Se descartó el paquete armado para Laravel, que trae su tabla y su forma de avisar ya hechas: menos código, pero una capa más que no se puede explicar.
- **Quien juega sin cuenta también recibe avisos.** El caso más común es alguien que entra por un link y juega sin registrarse.
- **Se avisa de tres cosas, siempre entre personas y solo si no se tiene la mesa a la vista:** que te toca jugar, que te cantaron algo, y que alguien se sentó en la sala que abriste.
- **Lo visible se hace con las piezas del sitio y con movimiento propio,** no solo para que funcione.

### Instalarlo

- **El manifiesto** (`public/manifest.json`) dice el nombre, los colores (el paño de fondo y el paño hondo en la barra del sistema) y los íconos.
- **Los íconos salen del isotipo,** dibujados con un navegador y guardados como imágenes en `public/iconos/`: dos comunes, uno "recortable" para Android (el paño llega a los bordes y los fósforos quedan en la zona que nunca se recorta), uno para iPhone y la insignia de una tinta que acompaña a un aviso.
- **El botón de instalar aparece solo cuando el navegador avisa que se puede.** Está en el pie y en los ajustes de la mesa. Ofrecer algo que no se puede hacer es peor que no ofrecerlo. En un iPhone el navegador no deja abrir ese cartel: se muestra cómo hacerlo a mano.
- **Se comprobó que el navegador lo da por instalable** preguntándoselo directamente (sin errores de instalación ni del manifiesto). El plan pedía medirlo con Lighthouse, que sacó esa revisión en sus versiones actuales: se usó la misma comprobación que hace el navegador.

### Qué se guarda en el equipo, y qué no

- **Problema:** una aplicación instalada tiene que abrir rápido y no mostrar el error del navegador cuando no hay conexión. Pero el truco se juega en vivo: una partida guardada mostraría cartas viejas, o de otra persona que usó el mismo equipo.
- **Elegido:** un service worker (`public/sw.js`) que guarda solo lo que no depende de quién lo pide: los estilos, los scripts y las fuentes compilados, los íconos y la página de "sin conexión". Qué se guarda lo decide una sola función con una lista corta.
- **Una página pedida va siempre a la red.** Solo si la red no contesta se muestra la de "sin conexión". Ni la mesa ni ningún pedido suyo quedan guardados.
- **La página de "sin conexión" es la misma para todos:** se baja sin la sesión de nadie, y no lleva encabezado ni nada personal.
- **Cuando se publica una versión nueva se borra lo guardado por la anterior.** La página registra el service worker con la versión de lo compilado en la dirección; al cambiar, el navegador lo instala de nuevo.
- **Se descartó** guardar las páginas del sitio para verlas sin conexión (el ranking, las reglas): serían datos viejos presentados como actuales, y no es lo que pide el módulo.

### La página de "sin conexión"

- **El dibujo es el mazo, boca abajo.** Sin conexión las cartas "volvieron al mazo". No promete lo que no puede cumplir: dice que sin conexión no hay mesa.
- **"Probar de nuevo" no va a un lugar fijo:** pregunta si el sitio contesta y, si contesta, vuelve a pedir la página que se quería abrir. También prueba sola cuando el equipo avisa que volvió la conexión.
- **Movimiento:** al abrirse, las tres cartas llegan al mazo de a una, que es el reparto al revés (300 ms cada una, con la curva del sitio). Si se prueba y sigue sin andar, la carta de arriba se levanta y vuelve a caer, como quien va a repartir y no puede; lo mismo va escrito. Con movimiento reducido las cartas aparecen con un fundido y la de arriba no se mueve.
- Medida sin scroll a 360 x 640, 360 x 740 y 1355 x 638.

### Los avisos

- **El permiso se pide con un botón, nunca al cargar.** Un navegador que pregunta de entrada recibe un "no" que después cuesta deshacer. Hay dos lugares: en la sala, para quien se va a mandar el link por otra aplicación, y un renglón más en los ajustes de la mesa, con la misma llave de fósforo del sonido y el ritmo. Ahí mismo se dan de baja.
- **A quién se le avisa se decide dos veces.** Al pasar el turno: si esa persona no pidió avisos o tiene la mesa a la vista, no se encola nada. Y unos segundos después, al ir a mandarlo: si en ese rato jugó, volvió a la mesa o la partida se cerró, tampoco se manda.
- **"Tener la mesa a la vista" lo dice la propia página:** avisa cuando queda a la vista, lo repite cada tanto y avisa cuando se tapa o se cierra. Si se cerró sin llegar a avisar, la marca se vence sola a los 45 segundos.
- **El aviso sale de un trabajo en cola,** con cinco segundos de demora. Uno que falla no se reintenta: un "te toca" que llega tarde es peor que ninguno.
- **El texto nunca nombra una carta.** Se lee en la pantalla bloqueada del celular. Dice "Te toca jugar" o "La Tana te cantó truco", y nada más.
- **Un aviso nuevo de la misma partida reemplaza al anterior** en vez de apilarse.
- **El sitio solo le manda avisos a los servicios de avisos conocidos** (el de Chrome, el de Firefox, el de Windows y el de Apple). La dirección la da el navegador de cada persona: si se aceptara cualquiera, alguien podría hacer que el servidor le hable a la máquina que quiera. Tampoco se siguen redirecciones.
- **Un navegador que ya no existe se da de baja solo:** cuando su servicio contesta que la suscripción venció, se borra.
- **Contra el bot no hay avisos:** el bot juega en el momento y no hay a quién esperar.

### Las claves de los avisos

- **Problema:** el sitio firma cada aviso con un par de claves, y tienen que ser siempre las mismas: si cambian, todas las suscripciones hechas dejan de recibir avisos. El contenedor se rearma en cada publicación y su disco no sobrevive; y el repositorio es público.
- **Elegido:** se generan solas la primera vez que hacen falta y quedan en la base, con la privada cifrada con la clave de la aplicación. No hay que cargar nada a mano.
- **Se descartó** pedirlas como dos variables de entorno: es lo más común, pero suma un paso manual en cada lugar donde se instale el sitio, y la base ya es lo único que persiste.
- **A OpenSSL se le pasa una configuración mínima propia** para generar la clave, en vez de dejar que busque la de la máquina: en Windows esa suele estar mal y la clave no sale.

### Lo que encontró la revisión

- **El envío estaba escrito para una versión anterior de la librería** y fallaba al primer aviso. Los tests no lo veían, porque en lugar del envío real usan uno de mentira: apareció al hacer una pasada de verdad contra el servicio de avisos de Chrome.
- **La ventanita de ajustes quedaba debajo del fósforo de la cuenta regresiva** entre personas. Venía de antes y se notó al sumarle renglones.
- **En el registro de errores quedaba la dirección del navegador** cuando un aviso fallaba. Ahora queda solo el código de la respuesta.
- **El primer reparto de una partida mandaba dos avisos** a quien abrió la sala: el de la llegada y un "te toca". Queda el de la llegada.

### Qué se probó y qué no

- **Con tests (800 en total):** quién puede anotarse y con qué direcciones; un navegador es de un solo jugador y cada jugador tiene un tope; la baja; que el aviso se encola cuando el turno pasa a alguien suscripto que no tiene la mesa a la vista, y no en el caso contrario; que ningún aviso nombra una carta, jugando partidas enteras; que al mandarlo no sale si ya jugó, volvió o la partida se cerró; las claves; el manifiesto y sus íconos; y que el service worker guarda solo lo estático.
- **En un navegador real:** el sitio queda instalable; lo guardado después de recorrer el sitio y jugar son 15 archivos, todos estáticos, y la única página es la de "sin conexión"; sin conexión se ve esa página en cualquier dirección, también en la mesa; "Probar de nuevo" avisa si sigue sin andar y vuelve a la página pedida cuando anda.
- **En la integración continua,** la prueba de navegador que juega una mano revisa además qué quedó guardado.
- **El envío de verdad se probó contra el servicio de avisos de Chrome** con una suscripción inventada: lo cifró, lo firmó, lo mandó, y el servicio contestó lo esperable (que esa suscripción no existe).
- **No se pudo probar desde la máquina de desarrollo:** instalarlo en un celular y ver llegar un aviso. El navegador de pruebas tiene los avisos bloqueados. Eso se prueba con el sitio publicado y un celular de verdad.
- **Límites:** en iPhone los avisos solo llegan si el sitio está instalado en la pantalla de inicio (es una regla de Apple). Si la persona tiene la mesa abierta en una computadora y el celular suscripto, no le llega el aviso mientras la computadora la tenga a la vista: es lo buscado. De a cuatro todavía no hay avisos, porque los otros tres son bots.
- Capturas en `docs/capturas/`: `sin-conexion.png`, `sin-conexion-celular.png`, `mesa-ajustes-avisos-celular.png`, `sala-avisos.png` y `sala-avisos-celular.png`.

### Después de instalarlo en un celular de verdad

Román lo instaló en un Android desde Chrome y marcó tres cosas. Pidió recomendaciones y eligió.

- **La mesa había que deslizarla para ver los cantos.** La mesa mide justo el alto de la pantalla, y lo sacaba de la unidad `dvh`. Instalada, esa unidad contaba más alto del que se ve. En el navegador no pasa.
  - **Elegido:** la página mide ella misma el alto que se ve, antes de pintar y cada vez que cambia, y lo deja en una variable (`--alto`). De las medidas que da el navegador vale la más chica: si alguna cuenta de más, gana la otra.
  - **Primero se aplicó solo a la mesa, y no alcanzó.** Román mandó capturas del celular: la página medía unos 46 px más de lo que se veía, y en la portada el botón de jugar quedaba debajo de la barra de abajo. Ahora todo lo que se mide contra el alto de la ventana usa esa variable (la mesa, la portada, los modos, la repetición, las pantallas de cuenta), y un test impide que la unidad `dvh` vuelva a usarse suelta. En un navegador común las dos medidas coinciden y nada cambia de lugar: se midieron de nuevo los botones de la portada y de los modos, y dieron lo mismo que antes.
  - **Por qué va en el layout y no con el resto del JavaScript:** tiene que estar antes del primer dibujo, o la mesa aparecería grande y se achicaría.
  - **No se pudo reproducir en la computadora.** Se comprobó que, cuando esa medida es menor que `dvh`, la mesa entra entera en ella. Que la medida sea la correcta en ese celular lo confirma Román con el sitio publicado.
  - No se tocaron las cartas ni la disposición.
- **El botón de instalar estaba al fondo del pie y no se veía.**
  - **Elegido:** una franja propia en la portada, debajo de la primera pantalla: el ícono con su nombre debajo, como queda en el celular, una frase que dice para qué sirve y el botón lleno. Aparece solo cuando se puede instalar; si no, no ocupa lugar. En el pie queda un link chico.
  - **Se descartó** un cartel que aparezca solo desde abajo: es invasivo y nada en el sitio queda fijo sobre la página.
- **La pantalla que se ve al abrir la aplicación (el ícono y el nombre) estaba quieta.** Esa pantalla la dibuja el sistema antes de que cargue el sitio: no se puede animar.
  - **Elegido:** una apertura propia que la continúa. El mismo paño y los mismos cuatro fósforos en el centro; las cabezas se encienden de a una, el nombre acusa el golpe de la cuarta y todo se funde a la portada. Dura menos de un segundo.
  - **Es el segundo momento coreografiado del sitio** (el otro es el reparto). Román lo decidió sabiendo que cambiaba esa regla.
  - Se muestra solo en la aplicación instalada, una vez por apertura. Se corta tocando o con una tecla, y con movimiento reducido no aparece. Si el resto del JavaScript no cargara, se retira sola a los cuatro segundos.
  - El fondo es el paño de día también de noche, porque es el color que usa la pantalla del sistema.
  - **Se descartó** repartir la mano de la portada al abrir: no choca con ninguna regla, pero no continúa lo que se está viendo.
  - **Límite:** si el servidor está dormido, la pantalla quieta del sistema se queda hasta que despierta. Eso no lo tapa ninguna animación.
- Capturas en `docs/capturas/`: `portada-instalar.png`, `portada-instalar-celular.png` y `apertura-celular.png`.

## M17. Desafíos y escalera del club

Más de una forma de jugar solo: manos armadas con un objetivo, y un camino de ocho escalones para ir pasando. La pantalla de modos ya existía (se adelantó antes de M3); acá se hizo lo que va adentro.

Se salteó a propósito el torneo entre personas (M13b), que era el que seguía por orden: necesita cuatro u ocho personas conectadas a la vez para probarlo y para mostrarlo. Queda para el final, junto con el de a cuatro entre personas.

### Lo que decidió Román

- **La escalera tiene ocho escalones mezclados:** partidas a 15 contra un rival cada vez más difícil (de Fácil a Ultra difícil) y manos con objetivo.
- **Hay ocho desafíos sueltos, todos abiertos** desde el principio y en cualquier orden. Lo que va en orden es la escalera.
- **El progreso se guarda también sin cuenta,** y se conserva al registrarse. No hizo falta código: quien juega sin cuenta es una fila de jugador como cualquier otra.
- **Nada de esto cuenta para el ranking:** son manos armadas y partidas que se repiten hasta que salen.
- **La escalera se ve como naipes en escalera** (entre tres opciones): ocho naipes apoyados en el paño que suben en diagonal; los abiertos boca arriba y los que faltan boca abajo.

### Un desafío es una ficha de datos

- **Problema:** el plan pedía que agregar un desafío no llevara código nuevo.
- **Elegido:** cada desafío es una ficha en `app/Juego/fichas-de-desafios.php`: el tanteo, quién es mano, las cartas de los dos, lo que ya está jugado al arrancar (por ejemplo, "el bot cantó truco") y el objetivo.
- **El objetivo también es un dato:** una lista de condiciones que se cumplen todas. Hay siete: ganar la mano, ganar la partida, ganar el envido, sumar tantos puntos, que el bot no sume más de tantos, que el bot no quiera un canto y que lo quiera. Un test arma una mano que no está en el catálogo, con dos objetivos distintos, y comprueba que el resultado cambia.
- **Se descartó** una clase por desafío: doce clases casi iguales, y una más por cada desafío nuevo.

### El bot de un desafío contesta siempre lo mismo

- **Problema:** los bots Fácil, Difícil y Ultra difícil usan azar (para tirar cualquier cosa, o para mentir). Con azar, la misma jugada podía salir bien un intento y mal el siguiente: un desafío dependería de la suerte.
- **Elegido:** en un desafío el azar del bot sale de la clave del desafío y del número de la jugada. Las mismas jugadas reciben siempre las mismas respuestas, así que cada desafío tiene una solución que se puede buscar.
- **Lo que eso permite:** un test juega cada desafío de todas las maneras posibles y comprueba que al menos una cumple el objetivo y al menos una no. Los doce pasan. De la más fácil sale bien el 67 % de las maneras de jugarla; de las más difíciles, el 3 %.
- **Límite que hay que conocer:** como la clave decide cómo contesta el bot, cambiarle la clave a una ficha cambia el desafío. Ese test lo detecta.
- En una partida de la escalera (no una mano) el bot juega con azar de verdad, como en cualquier partida.

### Un desafío sobre el motor, sin tocar sus reglas

- **Una mano armada ya existía en el motor** desde M2. Se le sumó una sola cosa: poder fijar el tanteo de arranque antes de repartir (`conTanteo`), que era lo que la situación armada hacía por dentro. Tiene su test.
- **El tanteo de arranque queda guardado con el evento del reparto.** Así la partida se vuelve a contar desde sus eventos aunque después la ficha cambie o se borre.
- **Un desafío es una sola mano.** Cuando la mano se cierra, la mesa cierra la partida. "Quién la ganó" pasa a querer decir si se cumplió el objetivo: se puede cumplir perdiendo la mano (en "Irse a tiempo" el objetivo es que el bot sume un solo punto).
- **Si se cumplió lo decide el servidor,** volviendo a pasar los eventos guardados por el motor, igual que el historial y el ranking. La pantalla no manda ningún resultado; un test manda uno falso y comprueba que no cuenta.

### La escalera y su candado

- **Un escalón se juega solo si ya se pasaron los anteriores.** Lo decide el servidor con lo que tiene anotado: pedir a mano un escalón cerrado vuelve a la escalera con un aviso y no crea nada, ni siquiera el jugador invitado.
- **Las manos de la escalera son desafíos que no están en la lista suelta:** la única manera de llegar es subiendo. Pedirlas por la dirección de los desafíos contesta que no existen.
- **Los rivales de las partidas son gente del club:** jugadores de ejemplo del ranking, con su apodo y su carta, que sube por la jerarquía del truco (del cuatro de espada al ancho de espada). Es lo mismo que en el torneo contra bots.
- **Lo que hay en un escalón cerrado no se cuenta:** está boca abajo, también en el código de la página.
- **El progreso se anota en un solo lugar:** cuando la mesa cierra una partida de un desafío o de la escalera. Una que se abandona cuenta como un intento y nada más.

### Las pantallas

- **Desafíos:** cada uno se muestra con lo que lo distingue, que es la mano que te toca, con las cartas de verdad. Van agrupados por tema y con una lista, no con tarjetas iguales.
- **Escalera:** los ocho naipes llegan repartidos y los abiertos se dan vuelta, con el mismo reparto del resto del sitio. Tocar uno muestra su escalón al lado; el elegido se levanta, como la carta de un modo de juego.
- **En la mesa, el objetivo va en una ventanita** como la de los ajustes: se abre sola al llegar y se corre con la primera jugada. No le agrega una fila a la mesa, que sigue sin scroll.
- **Al cerrar la mano no se reparte:** el cierre dice "Desafío superado" o "No salió esta vez", y en la fila de los cantos van los botones de lo que sigue.
- **Modos:** con los cinco juegos en la mano ya no queda ninguno en el mazo, así que esa columna desaparece y la mano va en una sola fila.
- **Medido:** el botón de jugar de la escalera termina a 614 px en un celular de 360 x 740 y a 502 px en una ventana de 1355 x 638. El de los modos no se movió (588 px en esa ventana). La mesa de un desafío no hace scroll a 360 x 640, 360 x 740 ni 1355 x 638.

### La pantalla de modos en escritorio, rehecha

Con cinco juegos en la mano, en una pantalla ancha quedaban cinco cartas chicas a la izquierda, el detalle a la derecha y mucho paño vacío. Román pidió que se viera mejor y fue eligiendo entre opciones, mirándola en su ventana.

- **Dos zonas, como las pantallas de cuenta.** A la izquierda, sobre el paño, el título y la mano. A la derecha, un panel de paño hondo que va de arriba a abajo y llega al borde de la ventana, con el juego elegido: el nombre arriba y el rival, el nivel y el botón apoyados al pie. El botón queda a la misma altura en los cinco juegos.
- **La mano abierta.** Las cinco cartas van en abanico, más grandes y centradas en su zona. La elegida sube y casi se endereza, como la carta que se está por tirar. Ninguna cambia de lugar.
- **El tamaño de las cartas sale del ancho de su zona,** no del de la ventana: así la mano nunca llega al panel. Se había probado con el ancho de la ventana y en la de Román la última carta lo tocaba.
- **El medio del panel dice lo tuyo en ese juego,** dibujado con las piezas del sitio. Sin eso quedaba un hueco entre el nombre y las opciones. En mano a mano y de a cuatro, las partidas ganadas contadas con fósforos; en desafíos, la mano del próximo que falta; en la escalera, la escalera en chico con el escalón que toca levantado; en el torneo, el cruce que toca o cuántos se ganaron. Quien todavía no jugó lee "Todavía sin partidas" y no un "0 de 0".
- **Se descartó:** una línea de texto con lo que está empezado (era la primera idea y no llenaba nada) y achicar el panel para que no sobrara lugar.
- **El celular no cambió.** Todo esto corre desde `lg`; lo dibujado en el panel no se muestra más abajo.
- **Medido:** en 1355 x 638 el botón termina a 600 px en los cinco juegos y la mano termina 25 px antes del panel. También se miró en 1024 x 768, 1366 x 768 y 1920 x 1080. En un celular de 360 x 740 el botón sigue donde estaba.
- **Qué se probó:** los tests de las páginas y las pruebas de navegador que pasan por los modos. No se escribieron tests nuevos para lo dibujado en el panel: queda pendiente.
- Capturas: `modos-dos-zonas.png` y `modos-dos-zonas-pantalla-grande.png`.

### Lo que encontró la revisión

- **En Windows, dos archivos que solo se distinguen por una mayúscula son el mismo.** El archivo de las fichas se llamó primero igual que su clase, en minúscula, y la pisó. En Linux (donde corre el sitio) habrían sido dos archivos distintos y el error no se habría visto hasta publicar. Ahora las fichas tienen otro nombre.
- **Un test pedía que la mesa de un escalón no dijera "Objetivo"** y fallaba por el nombre de una variable de la página. Se cambió para que busque la ventanita y no la palabra.
- **El botón del final de un desafío con ícono no entraba a 360 px.** Los dos botones de esa fila van sin ícono.
- **Una prueba de navegador miraba el cartel del cierre un instante antes de que terminara de aparecer.** Acá pasaba siempre y en la integración continua, que es más lenta, fallaba: el cartel entra con un fundido y, mientras está transparente, el navegador no lo cuenta como visible. Ahora lo espera. Con la subida en rojo el sitio no se publica, así que M17 tardó tres subidas en salir.
- **La integración continua no decía qué había fallado** sin entrar al registro con la cuenta. Ahora cada test que falla queda escrito como un aviso de la corrida, que se lee desde afuera.
- **Un test viejo de la revancha de a cuatro dependía de la suerte:** la revancha iba a un punto contra bots de verdad, que a veces la terminaban antes de que el test la mirara. Falló por primera vez en una de estas subidas. Ahora los bots no juegan en ese test.
- **Otro test viejo de a cuatro pedía que en cada partida jugaran los cuatro asientos,** y una partida puede terminarse en la primera mano antes de que le toque al último. También falló por primera vez acá. Ahora lo mira entre las cuatro partidas que juega. Es la misma lección de M3: un test que juega partidas al azar no puede exigirle algo a una sola.
- **Un test de la escalera dependía de quién salía mano.** Para perder una partida a propósito, la persona se iba al mazo contra un bot que también se iba: ganaba el que fuera mano segundo. Pasó la corrida completa y falló la siguiente. Ahora el bot de prueba tira una carta cuando la persona tiene que perder, y el resultado no depende del sorteo.

### Qué se probó y qué no

- **Con tests (830 en total, 27 nuevos):** las fichas bien escritas; cada desafío con solución y sin salir solo; el mismo desafío arranca siempre igual y el bot contesta siempre igual; cada desafío ganado y perdido por la mesa de verdad, con el bot en la cola, da lo mismo que al buscarle la solución; el objetivo sale de los eventos; el candado de la escalera; nada va al ranking ni tiene revancha; el historial dice qué fue cada cosa.
- **En un navegador real:** una prueba automática supera un desafío de punta a punta y lo ve marcado en la lista. A mano: un desafío perdido, el final de una partida de la escalera, y las tres pantallas en celular y en escritorio.
- **No se jugó la escalera entera a mano,** escalón por escalón: las partidas a 15 se probaron contra un bot de prueba.
- **No se probó con lector de pantalla.** Queda para el pulido final.
- Capturas en `docs/capturas/`: `escalera.png`, `escalera-celular.png`, `desafios.png`, `desafios-celular.png`, `desafio-objetivo-celular.png`, `desafio-superado-celular.png`, `modos-cinco-juegos.png` y `modos-cinco-juegos-celular.png`.

## M18. Perfil y categorías

Que jugar mucho se note, sin dar ninguna ventaja en la mesa. El perfil pasó de cambiar solo el apodo a ser la pantalla de quién sos en el club: tu carta, tu categoría, el dorso de tus cartas y cuánto truco sabés.

### Lo que decidió Román

- **La categoría sube con partidas ganadas:** novato hasta 9, medio de 10 a 39, avanzado desde 40. Cuentan las mismas partidas que en el ranking (contra el bot desde Intermedio, y todas las de personas).
- **Quien declara que está aprendiendo** ve su tanto de envido ya sumado y entra contra el bot Fácil.
- **Las cartas de perfil se ganan con la categoría; los dorsos, con la escalera.** Así la escalera también tiene premio.
- **Las cartas se destraban una por una:** cada carta del camino pide una cantidad de partidas ganadas, y las categorías son tres tramos de ese mismo camino. Se descartó destrabarlas en tres tandas, porque entre tanda y tanda pasaban muchas partidas sin premio.
- **Tres dorsos nuevos**, además del de fábrica. Se descartaron cinco: en tintas planas cuesta que tantos se distingan en chico.
- **La pantalla: "tu carta y su camino"**, entre tres opciones. Se descartaron la mesa servida (el formulario quedaba apretado sobre el paño) y el abanico de catorce cartas (se parecía demasiado a la pantalla de modos).

### Nada se guarda que se pueda calcular

- **La categoría y lo destrabado no tienen columna.** Salen de los resultados que anota el ranking leyendo los eventos (`App\Juego\Premios`), y de los escalones pasados de la escalera. Si se rehace el ranking con `ranking:recalcular`, la categoría se rehace sola.
- **Lo que se guarda es lo que se eligió** (`jugadores.carta`, `jugadores.dorso`) y lo que se declaró (`jugadores.experiencia`). Al leer la carta o el dorso se vuelve a comprobar que estén ganados: una carta escrita a mano en la base no se muestra.
- **El candado está en el servidor.** Elegir una carta o un dorso es un pedido que se vuelve a validar contra lo ganado, venga de la pantalla o no.

### El camino

Catorce cartas en el orden del truco, una por cada escalón de la jerarquía: 4, 5, 6, 7 falso, 10, 11, 12, ancho falso, 2, 3, 7 de oro, 7 de espada, ancho de basto y ancho de espada. Piden 0, 2, 4, 6, 8, 10, 14, 18, 24, 31, 40, 55, 75 y 100 ganadas. Las de medio arrancan justo en 10 y las de avanzado en 40: cada categoría empieza con una carta nueva. Los saltos crecen de a poco, para que al principio haya una carta cerca y el ancho de espada cueste.

Los palos se eligieron para variar los colores en el renglón. Algunas cartas del camino son las mismas que tienen los jugadores de ejemplo: en un torneo, el bot que tiene tu carta no se inscribe, así cada carta de las llaves es de uno solo.

### Los dorsos

Fósforos (escalón 3), Tinta (escalón 6) y Ronda (escalón 8). Van en las tintas del mazo, planas, y se distinguen por la forma y no por el color: rayas de fósforos con la cabeza en Copa, un bloque de Tinta con el isotipo, y una ronda de anillos. Las combinaciones están en `Paleta::combinacionesDeLosDorsos()` con su test, y se miraron a 60 px: los cuatro se reconocen.

El dorso que elegiste es el que ve tu rival en la mesa entre personas. El mazo de la mesa sigue siendo el de fábrica.

### Lo que ve el rival

De tu perfil, la otra persona recibe tu apodo, tu categoría, tu carta y tu dorso. Nada más: ni tu cuenta, ni lo que declaraste. La categoría y la carta van junto a su apodo, en el lugar del ícono, así la fila del rival mide lo mismo. Contra el bot no viaja el perfil de nadie.

### La administración tiene todo abierto

Román quería ver la pantalla con todo destrabado en el sitio publicado. Se descartó darle partidas ganadas inventadas a una cuenta: habrían aparecido en el ranking público, que es una de las partes reales del proyecto. En cambio, la cuenta de administración tiene abiertas las catorce cartas y los cuatro dorsos para probarlos (`Premios::loTieneTodo()`). Su categoría no cambia: sale de sus partidas, como la de todos, y eso es también lo que ve un rival. El perfil lo dice en lugar de contar lo que falta.

### La ayuda del tanto

Es una cuenta hecha en el servidor con tus tres cartas y va solo en tu paso de la mesa (`ayudas.tanto`), así `mesa.js` sigue sin reglas. Se ve como una ficha de Oro sobre el borde de abajo de tus cartas mientras dura la primera baza: no le agrega una fila a la mesa. No cambia las jugadas permitidas ni lo que recibe el rival (test). Vale también en la mesa de a cuatro.

### La pantalla

- **Es una mesa.** Sobre el paño, tu carta grande; al tocarla se da vuelta y muestra tu dorso, que es lo que ve el rival. Al lado, un panel de paño hondo (como el tanteador y como el de los modos) con tu apodo, tu categoría, tus ganadas y cuánto te falta para la próxima carta, contado con fósforos. Debajo, sobre el mismo paño, el camino en sus tres tramos y los dorsos. Al final, en la superficie de lectura, cuánto truco sabés y tu cuenta.
- **El panel no es de naipe:** se probó y los fósforos (palito claro) no se leían sobre naipe.
- **Lo que falta ganar va boca abajo y a media tinta,** con tu dorso. La carta elegida sube y lleva la píldora "Tu carta". Las cartas y los dorsos que se eligen usan el mismo gesto que los escalones de la escalera (se asoman con el mouse, se hunden al apretar).
- **Al elegir un dorso** la página vuelve con la carta dada vuelta: es lo que cambió.
- **En el ranking** cada jugador va con su carta y su categoría. Los cuatro de arriba siguen con las cuatro cartas que mandan: esas son del puesto, no de quien lo ocupa.
- **Medido:** en 1355 x 638 la carta, su texto y su botón entran en la primera pantalla. La mesa entre personas sigue sin scroll a 360 x 740, 360 x 640 y 1355 x 638.

### Qué se probó y qué no

- **Con tests (16 nuevos):** la categoría sale de partidas jugadas y se rehace leyendo solo los eventos; se sube con las ganadas y las perdidas no cuentan; el camino va en el orden del truco; no se elige una carta ni un dorso sin haberlos ganado, tampoco mandando el pedido a mano; una carta guardada que no está ganada no se muestra; el rival recibe solo apodo, categoría, carta y dorso; la ayuda del tanto la ve solo quien la pidió y no cambia nada más; lo declarado cambia el bot de entrada y no pesa si se elige un nivel; en el torneo la persona juega con su carta y ningún bot la repite; el ranking dice categoría y carta.
- **En un navegador real:** el perfil en escritorio, celular y de noche; la mesa entre dos cuentas a 360 px, de los dos lados. Las siete pruebas automáticas de navegador pasan.
- **No se probó con lector de pantalla.** Queda para el pulido final.
- **Sin `code-review` con la skill:** fue revisión propia sobre lo cambiado.
- Capturas en `docs/capturas/`: `perfil.png`, `perfil-celular.png`, `perfil-dorso.png`, `dorsos.png`, `ranking-con-categorias.png`, `mesa-rival-con-dorso-celular.png` y `mesa-ayuda-del-tanto-celular.png`.

## "La máquina" en vez de "bot"

Román pidió que la palabra "bot" no se viera: es técnica, en inglés, y desentonaba con un sitio que habla como en un club. La regla del proyecto pide decir abiertamente qué es simulado, así que la palabra nueva no podía esconder que no es una persona: se descartaron "el club" o "la casa". Quedó **"la máquina"**, que es como se dice acá ("jugar contra la máquina").

- Cambian solo los textos que se ven: botones ("Jugar contra la máquina"), niveles ("Máquina intermedia"), la mesa ("Ganó la máquina", "Juega la máquina"), la marca de los jugadores de ejemplo en el ranking, el historial, el torneo, los desafíos, los modos y el panel.
- El código sigue diciendo `Bot` (`BotDificil`, `nivel_bot`): no lo ve nadie y cambiarlo no aporta nada.
- "Máquina" es femenino y los niveles se dicen en masculino ("nivel Intermedio"), así que se sumó `Nivel::deLaMaquina()` para concordar: "Máquina intermedia", "contra la máquina difícil".
- La palabra es más larga: en el celular, la fila "Contra la máquina / Con otra persona" de los modos ya no entraba en un renglón y empujaba el botón de jugar debajo de la barra de abajo. Va un punto más chica en el celular; el botón quedó 4 px más arriba que antes.
- Probado: la suite entera, las siete pruebas de navegador, y el inicio, los modos y la mesa medidos a 360 x 740, 360 x 640 y 1355 x 638, sin scroll de costado.

## M22. Unificación visual

Román pidió una opinión honesta de la identidad del sitio. Lo bueno era la mesa, el tanteador, el mazo y la disciplina de color y movimiento; lo flojo, que cada pantalla se había armado en un momento distinto y se notaba, que sobraba texto explicativo, que la mesa de escritorio era una columna angosta en un campo enorme y que había mucho verde plano. M22 junta todo eso en una pasada, antes de los niveles y los premios, así lo nuevo se construye sobre el sistema unificado.

### La mesa ancha

- **Problema:** en una pantalla de escritorio el juego ocupaba una columna de unos 450 px en un paño de más de 1.300.
- **Elegido (entre tres disposiciones con capturas):** la mesa del centro queda igual y gana dos costados que sirven para jugar, no adornos. Solo desde `lg`; el celular no cambia.
- **A la izquierda, la planilla de lo cantado:** una hoja de anotador (papel naipe apenas torcido, con la sombra corta de las cartas, porque está apoyada en la mesa). Se descartaron el texto suelto sobre el paño y un panel de paño hondo: el papel es lo único que se lee de un vistazo junto a las cartas.
- **En dos columnas, como la planilla de cantos del bridge:** "Vos" y el rival con la raya al medio, cada renglón una vuelta. Se eligió así porque la queja más repetida de los historiales de juegos de cartas es que no se entiende quién hizo cada cosa. Truco y "No quiero" van en Copa y "Quiero" en Basto, los colores de sus botones (medidos sobre naipe). Con una mano muy cantada la letra baja un paso; si igual no entra, lo más viejo queda tapado arriba y lo último siempre se ve.
- **Para saber quién dijo qué, el motor entrega `cantos` en la vista:** cada canto, cada respuesta y quién ganó los tantos, en orden y con su asiento. Es lo que oyeron los dos en la mesa, así que va igual a cualquier asiento (test). Se descartó juntarlo en el navegador a medida que llega: al recargar la página se perdía.
- **A la derecha, el mazo delante de quien repartió:** arriba si repartió el rival, abajo si repartiste vos, y dice quién. Reparte el que no es mano, así que en cada mano nueva el mazo se desliza al otro lado antes de repartir (260 ms) y las cartas salen de su lugar nuevo. Es una pila de seis dorsos corridos para que se vea el canto. La primera versión decía "Sos mano" ahí, y repetía la marca que ya está junto a tu mano.
- **Medido:** sin scroll en 1024 x 768, 1355 x 638 y 1920 x 1080, también con doce cantos en la planilla. La mesa de a cuatro no lleva costados: la cruz ya usa ese lugar.

### Arrastrar la carta a la mesa

- Una forma más de jugar, que le recomendaron a Román. Tocar la carta sigue igual (y el teclado y el lector de pantalla).
- `resources/js/arrastre.js`, sin librerías: eventos de puntero, el puntero queda capturado por la carta y un segundo dedo se ignora. Solo se mueve con `transform`.
- **En el truco no hay deshacer:** hasta los 10 px es un toque; juega si se suelta sobre la mesa o con un tirón rápido hacia arriba (0,6 px por milisegundo y al menos 30 px); hacia abajo la carta se resiste. Si no juega, vuelve a la mano en 200 ms. Mientras está sobre la mesa se marca el lugar de la baza.

### Las piezas compartidas

- **Un mismo molde** (`.pagina`): el mismo ancho y los mismos márgenes en todas las páginas. Antes había dos anchos y cada título tenía su tamaño.
- **El encabezado** (`<x-pagina.encabezado>`): título, bajada de un renglón, lugar a la derecha para lo que la página cuenta de un vistazo, y el cierre con el **filete doble** de los impresos (una raya gruesa y una fina). Se buscaron referencias de impresos de club argentinos (carteleras, programas del hipódromo) y no apareció material útil; de lo que sí apareció se tomaron las tablas con filetes de la imprenta de plomo y la letra pesada que se lee de lejos.
- **Las tablas son planillas:** la cabeza cierra con el mismo filete doble y las columnas van separadas por una raya, como la planilla de la mesa. Vale para el ranking, cómo se juega e identidad.
- **El desplegable** (`<x-pagina.desplegable>`) para las explicaciones que no todos necesitan: es un `<details>` (se abre con teclado y el lector dice si está abierto), con una cruz que gira y queda en equis y el texto que entra con un fundido de 180 ms.
- **Dónde va cada superficie** (decidido por Román): donde se elige qué jugar va el paño (modos, desafíos, escalera, torneo); donde se lee, el papel (ranking, historial, cómo se juega, identidad). Las páginas de elegir juego que entran en una pantalla (modos, escalera, torneo, sala) usan el mismo título y bajada pero no el filete, que las haría más altas.
- **Los links dentro de un texto sobre el paño** van en el color del texto, subrayados: Espada no se lee sobre el paño. Los del pie pasaron al subrayado de fósforo de toda la navegación.

### Menos texto

- Cada bajada entra en un renglón en escritorio. Lo largo pasó a desplegables: "Cómo se cuenta" (ranking), "Cómo funcionan" (desafíos), "De dónde sale" (identidad).
- **Lo simulado se dice en el pie de todas las páginas, una sola vez** (decidido por Román): una línea y un "Más" con el detalle. El inicio tenía además una sección propia que lo repetía justo encima del pie.

### El inicio

La primera pantalla no se tocó: funciona y está medida. Debajo:

- **Formas de jugar:** las cartas de modo de los juegos que ya se juegan, con una línea cada una y el link directo (a los modos con ese juego elegido, o a desafíos y escalera). En el celular, un renglón por juego; en escritorio, en fila y apenas giradas, como una mano tirada en la mesa. Al pasar el mouse la carta se levanta y se endereza, el mismo gesto que los botones.
- **Instalar, después de los juegos:** tres ventajas en renglones de planilla y el botón; en el iPhone, los dos pasos con el dibujo de sus botones (íconos nuevos `compartir` y `agregar`, con el trazo de los demás).
- **El tanteador y las cuatro que mandan,** con una línea cada uno.

### Qué se probó y qué no

- **Con tests:** la vista lleva lo cantado en orden y con quién lo dijo, igual para los dos asientos, y se vacía al repartir. La suite entera y `MovimientoTest` (todo lo nuevo se mueve solo con `transform` y `opacity` y tiene su versión de movimiento reducido).
- **En un navegador real:** la mesa ancha en tres tamaños durante varias manos, el pase del mazo, la planilla con una mano de doce cantos, el arrastre (corto vuelve, hasta la mesa juega, tirón juega, tocar juega) en escritorio y celular; todas las páginas a 1355 x 638 y 360 x 740, antes y después.
- **Un test que falló una vez y no se pudo repetir:** "recargar la mesa vuelve a la misma partida", corrido junto con otros. Pasó 15 veces solo y 3 en grupo.
- **No se probó con lector de pantalla.** Queda para el pulido final.

## Tanda 1: niveles, el camino de premios y cada uno con su dorso

Primera tanda de la segunda vuelta de M18 y M20. Román eligió antes de escribir código, con dos láminas: el dorso de fábrica nuevo (entre tres variantes) y el camino entero de cuarenta niveles.

### Los niveles

- **Diez fósforos por nivel, siempre** (decidido por Román): la tira se lee como el tanteador, dos grupos de cinco. Ganar anota tres, perder uno y abandonar ninguno. Un desafío anota tres la primera vez que sale y repetirlo no anota.
- **Cuentan todas las partidas terminadas:** contra la máquina de cualquier nivel (también la fácil, que no cuenta para el ranking), entre personas, de a cuatro (a la persona) y de torneo. Las de máquina contra máquina de un torneo no son partidas. Las de los jugadores de ejemplo también: así tienen nivel.
- **Ganar porque el otro se fue** anota solo si ya ibas en las buenas, la misma regla que el ranking: así no sirve abrir una sala desde otra ventana y abandonar para regalarse fósforos.
- **Se guarda el total en el jugador** (`jugadores.fosforos`) y se suma al cerrar cada partida (`Camino::anotar`, dentro del cierre, como el ranking). El nivel sale de ese número con una división: abrir una página no cuenta nada. **Se descartó** guardar los fósforos de cada partida en una tabla: no hace falta para nada que se vea, y lo de cada partida se puede volver a calcular.
- **Se rehace desde las partidas** con `php artisan fosforos:recalcular`. El contenedor lo corre en cada arranque, después de sembrar los jugadores de ejemplo: así las partidas jugadas antes de que hubiera niveles también cuentan.
- **Las categorías pasan a ser tramos de niveles:** novato del 1 al 9, medio del 10 al 24 y avanzado desde el 25. Antes salían de las partidas ganadas.
- **Cuánto se tarda:** al nivel 40 se llega con 390 fósforos, unas 195 partidas ganando la mitad o 156 ganando tres de cada cuatro. Se le había dicho a Román "130 a 150" y era una cuenta mal hecha: se le corrigió antes de elegir, y eligió quedarse con los cuarenta niveles.

### El camino

- **Es un archivo de datos** (`app/Juego/fichas-de-premios.php`), como los desafíos: cada premio con su tipo, su nivel (o su escalón de la escalera) y si ya está dibujado. Lo que no está dibujado figura en el camino como "próximamente"; cuando llegue, queda abierto para quien ya pasó ese nivel, porque lo destrabado se calcula, no se guarda.
- **El orden lo eligió Román sobre una lámina:** las catorce cartas cada tres niveles en el orden del truco (del 4 de copa de entrada al ancho de espada en el 40) y lo más fino arriba (grafito y latón en el 39, el fileteado en el 40). Cada nivel del 2 al 40 tiene uno o dos premios: 61 en total. Los nombres de los mazos se eligen en la tanda 5.
- Un test comprueba que cada nivel destraba algo y que las cartas y los dorsos del camino son los mismos que conoce `Premios`.

### El final de la partida

- Después de contarse los tanteadores, en el mismo paño, aparece el nivel con su tira y los fósforos ganados caen de a uno (220 ms entre uno y otro, con el sonido del punto si está prendido). Si la tira se llena hay un respiro, el nivel sube, la tira arranca de nuevo, y abajo se dice "Subiste al nivel N:" con lo que destrabó. Con movimiento reducido la tira aparece ya contada.
- Va en un renglón para no empujar los botones: en una ventana de 638 px de alto, "Jugar la revancha" sigue a la vista. Lo cuenta también el texto para el lector de pantalla.
- El paso que termina la partida trae el resumen (`experiencia`: cuántos anotó, desde dónde, hasta dónde y qué destrabó). Vale justo al cerrar: es la única vez que la mesa lo muestra.

### Cada uno ve lo suyo

- **Decidido por Román el 14 de octubre de 2026:** en tu pantalla todas las cartas boca abajo salen con tu dorso: las tuyas, el mazo y las del rival. El rival ya no recibe tu dorso; de vos le llegan el apodo, la categoría y tu carta. Cambia lo que decía M18 ("el dorso es lo que ve el rival").
- `<x-dorso>` sin decir cuál dibuja el de quien mira, averiguado una vez por pedido. La mesa trae una sola plantilla de dorso, la propia: es también menos peso.
- **El dorso de fábrica nuevo es "Isotipos"** (variante B de la lámina, elegida por Román): una trama de isotipos chicos con el marco doble de las caras y el logo grande en un medallón, palito en Tinta y cabeza en Copa. Se descartaron la trama sola (en chico se lee como un textil) y los isotipos girados (en chico se confundía con los rombos). El dorso de fábrica de antes pasó a ser el premio "Rombos", en el nivel 3.

### Las marcas del menú

- **Elegidas por Román el 14 de octubre de 2026,** sin tocar cómo está armada la navegación: el puntito que ya tiene el subrayado de los links queda encendido, sin el subrayado.
- **En "Jugar"**, también en la barra del celular, cuando hay una partida sin terminar o una sala esperando: "Jugar" lleva directo a la mesa.
- **En el apodo**, cuando subió de nivel y en el medio se destrabó algo ya dibujado. Se apaga al mirar el perfil (`jugadores.nivel_visto`).
- Las dos se averiguan una vez por pedido (`App\View\Marcas`), aunque las pregunten el menú de arriba y la barra de abajo. Las dicen también para el lector de pantalla.

### El perfil

No se rehizo (es la tanda 2): pasó a decir el nivel con su tira ("4 de 10 para el nivel 18"), en qué nivel llega la próxima carta y desde qué nivel se pasa de categoría. Debajo de cada carta del camino va solo el número del nivel: Román marcó que "Nivel 25" no entraba y se pisaba con el de al lado.

### Qué se probó y qué no

- **Con tests:** cuánto anota ganar, perder, abandonar y un desafío (la primera vez y repetido); que se suma al cerrar y que recalcular da lo mismo; el resumen del paso final; la categoría por tramos; las cartas y los dorsos por nivel, también mandando el pedido a mano; que el rival no recibe el dorso y que cada uno ve el suyo; las dos marcas del menú; la administración con todo abierto.
- **En un navegador real:** una partida a un punto contra la máquina, a un fósforo de subir, en 1355 x 638 y 360 x 740 (el final con la tira, el nivel nuevo y la carta destrabada); el menú con y sin la marca; el perfil.

## Tanda 2: el perfil rehecho

Román dijo del perfil anterior que "estaba muy desordenado, son cosas metidas ahí porque sí", y aprobó el 14 de octubre de 2026 cómo tenía que ser. Se buscaron referencias: chess.com separa los números en su propia sección, debajo del encabezado del perfil, y en los "lockers" de los juegos lo que funciona es ver cada pieza puesta sobre el objeto del juego, no como una miniatura suelta. De ahí salen la planilla y el probador.

### Cómo quedó

- **Arriba, "tu mesa", sobre el paño:** el mazo apilado (con el dorso de quien mira, como todas las cartas boca abajo) y tu carta grande, que se reparte desde el mazo al entrar. Al lado: el apodo, la categoría, el nivel con su tira, lo que trae el nivel que sigue (también lo que todavía no está dibujado) y "Jugar una partida". En 1355 x 638 entra entero en la primera pantalla.
- **Un índice quieto** (nada acompaña el scroll): Resumen, Colección, Camino, Partidas, Cuenta. En el celular se desliza de costado si no entra.
- **Resumen como la planilla del club:** números grandes con su nombre abajo y las rayas del anotador, sin tarjetas. Partidas, ganadas (con el porcentaje), racha (con la mejor), envidos ganados, torneos ganados, escalones y desafíos. Partidas y ganadas salen de lo mismo que el ranking, así un número del perfil nunca contradice a la tabla (`App\Juego\Numeros`).
- **Colección sobre el paño** (es lo que se usa en la mesa): pestañas de cartas, dorsos, mesas y mazos, y un probador. Tocar una pieza la muestra armada en una mesa chica: una carta de frente, un dorso con la carta dada vuelta. "Usar esta carta" o "Usar este dorso" la elige; lo que falta dice en qué nivel llega o en qué escalón se gana. Lo que todavía no está dibujado va punteado, con su nombre y su nivel. Solo se dibuja la pestaña abierta. En el celular el probador va arriba de las piezas, con la mesa y el texto lado a lado: al tocar se ve qué cambió sin tener que bajar.
- **El camino:** ocho niveles alrededor del tuyo (los tuyos dicen "Tuyo", el que sigue va sobre paño hondo) y "Ver el camino entero" en un desplegable, con los cuarenta como planilla.
- **Últimas partidas:** las cinco últimas, con "Ver de nuevo" y "Ver el historial".
- **Tu cuenta al final:** cuánto truco sabés, el apodo, el email y cerrar sesión.
- **Las secciones siguen el armado de M22:** el título con el filete doble, las planillas con sus rayas, el paño para lo que se usa en la mesa y el papel para lo que se lee.

### El perfil de otro

- Se abre tocando un apodo en el ranking (`/jugadores/{id}`). Muestra su mesa, su carta, su nivel, sus números y sus últimas partidas, sin links a la repetición (las partidas no son tuyas). Nada de su cuenta, ni su dorso: cada uno ve las cartas boca abajo con el suyo.
- Solo de quien tiene cuenta y de los jugadores de ejemplo, que llevan la marca "máquina". Quien juega sin cuenta y la cuenta de administración no tienen perfil público (404). Abrir el propio lleva al perfil.

### El movimiento

Con el criterio de Emil Kowalski (animar solo lo que tiene un motivo, nunca lo que se toca muchas veces):

- **Al entrar, tu carta se reparte desde el mazo** (la misma animación del reparto de la mesa, 280 ms): una vez por visita, dice de dónde sale tu carta.
- **Los fósforos ganados desde la última visita caen en la tira**, de a uno: lo visto se recuerda en el navegador. Si no ganaste nada desde entonces, la tira está quieta.
- **En el probador la carta se da vuelta** para mostrar un dorso: es una transición, así que si se toca otra pieza a mitad de camino vuelve desde donde está.
- **Las pestañas cambian sin animación:** se tocan muchas veces. Se levantan apenas al pasar el mouse, como los botones.
- Con movimiento reducido no hay reparto ni caída de fósforos, y la carta del probador cambia sin girar.

### Lo que cambió después de que Román lo usó

- **El aviso de confirmación es un cartelito abajo** ("Dorso elegido."). Antes era una franja arriba de la página que la empujaba hacia abajo y la volvía a subir al irse. Ahora sube desde abajo, queda unos segundos y baja por donde vino (240 ms al entrar, 160 al salir); con el mouse encima espera y se cierra tocándolo. En el celular va arriba de la barra de abajo. Sirve para todo el sitio: lo trae el servidor después de un formulario o lo pide la página con un evento (`resources/js/aviso-flotante.js`). Es lo segundo que queda quieto en la pantalla, después de la barra de abajo, y lo pidió él.
- **Elegir no recarga la página ni la lleva arriba.** "Usar esta carta" y "Usar este dorso" guardan con un pedido de fondo: tu carta de arriba o el dorso de tu mazo cambian en el lugar, "En uso" pasa a la pieza nueva y el aviso lo confirma. Si el servidor lo rechaza (una pieza que no ganaste, mandada a mano), el aviso lo dice en Copa. Sin JavaScript el formulario común sigue andando: el servidor contesta JSON solo cuando se lo piden.
- **Los links del índice bajan con un deslizamiento suave** (`scroll-behavior: smooth`), salvo con movimiento reducido.
- **El dorso del probador quedaba en blanco al cambiar de dorso:** el mazo de arriba usaba una variable con el mismo nombre que el dorso elegido y la pisaba. Corregido.

### Qué se probó y qué no

- **Con tests:** elegir desde el probador contesta sin redirigir y el candado rechaza igual lo que no se ganó; los números (partidas, ganadas, porcentaje, rachas, envidos) contra resultados conocidos; el perfil de otro muestra lo suyo y nada de la cuenta; quien juega sin cuenta y la administración no tienen perfil público; el propio lleva al perfil; el ranking lleva a cada perfil; elegir carta y dorso vuelve a la Colección.
- **En un navegador real:** el perfil propio y el de otro en 1355 x 638 y 360 x 740; el probador con un dorso; elegir una carta y un dorso sin recargar (cambian la carta y el mazo de arriba, y el aviso sube abajo); sin scroll de costado.
- **No se probó con lector de pantalla.** Queda para el pulido final.

## Tanda 3: los primeros premios dibujados

Román aprobó el plan el 15 de octubre de 2026 y pidió buscar referencias y usar las skills de diseño ("no tiene que quedar feo"). Todo se eligió primero en una lámina con las piezas juntas; él aprobó dorsos, formas de anotar, distintivos y títulos de entrada, y pidió más detalle en tres mesas.

### Lo que trae

- **Cuatro mesas:** Paño petróleo (nivel 5), Mármol de café (10), Pizarrón (14) y Madera del club (17). Se ven solo en la mesa de juego de quien la eligió (mano a mano y de a cuatro) y en su perfil. Cada una pisa los dos colores del paño, como la mesa del Ultra difícil, y dibuja su adorno detrás de las cartas, en el borde del campo. Con una mesa elegida, la del Ultra no oscurece el paño.
- **Tres dorsos:** Damero (8), Lunares (12) y Zigzag (16), en la tradición de los dorsos del siglo XIX: una trama chica y pareja que no distrae. Los tres son iguales dados vuelta (las grillas están centradas en la carta; en el zigzag, cada renglón de abajo es el de arriba girado).
- **Dos formas de anotar:** Palotes con lápiz (7) y Tiza (13). Son el mismo cuadrado con su diagonal del anotador de truco, a mano: cada trazo se corre y se curva apenas, siempre igual para el mismo lugar. Cada forma trae su superficie (un papelito, una pizarrita con marco) detrás de los grupos, sin cambiar el alto del tanteador, así la cuenta de la mesa no se mueve. No caen como un fósforo: aparecen en su lugar, creciendo apenas.
- **Cinco distintivos:** Un fósforo (2), El cuadrado (6), Grupo de cinco (11), El 15 (15) y Dos cruzados (18), con el trazo de los íconos propios (rectas de 2 px con su cabeza, en una sola tinta). Dos cruzados va más parado que el ícono de "No quiero" y uno pasa por debajo del otro, para que no se confundan. Se puede no llevar ninguno.
- **Cinco títulos:** Pichón (de entrada), Cantor (25 envidos cantados), Tantero (25 envidos ganados), Truquero (25 trucos queridos ganados) y Florido (10 flores cantadas). Cada uno elige cómo se lee el suyo (Cantor o Cantora); nunca se adivina por el apodo. Los jugadores de ejemplo muestran el mejor que ganaron en sus partidas.
- **Todo se elige en la Colección** (pedido de Román): siete pestañas (cartas, dorsos, mesas, mazos, anotar, títulos y distintivos), cada una con su probador y "Usar" sin recargar. Arriba del perfil, "tu mesa" toma la mesa elegida, y el apodo lleva su distintivo y su título.

### Las tres mesas con detalle

La primera versión del mármol, el pizarrón y la madera le pareció plana a Román. Se buscaron referencias y eligió, entre opciones, las tres recomendadas:

- **Pizarrón, "pizarra usada":** pasadas de borrador tenues que se pisan, y en dos esquinas lo que quedó de otras partidas a medio borrar (unos palotes y un 30). El marco de madera tiene su veta y las esquinas en diagonal. Marcó que los palotes viejos se pisaban con la raya de tiza: se corrieron hacia adentro.
- **Mármol, "Carrara de café":** vetas finas y quebradas que corren en diagonal desde dos esquinas, con ramitas que se abren en ángulo chico, nubes grises apenas marcadas y el filete doble de latón. Es la única clara: sobre ella el texto pasa a tinta, el envido a un ocre del oro y el palito de los fósforos se oscurece; las cartas, el papel de la planilla y los botones vuelven a sus colores.
- **Madera, "mesa de juego antigua":** la veta del nogal con un nudo cerca del borde, una banda clara con su filete oscuro y un filete de boj adentro. Los abanicos de marquetería de las esquinas se probaron y se sacaron (Román: "están de más"), y el marco hecho como imagen en nueve partes se rehízo con líneas, porque a ciertos tamaños sus esquinas no encajaban.

Se descartó hacerlas con texturas generadas (ruido que imita la piedra o la veta): todo está dibujado trazo por trazo, que es lo que permite la regla de "nada de texturas" desde que las mesas pueden tener diseño.

### Cómo está hecho

- **Los dibujos de las mesas** están en `resources/mesas/` (SVG). Lo que tiene forma (vetas, el nudo, lo que quedó a medio borrar) va en las esquinas a tamaño fijo, para que no se deforme entre el celular y la computadora; solo lo informe (vetas largas, borrones) se estira. Vite los compila como archivos aparte, y el navegador los pide solo si hay una mesa de esas en la página.
- **Qué tiene cada uno:** `Premios::POR_NIVEL` (mesas, formas de anotar y distintivos con su nivel), `piezas()`, `tiene()`, `elegidaDe()`, `propia()` (la de quien mira, una vez por pedido), `titulos()`, `tieneTitulo()`, `tituloDe()`. Lo elegido se guarda en `jugadores.mesa`, `anotar`, `distintivo`, `titulo` y `titulo_femenino`, y al leerlo se vuelve a comprobar que lo tenga. La administración tiene todo abierto.
- **Los títulos** salen de `App\Juego\Titulos`: al cerrar cada partida se suman, desde los hechos del motor, los envidos cantados, los envidos ganados, los trucos queridos ganados (la mano se cerró con un truco que valía 2 o más a favor de su equipo) y las flores cantadas, y se guardan en el jugador. `fosforos:recalcular` también los rehace. Los desafíos no cuentan: reintentar la misma mano no puede ser la forma de ganar un título.
- **Lo que recibe el rival** pasa a ser apodo, categoría, carta, título y distintivo (`Premios::aLaVista()`). Su mesa, su dorso y su forma de anotar no salen de su pantalla.
- **La paleta:** `Paleta::MESAS` y `ANOTADORES` con sus combinaciones (`combinacionesDeLosPremios()`, contraste medido) y `esDeLaFamilia()`: un color de premio no pasa de un tope de intensidad, no cae en la franja de rosas y violetas y no es un celeste claro de bebé. Los colores de la paleta fija (el oro, el fósforo) no pasan por esa regla.

### Qué se probó y qué no

- **Con tests:** las cuentas de los títulos coinciden con las jugadas guardadas y con el ranking, y se rehacen igual; un título se gana al llegar a lo que pide y se lee como se eligió; el candado de mesa, forma de anotar, distintivo y título, también mandando el pedido a mano; la administración tiene todo; lo que recibe el rival; la mesa y la forma de anotar se ven en la mesa de juego; los distintivos son rectas con su cabeza y no repiten un ícono; lo dibujado coincide con el camino; el contraste y la familia de color de las mesas y las formas de anotar.
- **En un navegador real:** las cuatro mesas en la mesa de juego en 1355 x 638 y 360 x 740; el perfil con cada pestaña nueva, probando y usando una pieza de cada una, en los dos tamaños.
- **No se probó:** la mesa de a cuatro con cada mesa nueva (usa las mismas reglas); lector de pantalla (pulido final). Un test de la mesa ("recargar la mesa vuelve a la misma partida") volvió a fallar una vez en la corrida completa y pasa solo, como en M22.

## Las mesas de noche y las cartas al estilo Balatro

Román marcó el 15 de octubre de 2026 que las mesas que se ganan no cambiaban con el modo de día y de noche, y pidió que las cartas se movieran como en Balatro. Se buscaron referencias y eligió las recomendadas.

### Las mesas de noche

- **Cada mesa baja la luz, como el paño de fábrica.** Sus colores se multiplican por un mismo factor (0,7; el mármol, 0,78) y sus dibujos se oscurecen en la misma proporción (`brightness` sobre el adorno del campo), así las vetas, los borrones y el marco siguen en su lugar respecto del fondo. Las muestras del perfil llevan encima una capa pareja del mismo oscuro.
- **El mármol de noche es el mismo mármol con la luz baja** (lo eligió Román entre ese, un mármol negro y dejarlo claro). Su ocre del envido y el palito de los fósforos bajan un poco más para seguir leyéndose (medido: 4,8 y 3,1).
- **Un test exige que toda mesa que se gana tenga su noche**, en `Paleta::MESAS` y en los estilos, con el contraste medido.
- **Regla para las mesas que ya son oscuras** (Román, mismo día, pensando en el mármol negro que viene): no se oscurecen de noche, porque no se notaría y se perderían contra la página; baja la luz solo de lo claro (marco, vetas, tiza) y las barras pasan a un paso más oscuro que la mesa.

### Las cartas, "Balatro medido"

Balatro no publica su código; lo que se tomó es lo que se observa: las cartas se inclinan hacia donde van, se apoyan con un rebote chico y cada una queda con un pequeño giro. Lo que se descartó: que floten solas todo el tiempo (la regla de "nada en loop" sigue).

- **Al arrastrar,** la carta se inclina según la velocidad del dedo (hasta 14°) y se endereza cuando el dedo se frena; la inclinación se calcula cuadro a cuadro solo mientras dura el arrastre. Si se suelta antes, vuelve con un resorte.
- **Al jugarla,** cae en la baza con un resorte de poco rebote y queda con su giro de entre −3° y 3°, que sale de la carta misma: al recargar la página la baza se ve igual.
- **Con un mouse,** la carta bajo el cursor se inclina apenas hacia él, en 3D (hasta 7°). En el celular no, porque no hay cursor.
- **La mano se junta** con un resorte cuando sale una carta; el lugar vacío se esconde mientras quede alguna y vuelve con la mano vacía, así la fila mide lo mismo.
- **Los resortes, sin librerías:** `resorte(duracion, rebote)` en `cartas.js` arma una curva `linear()` con la física de un resorte (como los de Apple: una duración y un rebote) y se usa en la Web Animations API. Si el navegador no entiende `linear()`, usa la curva de llegada del sitio. Se le avisó a Román que un resorte con rebote tarda un poco más que el tope de 300 ms en quedar quieto (la caída dura 400 ms, aunque a los 250 ya está casi apoyada).
- **Con movimiento reducido** no hay inclinación ni resorte: la carta aparece con un fundido, como antes.

### Qué se probó

- **Con tests:** el contraste de noche de cada mesa y que ninguna se quede sin su noche; el test de movimiento (solo se escribe `transform` desde JavaScript, nada en loop, versión reducida).
- **En un navegador real:** las cuatro mesas de noche en la mesa y en el perfil; el arrastre de costado (se inclinó unos 6° yendo rápido y se enderezó al frenar), la inclinación hacia el cursor, la caída con su giro y la mano que se junta. **No se probó con el dedo en un celular de verdad:** cómo se siente el arrastre lo juzga Román.



## Tanda 4: premios II

Pedida por Román el 15 de octubre de 2026 ("seguí con lo que sigue"). Se armó una lámina con todo junto y él la aprobó, con tres pedidos en el camino: que la noche se note en las mesas oscuras, un dorso clásico de cuadros (y otros dos de los que mandó de ejemplo) y dos dorsos más para completar la fila de la Colección, "más creativos, de recompensa para los últimos niveles".

### Lo que trae

- **Seis mesas:** Baldosa calcárea (20), Mantel de bodegón (23), Milonga (25), Mármol negro (26), Diario de café (28) y Pana del club inglés (30). Siguen el patrón de la tanda 3: el detalle dibujado en SVG en los bordes, el medio limpio para que las cartas y el texto se lean.
- **Doce dorsos:** Esterilla (11), Aros (14), Guarda griega (18), Ramitas (20), Cuadros (21), Abanicos (23), Ondas (24), Tanteador (27), Estrella (30), Guarda pampa (32), Grabado (39) y Las que mandan (40). Con los de antes son veinte; con el Monograma, que falta dibujar, la Colección queda en tres filas de siete.
- **Anotar con porotos y garbanzos (27):** cada punto es un poroto acostado sobre su lado del cuadrado y el quinto es un garbanzo en el medio, como cuando se cambian cinco porotos por un garbanzo. Van sobre la tapa clara de la caja: sobre el paño un poroto bordó casi no se ve.
- **Distintivos:** el 7 (21), el 4 (24), el 30 (29), el 33 (33) y los cuatro palos (38). Los cuatro palos son un solo premio del camino: al ganarlo se abren los cuatro y cada uno elige cuál lleva (`ficha` en `Premios::POR_NIVEL`).
- **Ocho títulos:** Retruquero (10 retrucos queridos ganados), Chamuyero (10 trucos ganados sin que te quieran y sin cartas bravas), Pardero (10 manos ganadas después de una primera parda), Falta envido y Vale cuatro (ganar uno querido), Campeón (un torneo), Del club (la escalera entera) y Desafiante (los ocho desafíos).

### Los dorsos de los últimos niveles

Román pidió referencias. Las que sirvieron: los 173 dorsos que Owen Jones dibujó para De La Rue entre 1844 y 1863, armados sobre grillas geométricas con arabescos, y la idea de un dorso que no sea una trama que se repite sino una pieza. De ahí salieron dos:

- **Grabado (39):** un rosetón de líneas finas que se cruzan, como la trama de seguridad de un billete viejo, con una guarda de ondas y el isotipo en el medio. Es trabajo de imprenta: va con la identidad de tintas planas.
- **Las que mandan (40):** un medallón de dos cabezas, como las figuras, con el ancho de espada y el de basto cruzados, un oro en cada punta y un siete y un uno en las esquinas, sobre Copa. Es el premio más alto y dice algo del truco.

Todos los dorsos quedan iguales dados vuelta: se dibuja la mitad de arriba y la de abajo es la misma girada media vuelta, o la trama está centrada en la carta.

### Cómo se cuentan los títulos

Se cuentan desde los hechos del motor al cerrar cada partida, como los de la tanda 3, y se rehacen con `fosforos:recalcular`. Lo que hizo falta recordar de cada mano: las cartas repartidas (para saber si había cartas bravas), quién cantó el último truco, si la primera baza fue parda y si lo que se quiso fue un falta envido. Un truco querido ganado vale 2 o más puntos; 3 o más es un retruco y 4 un vale cuatro.

Campeón, Del club y Desafiante salen de los otros juegos: `Titulos::ponerAlDia()` los pone al día desde los torneos y los progresos cuando se cierra una partida de la escalera o de un desafío y cuando un torneo tiene campeón. Se guardan en el jugador, como el resto, para que ninguna página cuente nada al abrirse.

Cada caso tiene su mano armada en `TitulosDeLaTandaCuatroTest`, jugada de punta a punta.

### Las mesas oscuras de noche

Primero las mesas oscuras no se oscurecían de noche (era la regla que había pedido Román): solo bajaba un 15 % lo claro y las barras un tono. Así, el mármol negro y la pana quedaban iguales de día y de noche, y lo marcó. Entre tres opciones con capturas eligió la que se nota en todas: la mesa baja un paso (cerca del 70 % de su color) y lo claro baja más, como en las mesas claras. Se descartó bajar solo lo claro (en la pana casi no se nota) y una "luz de luna" que pasaba el latón a gris.

### Un error que apareció: Truco en tinta sobre rojo

Román preguntó si el contraste de los cantos había cambiado. Era un error de la tanda 3: en las mesas claras el texto de la mesa pasa a tinta, y la ficha roja del Truco tomaba ese color en vez de su blanco. Ahora las fichas de los cantos vuelven a su texto claro, como las cartas y los botones, y un test lo cuida.

### Cada página trae solo sus dorsos

Con veinte dorsos, el mazo que va en cada página pesaba 70 KB. Ahora cada `<x-dorso>` anota el suyo en el pedido (`App\View\DorsosEnUso`) y el mazo, que se dibuja después del contenido, trae solo esos y el de quien mira. La portada y la mesa traen uno; la Colección del perfil, todos. Lo cuida un test.

### Qué se probó y qué no

- **Con tests:** las cuentas de cada título nuevo con manos armadas, los títulos de la escalera y los desafíos, el candado de los premios nuevos y que la administración los tenga todos, el contraste y la familia de color de las mesas y los porotos, y los dorsos por página.
- **En un navegador real:** las seis mesas de día y de noche, los dorsos grandes y a 60 px, los porotos en el tanteador, los distintivos sobre el paño y sobre papel, y la Colección con todo lo nuevo.
- **No se probó:** cómo se ve cada mesa nueva en el celular con una partida avanzada, y los títulos ganados en partidas reales (las cuentas salen de las mismas manos que en los tests).


## M23. Logros: la libreta del club

Pedido por Román el 15 de octubre de 2026, después de la tanda 4. Antes de diseñar se buscaron referencias:

- **Google Play Games** recomienda logros por escalones (el mismo logro con varias metas) y pocos secretos, usados como sorpresa.
- **Yu-kai Chou** separa los logros visibles, los "misterio" y las sorpresas, y advierte que si todos ganan uno deja de significar algo.
- **Chess.com** muestra lo ganado en color y lo que falta como un hueco gris que dice qué pide; el perfil público muestra los logros de cada uno.
- **Steam** muestra qué porcentaje de jugadores tiene cada logro: es una prueba real de esfuerzo.

Se le mostraron tres direcciones en una lámina (sellos de goma, álbum de figuritas, planilla) y eligió todas las recomendadas.

### Qué se eligió

- **Una libreta de socio con sellos de goma.** Cada escalón ganado es un sello entintado del color de su tema, apenas torcido como puesto a mano, con el nombre arriba, la fecha abajo, el ícono en el medio y un punto por escalón. Lo que falta es un círculo punteado; un secreto sin ganar es un signo de pregunta y no dice qué pide. Es tinta de imprenta, como el mazo y la planilla: nada brilla. Se descartó el álbum de figuritas (cada figurita pedía un dibujo propio para no verse vacía) y la planilla (clara, pero la menos memorable).
- **Treinta logros, setenta y ocho sellos,** en seis temas: envido, truco, flor, partidas, juegos y cuatro secretos.
- **Cada título sale de un escalón de su logro,** con el mismo número que tenía (Tantero sigue pidiendo 25 envidos ganados). Así no hay dos listas que cuentan lo mismo, y la libreta muestra el camino hacia cada título. Un test exige que cada título pida lo mismo que su escalón.
- **Un fósforo por sello:** unos ocho niveles si se gana todo, repartidos en meses. Se descartaron los 1, 2 y 3 fósforos por escalón que se habían hablado: daban la mitad del camino y se subía demasiado rápido.
- **Los demás ven tu libreta** en el perfil público, sin el "Nuevo" y sin decir qué piden los secretos que no ganaste.
- **El sello cae en el final de la partida,** debajo de la tira de fósforos, nunca en plena partida, con un golpe seco si el sonido está prendido.
- **De noche,** la libreta pasa a Tinta como las páginas de lectura y cada sello se aclara (Román lo pidió al verla): Copa, Basto y Espada en sus tonos de noche, el envido en Oro, las partidas en Naipe y los secretos en un bordó claro. El contraste de las dos tintas de cada tema se mide en `PaletaTest`.

### Cómo está hecho

- **Los logros son datos** (`app/Juego/fichas-de-logros.php`), como el camino de premios y los desafíos. Cada uno dice qué cuenta mide (una columna del jugador), sus escalones y el título que da.
- **Las cuentas se suman al cerrar cada partida** en `Titulos::en()`, que se partió en `contar()` (lo que suma cada hecho del motor) y `recordar()` (lo que hay que recordar de la mano: las cartas, quién cantó el truco y si quedó sin contestar, quién lo quiso, las bazas, el último envido y la última contraflor). Lo de la partida entera (jugadas, ganadas, de atrás, en las malas, treinta a cero, contra la difícil y la ultra, de a cuatro, revancha) usa la misma regla de los fósforos para saber si se ganó. La mejor racha, los torneos, la escalera y los desafíos se ponen al día desde sus tablas.
- **Los sellos se guardan** (tabla `sellos`, uno por escalón, con su fecha y si ya se vio), así ninguna página cuenta nada al abrirse. `Logros::revisar()` da los que faltan y su fósforo; `fosforos:recalcular` los rehace desde las cuentas sin tocar los que siguen, que conservan su fecha.
- **El porcentaje de socios** es real: las cuentas que jugaron alguna partida y tienen el primer sello, sin contar a los jugadores de ejemplo.
- **El golpe del sello** es un resorte corto (`resorte(0.36, 0.22)`) con la Web Animations API: entra grande y levantado, golpea con un rebote chico y queda en su giro. Solo `transform` y `opacity`; con movimiento reducido, un fundido. En la libreta, los sellos que todavía no viste caen cuando la libreta entra en la pantalla, una sola vez.
- **En el final de la partida** se muestran hasta tres sellos en un renglón, para no empujar los botones: en 1355 x 638 el último botón termina a 630 px y en 360 x 740 a 667.

### Un error que apareció

La primera versión llamó `sellar()` a la función que hace caer los sellos, y en `mesa.js` ya había una `sellar(paso)` que marca cada paso con su partida. La nueva pisó a la vieja y la mesa dejó de mostrar las jugadas. Se vio jugando una partida en el navegador y se le cambió el nombre (`sellarLaLibreta`).

### Qué se probó y qué no

- **Con tests:** que cada logro esté bien escrito (su cuenta existe y alguien la suma, su ícono existe, los escalones suben) y que cada título pida lo mismo que su escalón; que un escalón dé un solo sello y un solo fósforo; que al cerrar la partida se ganen los sellos y el final los muestre; que recalcular saque los que ya no alcanzan; que la libreta no diga qué pide un secreto y marque lo nuevo; cada cuenta nueva con su mano armada; y el contraste de los sellos de día y de noche.
- **En un navegador real:** el final de la partida con dos sellos en 1355 x 638 y 360 x 740, la libreta en el perfil de día y de noche, en el celular, y la caída de los sellos nuevos.
- **No se probó:** los logros más raros ganados en partidas jugadas a mano (sus cuentas salen de las mismas manos que en los tests), y cómo suena el golpe: lo juzga Román.
