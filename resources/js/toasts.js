import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';

/**
 * Los colores se pasan como la variable CSS en crudo, no como el valor ya
 * resuelto: así el toast sigue el tema (claro/oscuro) igual que el resto de
 * la app, sin tener que leer la variable de nuevo si alguien cambia el tema
 * mientras el toast está en pantalla.
 */
const notyf = new Notyf({
    duration: 4500,
    ripple: false,
    position: { x: 'right', y: 'top' },
    dismissible: true,
    types: [
        { type: 'success', background: 'var(--success)' },
        { type: 'error', background: 'var(--danger)' },
    ],
});

/**
 * Confirmación de una acción (crear, guardar, inactivar…). Lo que hoy es
 * session('exito') / session('error') en el backend llega aquí desde
 * partials/alertas.blade.php.
 */
window.mostrarToast = (tipo, mensaje) => {
    if (!mensaje) {
        return;
    }

    if (tipo === 'error') {
        notyf.error(mensaje);
    } else {
        notyf.success(mensaje);
    }
};
