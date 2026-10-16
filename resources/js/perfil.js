/*
 | El perfil (paginas/perfil.blade.php).
 |
 | Tres movimientos, cada uno con su motivo:
 | - Al entrar, la carta se reparte desde el mazo: es la tuya, y sale de tu mesa. Pasa una vez por visita.
 | - Los fósforos ganados desde la última visita caen en la tira, de a uno, como en el tanteador. Si no
 |   ganaste nada desde entonces, la tira ya está quieta: no se cuenta lo que ya viste.
 | - En el probador, la carta se da vuelta para mostrar un dorso (es una transición: se corta a mitad).
 | - Los sellos nuevos de la libreta golpean el papel cuando la libreta entra en la pantalla, una sola vez.
 | Las pestañas de la Colección cambian sin animación: se tocan muchas veces.
 */
import { llegarDelMazo, resorte } from './cartas';

// El sello golpea el papel: el mismo resorte que en el final de la partida.
const SELLA = resorte(0.36, 0.22);
const ENTRE_SELLOS = 170;

const POR_NIVEL = 10;
const ENTRE_FOSFOROS = 180;
const ANTES_DE_CONTAR = 420;

const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');

// Lo que dice el botón de usar, según lo que se está probando.
const USAR = {
    carta: 'Usar esta carta',
    dorso: 'Usar este dorso',
    mesa: 'Usar esta mesa',
    anotar: 'Anotar así',
    titulo: 'Usar este título',
    distintivo: 'Usar este distintivo',
};

