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

// Si la persona ya contestó al permiso, con un sí o con un no. Mientras no
// haya contestado, abrir la cámara taparía el aviso.
let respondida = false;
let avisar = () => {};

// Sin esto la foto solo decía «Ubicación no disponible» y nadie sabía por qué.
// Dónde se devuelve el permiso cambia según el teléfono, y es lo único útil
// que se puede decir: ningún navegador deja volver a preguntar una vez negado.
const esIphone = /iPhone|iPad|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1); // iPad que se anuncia como Mac
const esAndroid = /Android/.test(navigator.userAgent);

// En iPhone todos los navegadores usan el motor de Safari, pero cada app
// tiene su propio permiso de ubicación ante el sistema. Si Opera o Chrome no
// lo tienen, la página no puede ni preguntar: se arregla en Ajustes del iPhone.
const esSafari = esIphone && !/CriOS|FxiOS|EdgiOS|OPiOS|OPT\/|OPX\/|GSA\//.test(navigator.userAgent);

const COMO_PERMITIR = esSafari
    ? 'toca «aA» en la barra de direcciones → Configuración del sitio web → Ubicación → Permitir. Revisa también Ajustes → Privacidad → Localización → Sitios web de Safari.'
    : esIphone
        ? 'abre Ajustes del iPhone → busca este navegador (Opera, Chrome…) → Ubicación → «Al usar la app». Revisa también Ajustes → Privacidad → Localización, que esté activada.'
    : esAndroid
        ? 'toca el candado junto a la dirección de la página → Permisos → Ubicación → Permitir. Si no aparece, revisa Ajustes del teléfono → Aplicaciones → tu navegador → Permisos → Ubicación.'
        : 'haz clic en el ícono junto a la dirección de la página y permite la ubicación.';

const MOTIVOS = {
    1: 'No hay permiso de ubicación: ' + COMO_PERMITIR + ' Luego vuelve a tomar la foto.',
    2: 'El teléfono no está entregando la ubicación. Revisa que la ubicación (GPS) esté activada en los ajustes del teléfono.',
    3: 'La ubicación está tardando en llegar. Al aire libre la señal mejora.',
};

let ciudad = null;
let ciudadPedida = null;

/**
 * El municipio, que en OpenStreetMap no tiene una sola etiqueta. En Colombia
 * 'county' es el municipio y 'city' puede traer la vereda: en el norte del
 * Valle de Aburrá, 'city' devolvía «Platanito Parte Baja» donde el municipio
 * es Barbosa. Y lo que llega viene con los nombres del DANE pegados
 * —«Perímetro Urbano Medellín», «Bogotá ciudad»—, que en una foto sobran.
 */
export function nombreMunicipio(address = {}) {
    const municipio = address.county || address.city || address.town
        || address.village || address.municipality;

    return municipio
        ? municipio
            .replace(/^(per[ií]metro|zona|[áa]rea)\s+urban[ao]\s+(de\s+)?/i, '')
            .replace(/\s+ciudad$/i, '')
            .trim() || null
        : null;
}

/**
 * El nombre del lugar a partir de las coordenadas, contra Nominatim (el
 * geocodificador de OpenStreetMap): unas cifras no le dicen nada a quien
 * después revisa la foto, «Medellín, Antioquia» sí. Si no hay red o tarda,
 * la foto sale igual con las coordenadas solas.
 */
async function resolverCiudad({ latitude, longitude }) {
    const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=12&accept-language=es'
        + `&lat=${latitude}&lon=${longitude}`;

    try {
        const respuesta = await fetch(url, { signal: AbortSignal.timeout?.(8000) });
        const { address = {} } = await respuesta.json();

        ciudad = [nombreMunicipio(address), address.state].filter(Boolean).join(', ') || null;
    } catch {
        ciudad = null; // sin red, sin permiso de salida o demasiado lento
    }
}

/** La ciudad solo se espera si ya hay coordenadas: nunca retrasa la foto por sí sola. */
function esperarCiudad() {
    if (ciudad || !ciudadPedida) {
        return Promise.resolve(ciudad);
    }

    return Promise.race([ciudadPedida, new Promise((r) => setTimeout(r, 4000))]).then(() => ciudad);
}

