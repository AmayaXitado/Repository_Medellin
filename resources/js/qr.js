/**
 * QR de los enlaces de turno, dibujado en el navegador: el servidor no
 * necesita ninguna librería de imágenes.
 *
 * Se engancha solo: cualquier elemento con data-qr="<url>" recibe el SVG.
 * Corrección de errores 'M': aguanta un QR impreso algo gastado o con una
 * mancha, sin volverse tan denso que cueste leerlo con un celular viejo.
 */
import qrcode from 'qrcode-generator';

function dibujar(contenedor) {
    const qr = qrcode(0, 'M');
    qr.addData(contenedor.dataset.qr);
    qr.make();

    contenedor.innerHTML = qr.createSvgTag({ cellSize: 8, margin: 4, scalable: true });
    contenedor.querySelector('svg')?.setAttribute('role', 'img');
    contenedor.querySelector('svg')?.setAttribute('aria-label', 'Código QR del enlace');
}

document.querySelectorAll('[data-qr]').forEach(dibujar);
