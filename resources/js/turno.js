/**
 * La pantalla de marcar turno (/t/{token}).
 *
 * - Pide la ubicación al entrar, no al tocar el botón: así ya está lista.
 * - Cambia el texto del botón según la cédula: «Registrar entrada» o
 *   «Registrar salida». Lo que vale es lo que decida el servidor; esto solo
 *   le dice a la persona qué va a pasar.
 * - Al enviar, espera la ubicación (con tope) y la pone en campos ocultos.
 *   Sin señal se envía igual: el servidor lo anota como novedad.
 */
import { datosDeUbicacion, pedirUbicacionAhora, prepararUbicacion } from './foto-georreferencial';

const TEXTOS = {
    entrada: 'Registrar entrada',
    salida: 'Registrar salida',
    cerrado: 'Ya registraste tu turno de hoy',
};

function iniciar(formulario) {
    const boton = formulario.querySelector('[data-turno-boton]');
    const textoBase = boton?.textContent.trim() ?? '';
    let enviando = false;

    prepararUbicacion((motivo) => {
        const aviso = formulario.querySelector('[data-turno-ubicacion]');

        if (aviso) {
            aviso.textContent = motivo;
            aviso.classList.toggle('hidden', motivo === '');
        }
    });

    formulario.addEventListener('cedula:consultada', (evento) => {
        if (boton) {
            const accion = evento.detail?.accion;
            boton.textContent = TEXTOS[accion] ?? textoBase;
            // Con el turno del día cerrado no hay nada que marcar: si la hora
            // está mal, la corrige Coordinación, no un segundo envío.
            boton.disabled = accion === 'cerrado';
        }
    });

    formulario.addEventListener('submit', async (evento) => {
        if (enviando) {
            return;
        }

        evento.preventDefault();

        if (!formulario.reportValidity()) {
            return;
        }

        enviando = true;

        if (boton) {
            boton.disabled = true;
            boton.textContent = 'Ubicando…';
        }

        // Tope propio de 10 s: más allá de eso se marca sin ubicación antes
        // que dejar a la persona mirando un botón que no responde.
        await Promise.race([pedirUbicacionAhora(), new Promise((r) => setTimeout(r, 10000))]);

        const ubicacion = datosDeUbicacion();
        const poner = (nombre, valor) => {
            const campo = formulario.querySelector(`[name="${nombre}"]`);

            if (campo) {
                campo.value = valor ?? '';
            }
        };

        poner('lat', ubicacion?.lat);
        poner('lng', ubicacion?.lng);
        poner('precision_m', ubicacion?.precision);
        poner('municipio', ubicacion?.municipio);

        if (boton) {
            boton.textContent = 'Registrando…';
        }

        formulario.submit();
    });
}

document.querySelectorAll('[data-turno]').forEach(iniciar);
