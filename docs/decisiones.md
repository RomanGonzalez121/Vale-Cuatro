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

### Los modos con una sala abierta

- **Problema:** Román abrió una sala, volvió a los modos y creyó que la pantalla estaba rota: los niveles del bot estaban apagados y el motivo se leía poco.
- **Se eligió:** decirlo de entrada ("Tenés una sala abierta esperando rival. Para jugar contra el bot o en otro modo, primero cancelala.") y poner "Cancelar sala" ahí mismo, al lado de "Volver a la sala". Antes había que entrar a la sala para cerrarla.

### Detalles del arranque

- **Sin claves, ningún comando arranca.** Con `BROADCAST_CONNECTION=reverb` y las claves vacías, Laravel falla al crear el emisor y también falla el comando que genera las claves. Se resuelve corriendo `reverb:install` con la conexión en `null` solo para ese comando. Está explicado en `.env.example`, que deja la conexión en `log` para que la integración continua y quien clone el proyecto sin Reverb sigan arrancando.
- **Quinto proceso local:** `php artisan reverb:start`, en el puerto 8080.
- **Probado de punta a punta:** un cliente de Node se conecta al WebSocket como lo haría un navegador, Laravel manda un aviso y llega.
- **Pendiente que no es de M5:** `npm audit` marca dos vulnerabilidades críticas en `shell-quote`, que viene de `concurrently`, una herramienta de desarrollo que ya estaba antes.
