/**
 * Botones de «copiar al portapapeles» que contestan.
 *
 * Copiar en silencio deja a la persona sin saber si funcionó, y con estos
 * enlaces eso se paga caro: la URL del token se muestra una sola vez y no
 * vuelve. Si el copiado falla sin avisar, el enlace se pierde y hay que
 * generar otro. Por eso el botón responde siempre, salga bien o salga mal.
 *
 * Se engancha solo: cualquier elemento con data-copiar="<selector>" queda
 * activo, donde el selector apunta al campo cuyo valor se copia.
 */

/** Lo que dura la confirmación antes de que el botón vuelva a lo suyo. */
const DURACION_AVISO = 2200;

const ICONO_LISTO = `<svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5"
    viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round"
    d="m4.5 12.75 6 6 9-13.5"/></svg>`;

/**
 * El portapapeles moderno solo existe en contextos seguros —https, o
 * localhost mientras se desarrolla—. En una instalación servida por http
 * sencillamente no está, así que queda el camino viejo: seleccionar el
 * campo y pedirle la copia al navegador.
 */
async function alPortapapeles(campo) {
    const texto = campo.value ?? campo.textContent ?? '';

    if (texto === '') {
        return false;
    }

    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(texto);

            return true;
        } catch (e) {
            // Contexto inseguro o permiso denegado: se prueba lo de abajo.
        }
    }

    try {
        campo.focus();
        campo.select?.();

        return document.execCommand('copy');
    } catch (e) {
        return false;
    }
}

/**
 * Cambia el botón por su confirmación y lo devuelve a la normalidad.
 *
 * El ancho se congela mientras dura el aviso: «Copiar» y «¡Copiado!» no
 * miden lo mismo, y sin esto el botón pega un salto y mueve lo que tiene
 * al lado justo cuando la persona está mirando ahí.
 */
function confirmar(boton) {
    boton.dataset.textoOriginal ??= boton.innerHTML;
    clearTimeout(Number(boton.dataset.temporizador));

    boton.style.minWidth = `${boton.offsetWidth}px`;
    boton.innerHTML = `${ICONO_LISTO}<span>¡Copiado!</span>`;
    boton.classList.add('boton-copiado');

    boton.dataset.temporizador = setTimeout(() => {
        boton.innerHTML = boton.dataset.textoOriginal;
        boton.classList.remove('boton-copiado');
        boton.style.minWidth = '';
    }, DURACION_AVISO);
}

/**
 * Cuando no se pudo copiar, el campo queda seleccionado: así lo único que
 * falta es un Ctrl+C, en vez de tener que apuntar con el ratón a un texto
 * largo sin espacios.
 */
function explicarElFallo(campo) {
    campo.focus();
    campo.select?.();

    window.mostrarToast?.(
        'error',
        'El navegador no dejó copiar automáticamente. El enlace quedó seleccionado: cópialo con Ctrl+C.',
    );
}

document.addEventListener('click', async (evento) => {
    const boton = evento.target.closest('[data-copiar]');

    if (!boton) {
        return;
    }

    evento.preventDefault();

    const campo = document.querySelector(boton.dataset.copiar);

    if (!campo) {
        return;
    }

    await alPortapapeles(campo) ? confirmar(boton) : explicarElFallo(campo);
});