export default ({ jugador, fosforos, propio, prueba, enUso, rutas, pestana, titulos }) => ({
    // Si se acaba de elegir algo con el formulario común, la Colección vuelve abierta en esa pestaña.
    pestana: pestana ?? 'cartas',
    prueba,
    rutas: rutas ?? {},
    // Cómo se lee el título que se está probando: Cantor o Cantora. Arranca como lo usa hoy.
    femenino: enUso?.femenino ?? false,
    tira: fosforos % POR_NIVEL,
    // Lo que usás ahora: elegir algo lo cambia acá, sin recargar la página.
    enUso: enUso ?? {},
    guardando: false,
    // La sección que está a la vista: el índice la marca con el fósforo puesto.
    aqui: 'resumen',

    get esLaQueUsa() {
        if (this.prueba?.clave === undefined || this.enUso[this.prueba.tipo] !== this.prueba.clave) {
            return false;
        }

        // Un título es el que usa solo si además se lee igual.
        return this.prueba.tipo !== 'titulo' || this.enUso.femenino === this.femenino;
    },

    get textoDeUsar() {
        if (this.prueba?.tipo === 'distintivo' && this.prueba.clave === 'ninguno') {
            return 'Sacar el distintivo';
        }

        if (this.prueba?.tipo === 'titulo' && this.enUso.titulo === this.prueba.clave) {
            return 'Usarlo así';
        }

        return USAR[this.prueba?.tipo] ?? 'Usar';
    },

    /** Cómo se lee un título en una de sus dos formas. */
    tituloDe(clave, femenino) {
        const titulo = titulos?.find((uno) => uno.clave === clave);

        return titulo ? (femenino ? titulo.femenino : titulo.nombre) : '';
    },

    init() {
        if (! movimientoReducido.matches) {
            llegarDelMazo(this.$refs.carta, this.$refs.mazo, 0, { reducido: false, demora: 120 });
        }

        if (propio) {
            this.contarLoNuevo();
            this.sellarLoNuevo();
        }

        this.seguirLasSecciones();
    },

    /**
     * Marca en el índice la sección que está a la vista: la que cruza la franja de arriba de la pantalla.
     */
    seguirLasSecciones() {
        if (! ('IntersectionObserver' in window)) {
            return;
        }

        const observador = new IntersectionObserver((entradas) => {
            for (const entrada of entradas) {
                if (entrada.isIntersecting) {
                    this.aqui = entrada.target.id;
                }
            }
        }, { rootMargin: '-25% 0px -70% 0px' });

        document.querySelectorAll('.perfil-indice a[href^="#"]').forEach((enlace) => {
            const seccion = document.getElementById(enlace.getAttribute('href').slice(1));

            if (seccion) {
                observador.observe(seccion);
            }
        });
    },

    /**
     * Los sellos que todavía no habías visto golpean el papel, de a uno, la primera vez que la libreta entra en
     * la pantalla. Con movimiento reducido ya están puestos: el "Nuevo" alcanza.
     */
    sellarLoNuevo() {
        const libreta = document.getElementById('libreta');
        const nuevos = () => [...libreta.querySelectorAll('.libreta-logro.es-nuevo .libreta-sello')].filter((sello) => sello.getClientRects().length > 0);

        if (! libreta || movimientoReducido.matches || ! nuevos().length || ! ('IntersectionObserver' in window)) {
            return;
        }

        const observador = new IntersectionObserver(([entrada]) => {
            if (! entrada.isIntersecting) {
                return;
            }

            observador.disconnect();
            nuevos().forEach((sello, numero) => {
                // Termina en su giro de siempre (el del estilo), así no salta al quedar quieto.
                const giro = parseFloat(sello.style.getPropertyValue('--giro')) || 0;

                sello.animate([
                    { opacity: 0, transform: `translateY(-14%) scale(1.5) rotate(${giro - 7}deg)` },
                    { opacity: 1, offset: 0.3 },
                    { opacity: 1, transform: `rotate(${giro}deg)` },
                ], { ...SELLA, delay: 200 + numero * ENTRE_SELLOS, fill: 'backwards' });
            });
        }, { threshold: 0.35 });

        observador.observe(libreta);
    },

    /**
     * Los fósforos de la tira que se ganaron desde la última visita, en este navegador. Si se subió de
     * nivel desde entonces, la tira nueva cuenta desde cero.
     */
    contarLoNuevo() {
        const clave = `vale-cuatro:fosforos-vistos:${jugador}`;
        let vistos = null;

        try {
            vistos = Number(localStorage.getItem(clave) ?? NaN);
            localStorage.setItem(clave, String(fosforos));
        } catch {
            return;
        }

        if (! Number.isFinite(vistos) || vistos >= fosforos || movimientoReducido.matches) {
            return;
        }

        const hasta = fosforos % POR_NIVEL;
        const mismoNivel = Math.floor(vistos / POR_NIVEL) === Math.floor(fosforos / POR_NIVEL);

        this.tira = mismoNivel ? vistos % POR_NIVEL : 0;

        const caer = () => {
            if (this.tira < hasta) {
                this.tira++;
                setTimeout(caer, ENTRE_FOSFOROS);
            }
        };

        setTimeout(caer, ANTES_DE_CONTAR);
    },

    /**
     * Prueba una pieza de la Colección: la muestra en el probador. Una carta va de frente; un dorso, con
     * la carta dada vuelta. Se copia el dibujo de la pieza tocada, así el probador no pide nada al servidor.
     */
    probar(boton, pieza) {
        const dibujo = boton.querySelector('svg');

        if (dibujo && pieza.tipo === 'carta') {
            this.$refs.cara.replaceChildren(dibujo.cloneNode(true));
        }

        if (dibujo && pieza.tipo === 'dorso') {
            this.$refs.dorso.replaceChildren(dibujo.cloneNode(true));
        }

        // Una mesa se prueba con tu carta encima, la que se ve arriba.
        if (pieza.tipo === 'mesa') {
            const tuya = this.$refs.carta.querySelector('svg');

            if (tuya) {
                this.$refs.cara.replaceChildren(tuya.cloneNode(true));
            }
        }

        // Un título se prueba como lo leés hoy; después se puede cambiar a la otra forma.
        if (pieza.tipo === 'titulo') {
            this.femenino = this.enUso.femenino;
        }

        this.prueba = pieza;

        if (! this.$refs.probador.open) {
            this.$refs.probador.showModal();
        }
    },

    /** Cierra el probador; el foco vuelve solo a la pieza que lo abrió. */
    cerrarProbador() {
        this.$refs.probador.close();
    },

    /**
     * Elige lo que está en el probador, sin recargar ni moverse de lugar: se guarda, tu mesa de arriba
     * cambia (la carta, o el dorso del mazo) y el aviso de abajo lo confirma.
     */
    async usar() {
        const { tipo, clave } = this.prueba ?? {};

        if (this.guardando || ! this.rutas[tipo]) {
            return;
        }

        this.guardando = true;

        try {
            const respuesta = await fetch(this.rutas[tipo], {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                },
                body: new URLSearchParams({ _method: 'PUT', [tipo]: clave, ...(tipo === 'titulo' ? { femenino: this.femenino ? '1' : '0' } : {}) }),
            });
            const datos = await respuesta.json().catch(() => ({}));

            if (! respuesta.ok) {
                throw new Error(datos.errors?.[tipo]?.[0] ?? 'No se pudo guardar. Probá de nuevo.');
            }

            this.enUso[tipo] = clave;

            if (tipo === 'titulo') {
                this.enUso.femenino = this.femenino;
            }

            this.ponerEnLaMesa(tipo);
            this.cerrarProbador();
            this.avisar('hecho', datos.mensaje);
        } catch (error) {
            this.avisar('error', error instanceof TypeError ? 'No hay conexión. Probá de nuevo.' : error.message);
        } finally {
            this.guardando = false;
        }
    },

    /**
     * Lleva lo elegido a tu mesa de arriba: la carta grande o el dorso de cada carta del mazo. La mesa, el
     * título y el distintivo de arriba siguen solos a lo que se usa (enUso).
     */
    ponerEnLaMesa(tipo) {
        if (! ['carta', 'dorso'].includes(tipo)) {
            return;
        }

        if (tipo === 'carta') {
            const cara = this.$refs.cara.querySelector('svg');

            if (cara) {
                this.$refs.carta.replaceChildren(cara.cloneNode(true));
            }

            return;
        }

        const plantilla = this.$refs.dorso.querySelector('use')?.getAttribute('href');

        if (plantilla) {
            this.$refs.mazo.querySelectorAll('use').forEach((uso) => uso.setAttribute('href', plantilla));
        }
    },

    avisar(tipo, texto) {
        window.dispatchEvent(new CustomEvent('aviso', { detail: { tipo, texto } }));
    },
});
