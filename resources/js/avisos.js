/*
 | Los avisos que llegan al equipo cuando no se está mirando la mesa ("Te toca", "Te cantaron truco").
 |
 | El navegador los recibe aunque el sitio esté cerrado, a través del service worker (public/sw.js). Para
 | eso hacen falta tres cosas, en orden: que la persona dé permiso, que el navegador se anote en su
 | servicio de avisos (le dan una dirección y dos claves) y que esa dirección quede guardada en el servidor.
 |
 | El permiso se pide solo cuando la persona toca un botón, nunca al cargar la página: un navegador que
 | pregunta de entrada recibe un "no" que después cuesta deshacer.
 |
 | Acá no hay pantalla: lo usan la mesa y la sala, que ponen el botón y dicen en qué quedó.
 */

/** No hay avisos en este navegador (o es un iPhone sin el sitio instalado). */
export const SIN_AVISOS = 'no';
/** La persona dijo que no en el navegador: solo ella puede volver a permitirlos, desde sus ajustes. */
export const BLOQUEADOS = 'bloqueados';
export const APAGADOS = 'apagados';
export const PRENDIDOS = 'prendidos';

/** Cada cuánto la página repite que está a la vista. El servidor lo olvida a los 45 segundos. */
const CADA_CUANTO_SE_REPITE = 25000;

function hayAvisos() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

async function suscripcion() {
    const registro = await navigator.serviceWorker.ready;

    return registro.pushManager.getSubscription();
}

/**
 * La clave pública del sitio llega escrita en base64 "para direcciones"; el navegador la pide en bytes.
 */
function enBytes(clave) {
    const relleno = '='.repeat((4 - (clave.length % 4)) % 4);
    const cruda = atob((clave + relleno).replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(cruda, (letra) => letra.charCodeAt(0));
}

function pedir(ruta, token, cuerpo) {
    return fetch(ruta, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(cuerpo),
        credentials: 'same-origin',
    });
}

/**
 * En qué están los avisos en este navegador.
 */
export async function estadoDeAvisos() {
    if (! hayAvisos()) {
        return SIN_AVISOS;
    }

    if (Notification.permission === 'denied') {
        return BLOQUEADOS;
    }

    try {
        return Notification.permission === 'granted' && (await suscripcion()) ? PRENDIDOS : APAGADOS;
    } catch {
        return SIN_AVISOS;
    }
}

/**
 * Pide el permiso, anota el navegador en su servicio de avisos y guarda esa suscripción en el servidor.
 * Devuelve en qué quedó. Se llama desde un toque: el navegador no deja pedir el permiso de otra manera.
 */
export async function prenderAvisos({ clave, suscribir, token }) {
    if (! hayAvisos()) {
        return SIN_AVISOS;
    }

    const permiso = await Notification.requestPermission();

    if (permiso !== 'granted') {
        return permiso === 'denied' ? BLOQUEADOS : APAGADOS;
    }

    try {
        const registro = await navigator.serviceWorker.ready;
        const anotado = (await registro.pushManager.getSubscription())
            ?? (await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: enBytes(clave) }));
        const { endpoint, keys } = anotado.toJSON();
        const respuesta = await pedir(suscribir, token, { destino: endpoint, p256dh: keys.p256dh, auth: keys.auth });

        if (! respuesta.ok) {
            // El servidor no la guardó: no tiene sentido que el navegador quede anotado a medias.
            await anotado.unsubscribe();

            return APAGADOS;
        }

        return PRENDIDOS;
    } catch {
        return APAGADOS;
    }
}

/**
 * Da de baja este navegador: en su servicio de avisos y en el servidor.
 */
export async function apagarAvisos({ baja, token }) {
    try {
        const anotado = await suscripcion();

        if (anotado) {
            await pedir(baja, token, { destino: anotado.endpoint });
            await anotado.unsubscribe();
        }
    } catch {
        // Sin conexión: el navegador queda anotado y se puede volver a intentar.
        return PRENDIDOS;
    }

    return APAGADOS;
}

/**
 * Le va diciendo al servidor si esta página está a la vista: a quien está mirando la mesa no se le manda
 * un aviso. Lo dice al quedar a la vista, lo repite cada tanto y avisa cuando se tapa o se cierra.
 */
export function avisarPresencia({ vista, token }) {
    let reloj = null;

    const decir = (visible) => {
        // Al cerrarse la página un pedido común se corta: este sale igual.
        const cuerpo = new FormData();

        cuerpo.append('_token', token);
        cuerpo.append('visible', visible ? '1' : '0');
        navigator.sendBeacon(vista, cuerpo);
    };

    const alCambiar = () => {
        clearInterval(reloj);

        if (document.visibilityState === 'visible') {
            decir(true);
            reloj = setInterval(() => decir(true), CADA_CUANTO_SE_REPITE);
        } else {
            decir(false);
        }
    };

    document.addEventListener('visibilitychange', alCambiar);
    window.addEventListener('pagehide', () => decir(false));
    alCambiar();
}
