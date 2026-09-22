/**
 * Estampa hora y ubicación sobre una foto tomada con la cámara, con las 3
 * marcas del Comité en una franja inferior. Solo se aplica a fotos de cámara
 * (no a lo elegido de galería/archivo): es evidencia de que se tomó ahí y
 * entonces, no un sello decorativo.
 *
 * Todo con Canvas + Geolocation nativos del navegador. Si el usuario niega
 * la ubicación, la foto se estampa igual, solo sin coordenadas.
 */

const LOGOS = ['/img/COMITE DE ESTUDIOS MEDICOS.png', '/img/Mente.png', '/img/Salud.png'];

function cargarImagen(src) {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null); // un logo que no carga no debe tumbar el envío
        img.src = src;
    });
}

/**
 * Última posición conocida. Se pide al entrar al módulo y otra vez en cada
 * toque de «Tomar foto», no al estampar: en iPhone el aviso de permiso salía
 * justo al volver de la cámara y la espera se agotaba mientras la persona
 * tocaba «Permitir». Volver a pedirla en cada toque es también lo que deja
 * recuperarse sin recargar a quien la negó y luego la activó en Ajustes.
 */
let ultima = null;
let pedido = null;
let avisar = () => {};

// Sin esto la foto solo decía «Ubicación no disponible» y nadie sabía por qué.
// Dónde se devuelve el permiso cambia según el teléfono, y es lo único útil
// que se puede decir: ningún navegador deja volver a preguntar una vez negado.
const esIphone = /iPhone|iPad|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPad que se anuncia como Mac
const esAndroid = /Android/.test(navigator.userAgent);

const COMO_PERMITIR = esIphone
    ? 'toca «aA» en la barra de direcciones → Configuración del sitio web → Ubicación → Permitir. Revisa también Ajustes → Privacidad → Localización.'
    : esAndroid
        ? 'toca el candado junto a la dirección de la página → Permisos → Ubicación → Permitir. Si no aparece, revisa Ajustes del teléfono → Aplicaciones → tu navegador → Permisos → Ubicación.'
        : 'haz clic en el ícono junto a la dirección de la página y permite la ubicación.';

const MOTIVOS = {
    1: 'No hay permiso de ubicación: ' + COMO_PERMITIR + ' Luego vuelve a tomar la foto.',
    2: 'El teléfono no está entregando la ubicación. Revisa que la ubicación (GPS) esté activada en los ajustes del teléfono.',
    3: 'La ubicación está tardando en llegar. Al aire libre la señal mejora.',
};

function pedir() {
    if (!window.isSecureContext || !navigator.geolocation) {
        avisar('Este navegador no permite leer la ubicación en esta página.');
        return null;
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                ultima = pos.coords;
                avisar('');
                resolve();
            },
            (error) => {
                avisar(MOTIVOS[error.code] ?? MOTIVOS[2]);
                resolve();
            },
            { enableHighAccuracy: true, maximumAge: 60000, timeout: 20000 },
        );
    }).finally(() => {
        pedido = null;
    });
}

/** @param {(texto: string) => void} [alAvisar] recibe el motivo cuando no hay ubicación, y '' cuando la hay. */
export function prepararUbicacion(alAvisar) {
    if (alAvisar) {
        avisar = alAvisar;
    }

    pedido ??= pedir();
}

function ubicacion() {
    if (ultima) {
        return Promise.resolve(ultima);
    }

    prepararUbicacion();

    if (!pedido) {
        return Promise.resolve(null);
    }

    return Promise.race([pedido, new Promise((r) => setTimeout(r, 20000))]).then(() => ultima);
}

export async function estamparFoto(archivo) {
    const [foto, coords, ...logos] = await Promise.all([
        cargarImagen(URL.createObjectURL(archivo)),
        ubicacion(),
        ...LOGOS.map(cargarImagen),
    ]);

    if (!foto) {
        return archivo;
    }

    const lienzo = document.createElement('canvas');
    lienzo.width = foto.width;
    lienzo.height = foto.height;
    const ctx = lienzo.getContext('2d');
    ctx.drawImage(foto, 0, 0);

    const alturaFranja = Math.max(70, Math.round(foto.height * 0.12));
    const y0 = foto.height - alturaFranja;

    ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
    ctx.fillRect(0, y0, foto.width, alturaFranja);

    const logoAlto = alturaFranja * 0.6;
    let x = 16;
    logos.forEach((logo) => {
        if (!logo) return;
        const w = logoAlto * (logo.width / logo.height);
        ctx.drawImage(logo, x, y0 + (alturaFranja - logoAlto) / 2, w, logoAlto);
        x += w + 14;
    });

    const hora = new Intl.DateTimeFormat('es-CO', { dateStyle: 'short', timeStyle: 'medium' }).format(new Date());
    const lugar = coords
        ? `${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)}`
        : 'Ubicación no disponible';

    ctx.textAlign = 'right';
    ctx.fillStyle = '#fff';
    ctx.font = `bold ${Math.round(alturaFranja * 0.26)}px sans-serif`;
    ctx.fillText(hora, foto.width - 16, y0 + alturaFranja * 0.45);
    ctx.font = `${Math.round(alturaFranja * 0.22)}px sans-serif`;
    ctx.fillText(lugar, foto.width - 16, y0 + alturaFranja * 0.78);

    const blob = await new Promise((resolve) => lienzo.toBlob(resolve, archivo.type || 'image/jpeg', 0.92));

    return blob ? new File([blob], archivo.name, { type: blob.type, lastModified: Date.now() }) : archivo;
}
