/*
 | Lo que hace falta para que el sitio se instale como una aplicación.
 |
 | 1. Registra el service worker (public/sw.js), que guarda lo estático y muestra la página de "sin
 |    conexión". Va con la versión de lo compilado en la dirección: cuando se publica algo nuevo esa
 |    dirección cambia y el navegador lo instala de nuevo.
 | 2. Cuando el navegador avisa que el sitio se puede instalar, muestra los botones de "Instalar" que
 |    haya en la página (los que llevan data-instalar). Tocarlos abre el cartel del propio navegador.
 |    Hasta entonces esos botones no se ven: ofrecer algo que no se puede hacer es peor que no ofrecerlo.
 | 3. En un iPhone el navegador no avisa nada ni deja abrir ese cartel: se instala a mano, desde
 |    "Compartir". Ahí se muestra la indicación que lleve data-instalar-en-iphone.
 | 4. La franja de la portada (data-donde-instalar) se ve en cualquiera de los dos casos, y en ningún otro.
 */

let cartel = null;

// Solo Safari en iPhone y iPad tiene esta propiedad, y vale false mientras el sitio no esté instalado.
const enIphone = () => navigator.standalone === false;

function mostrar(selector, visible) {
    document.querySelectorAll(selector).forEach((elemento) => (elemento.hidden = ! visible));
}

function ofrecer(conBoton) {
    mostrar('[data-instalar]', conBoton);
    mostrar('[data-donde-instalar]', conBoton || enIphone());
}

async function instalar() {
    if (! cartel) {
        return;
    }

    cartel.prompt();

    // Aceptó o no, ese cartel no se puede volver a abrir: el navegador manda otro aviso si corresponde.
    await cartel.userChoice;
    cartel = null;
    ofrecer(false);
}

export function prepararInstalacion() {
    const version = document.querySelector('meta[name="version"]')?.content;

    // En desarrollo no se registra: Vite sirve los archivos desde otra dirección y no hay nada que guardar.
    if ('serviceWorker' in navigator && version && version !== 'desarrollo') {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(`/sw.js?v=${version}`).catch(() => {
                // Sin service worker el sitio anda igual: no se instala ni recibe avisos.
            });
        });
    }

    window.addEventListener('beforeinstallprompt', (evento) => {
        // El navegador mostraría su propia franja: se guarda el aviso para abrirlo desde el botón.
        evento.preventDefault();
        cartel = evento;
        ofrecer(true);
    });

    window.addEventListener('appinstalled', () => {
        cartel = null;
        ofrecer(false);
    });

    document.addEventListener('click', (evento) => {
        if (evento.target.closest('[data-instalar]')) {
            instalar();
        }
    });

    mostrar('[data-instalar-en-iphone]', enIphone());
    mostrar('[data-donde-instalar]', enIphone());
}