function pedir() {
    if (!window.isSecureContext || !navigator.geolocation) {
        avisar('Este navegador no permite leer la ubicación en esta página.');
        return null;
    }

    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                ultima = pos.coords;
                respondida = true;
                ciudadPedida ??= resolverCiudad(pos.coords);
                avisar('');
                resolve();
            },
            (error) => {
                respondida = true;
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

/** Si ya se sabe qué pasa con la ubicación: hay coordenadas, o la persona contestó que no. */
export function ubicacionResuelta() {
    return ultima !== null || respondida;
}

export function hayUbicacion() {
    return ultima !== null;
}

/** Pide la ubicación y espera la respuesta (o el tope de 20 s del propio navegador). */
export function pedirUbicacionAhora() {
    prepararUbicacion();

    return pedido ?? Promise.resolve();
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

/** Cuánto hay que encoger una imagen para que su lado largo no pase del tope (1 = déjala como está). */
export function escalaPara(ancho, alto, ladoMaximo) {
    const lado = Math.max(ancho, alto);

    return ladoMaximo > 0 && lado > ladoMaximo ? ladoMaximo / lado : 1;
}

function aLienzo(imagen, escala) {
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(imagen.width * escala);
    lienzo.height = Math.round(imagen.height * escala);
    lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height);

    return lienzo;
}

/**
 * Del lienzo a un archivo. Todo sale JPEG salvo el PNG, que se queda PNG para
 * no tragarse la transparencia: toBlob devuelve PNG cuando no sabe hacer el
 * formato pedido (WebP en Safari), y una foto en PNG pesa varias veces más.
 */
async function aArchivo(lienzo, original, lastModified = original.lastModified) {
    const tipo = original.type === 'image/png' ? 'image/png' : 'image/jpeg';
    const blob = await new Promise((resolve) => lienzo.toBlob(resolve, tipo, 0.92));

    if (!blob) {
        return original;
    }

    // El servidor guarda la extensión que dice el nombre: si el contenido pasó
    // a JPEG, el nombre tiene que decirlo también.
    const nombre = tipo === original.type ? original.name : original.name.replace(/\.[^.]+$/, '') + '.jpg';

    return new File([blob], nombre, { type: blob.type, lastModified });
}

/**
 * Reduce una imagen de galería al tope de resolución. Lo que ya cabe se
 * devuelve intacto: recomprimir un JPEG que no hace falta tocar solo lo
 * degrada.
 */
export async function reducirImagen(archivo, ladoMaximo) {
    if (!ladoMaximo || !archivo.type.startsWith('image/')) {
        return archivo;
    }

    const imagen = await cargarImagen(URL.createObjectURL(archivo));
    const escala = imagen ? escalaPara(imagen.width, imagen.height, ladoMaximo) : 1;

    return escala === 1 ? archivo : aArchivo(aLienzo(imagen, escala), archivo);
}

export async function estamparFoto(archivo, ladoMaximo = 0) {
    const [foto, coords, ...logos] = await Promise.all([
        cargarImagen(URL.createObjectURL(archivo)),
        ubicacion(),
        ...LOGOS.map(cargarImagen),
    ]);

    if (!foto) {
        return archivo;
    }

    // Se dibuja ya a la escala del tope, y la franja se calcula sobre el
    // lienzo reducido: así el sello conserva siempre las mismas proporciones.
    const lienzo = aLienzo(foto, escalaPara(foto.width, foto.height, ladoMaximo));
    const ctx = lienzo.getContext('2d');
    const { width: ancho, height: alto } = lienzo;

    const alturaFranja = Math.max(70, Math.round(alto * 0.12));
    const y0 = alto - alturaFranja;

    ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
    ctx.fillRect(0, y0, ancho, alturaFranja);

    const logoAlto = alturaFranja * 0.6;
    let x = 16;
    logos.forEach((logo) => {
        if (!logo) return;
        const w = logoAlto * (logo.width / logo.height);
        ctx.drawImage(logo, x, y0 + (alturaFranja - logoAlto) / 2, w, logoAlto);
        x += w + 14;
    });

    // El mismo instante que se estampa viaja en lastModified, para que el
    // servidor guarde exactamente la fecha que se lee en la foto.
    const momento = new Date();
    const hora = new Intl.DateTimeFormat('es-CO', { dateStyle: 'short', timeStyle: 'medium' }).format(momento);
    const nombreLugar = coords ? await esperarCiudad() : null;
    const lugar = coords
        ? [nombreLugar, `${coords.latitude.toFixed(6)}, ${coords.longitude.toFixed(6)}`].filter(Boolean).join(' · ')
        : 'Ubicación no disponible';

    // El texto va a la derecha y solo puede ocupar lo que dejan los logos. En
    // una foto angosta, o con un municipio de nombre largo, a su tamaño normal
    // se montaba encima de ellos: si no cabe, la letra se achica hasta caber.
    const margen = 16;
    const disponible = ancho - margen - (x + margen);

    const escribir = (texto, peso, proporcion, altura) => {
        let tamano = Math.round(alturaFranja * proporcion);
        ctx.font = `${peso} ${tamano}px sans-serif`;

        const medido = ctx.measureText(texto).width;

        if (medido > disponible && disponible > 0) {
            tamano = Math.max(8, Math.floor(tamano * disponible / medido));
            ctx.font = `${peso} ${tamano}px sans-serif`;
        }

        ctx.fillText(texto, ancho - margen, y0 + alturaFranja * altura);
    };

    ctx.textAlign = 'right';
    ctx.fillStyle = '#fff';
    escribir(hora, 'bold', 0.26, 0.45);
    escribir(lugar, 'normal', 0.22, 0.78);

    return aArchivo(lienzo, archivo, momento.getTime());
}
