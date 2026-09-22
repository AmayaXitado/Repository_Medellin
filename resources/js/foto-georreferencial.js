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
 * Última posición conocida. Se pide al entrar al módulo y no al tomar la foto:
 * en iPhone el aviso de permiso aparecía justo al volver de la cámara, y
 * mientras la persona tocaba «Permitir» se agotaba la espera y la foto salía
 * sin coordenadas. Además la primera lectura de GPS en iOS tarda varios
 * segundos; empezando antes, ya está lista cuando se estampa.
 */
let ultima = null;
let primera = null;

export function prepararUbicacion() {
    if (primera || !navigator.geolocation) {
        return;
    }

    // ponytail: watchPosition mantiene el GPS activo mientras la página esté
    // abierta; si el consumo de batería importa, pararlo con clearWatch al enviar.
    primera = new Promise((resolve) => {
        navigator.geolocation.watchPosition(
            (pos) => {
                ultima = pos.coords;
                resolve();
            },
            () => resolve(), // negada o sin señal: la foto se estampa igual, sin coordenadas
            { enableHighAccuracy: true, maximumAge: 60000 },
        );
    });
}

function ubicacion() {
    prepararUbicacion();

    if (ultima || !primera) {
        return Promise.resolve(ultima);
    }

    // Aún no llega la primera lectura: se le da un margen amplio antes de
    // rendirse, que un GPS en frío en exteriores puede tardar.
    return Promise.race([primera, new Promise((r) => setTimeout(r, 15000))]).then(() => ultima);
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
