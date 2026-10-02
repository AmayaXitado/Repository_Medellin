/**
 * La campana de la barra superior, al día sin recargar.
 *
 * El contador se pintaba solo al cargar la página, así que una notificación
 * que llegaba con la página abierta no se enteraba nadie hasta navegar. Ahora
 * se pregunta al servidor cada tanto: si hay más sin leer que la última vez,
 * suena y la insignia se actualiza.
 *
 * Se pregunta en vez de recibir avisos empujados por el servidor (websockets)
 * porque para una campana basta, y no obliga a montar un servicio más.
 */
import { reproducir } from './sonidos';

const CADA = 30 * 1000;

/*
 * Cada consulta renueva la sesión. Sin este tope, una pestaña abierta en un
 * equipo que alguien dejó solo seguiría con la sesión iniciada para siempre.
 * Tras 15 minutos sin que nadie toque la página se deja de preguntar, y la
 * sesión vuelve a vencer por su cuenta.
 */
const INACTIVIDAD_MAXIMA = 15 * 60 * 1000;

function iniciar() {
    const campana = document.querySelector('[data-notificaciones]');

    if (!campana) {
        return;
    }

    const insignia = campana.querySelector('[data-insignia]');
    const texto = campana.querySelector('[data-insignia-texto]');
    let conocidas = parseInt(campana.dataset.sinLeer || '0', 10);
    let ultimaActividad = Date.now();

    ['pointerdown', 'keydown'].forEach((evento) =>
        document.addEventListener(evento, () => {
            ultimaActividad = Date.now();
        }, { passive: true }),
    );

    const pintar = (cantidad) => {
        insignia.textContent = cantidad > 99 ? '99+' : String(cantidad);
        insignia.classList.toggle('hidden', cantidad === 0);
        insignia.classList.toggle('flex', cantidad > 0);
        texto.textContent = 'Notificaciones' + (cantidad > 0 ? ` (${cantidad} sin leer)` : '');
    };

    const consultar = async () => {
        if (document.hidden || Date.now() - ultimaActividad > INACTIVIDAD_MAXIMA) {
            return;
        }

        try {
            const respuesta = await fetch(campana.dataset.notificaciones, {
                headers: { Accept: 'application/json' },
            });

            // Si la sesión venció, la respuesta es la pantalla de ingreso y no
            // JSON: no hay nada que pintar, y el siguiente clic lo dirá.
            if (!respuesta.ok || !respuesta.headers.get('content-type')?.includes('application/json')) {
                return;
            }

            const { sin_leer: sinLeer } = await respuesta.json();

            // Solo suena cuando suben. Si bajan, es que alguien las leyó en
            // otra pestaña: se actualiza en silencio.
            if (sinLeer > conocidas) {
                reproducir('notificacion');
            }

            conocidas = sinLeer;
            pintar(sinLeer);
        } catch {
            // Sin red: se reintenta en la siguiente vuelta.
        }
    };

    setInterval(consultar, CADA);

    // Al volver a la pestaña se pregunta en el acto, sin esperar la vuelta.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            consultar();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
} else {
    iniciar();
}
