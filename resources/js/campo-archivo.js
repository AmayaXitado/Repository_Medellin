/**
 * Campo de archivo con vista previa, de uno o de varios.
 *
 * El <input type="file"> real siempre está en el HTML y nunca se quita: si
 * este script no llega a correr, el formulario se envía igual con el campo
 * nativo del navegador. Lo que se añade encima es la forma de elegir —zona
 * para soltar en escritorio, o botones de cámara y galería en el móvil—,
 * una tarjeta por archivo con su nombre editable, y la X para quitarlo.
 */

function pesoLegible(bytes) {
    const unidades = ['B', 'KB', 'MB', 'GB'];
    let cantidad = Math.max(0, bytes);
    let i = 0;

    while (cantidad >= 1024 && i < unidades.length - 1) {
        cantidad /= 1024;
        i++;
    }

    return (i === 0 ? cantidad : cantidad.toFixed(1).replace('.', ',')) + ' ' + unidades[i];
}

/** Dos archivos son el mismo si coinciden nombre, peso y fecha. */
function esElMismo(a, b) {
    return a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;
}

function sinExtension(nombre) {
    return nombre.replace(/\.[^/.]+$/, '');
}

/**
 * Copia el nombre del archivo al campo indicado, sin extensión. Solo con un
 * archivo y solo si está vacío: lo que ya escribió la persona manda.
 */
function autocompletar(campo, seleccion) {
    const destino = document.getElementById(campo.dataset.autocompletar || '');

    if (destino && !destino.value && seleccion.length === 1) {
        destino.value = seleccion[0].nombre;
    }
}

function iniciar(campo) {
    const entrada = campo.querySelector('[data-entrada]');
    const zona = campo.querySelector('[data-zona]');
    const selectores = campo.querySelector('[data-selectores]');
    const lista = campo.querySelector('[data-lista]');
    const plantilla = campo.querySelector('[data-plantilla]');
    const resumen = campo.querySelector('[data-resumen]');
    const aviso = campo.querySelector('[data-aviso]');

    // La zona y los botones son excluyentes, pero alguno tiene que haber.
    if (!entrada || !lista || !plantilla || (!zona && !selectores)) {
        return;
    }

    // Sin DataTransfer no se puede reescribir la lista del input, así que
    // tampoco se podría quitar un archivo suelto. Mejor dejar el campo
    // nativo tal cual que ofrecer una X que no funciona.
    if (typeof DataTransfer === 'undefined') {
        return;
    }

    const varios = entrada.multiple;
    const maximo = parseInt(campo.dataset.maximo || '0', 10) || Infinity;
    const formatos = entrada.getAttribute('accept');

    // 'hidden' y 'flex' o 'grid' son la misma propiedad: se alternan a la
    // vez y a mano, en vez de confiar en cuál gana en la hoja de estilos.
    const alternar = (elemento, visible, display = 'flex') => {
        if (!elemento) {
            return;
        }

        elemento.classList.toggle('hidden', !visible);
        elemento.classList.toggle(display, visible);
    };

    entrada.classList.add('sr-only');
    alternar(zona, true);
    alternar(selectores, true, 'grid');

    /**
     * La verdad de qué hay adjunto: { archivo, nombre }. El input y las
     * tarjetas se reconstruyen a partir de esto, nunca al revés. Guardar
     * aquí el nombre es lo que hace que no se pierda al quitar una tarjeta
     * y repintarse las demás.
     */
    let seleccion = [];
    let urls = [];

    const soltarUrls = () => {
        urls.forEach((url) => URL.revokeObjectURL(url));
        urls = [];
    };

    /** Vuelca la selección al input, que es lo que de verdad se envía. */
    const sincronizarEntrada = () => {
        const deposito = new DataTransfer();
        seleccion.forEach(({ archivo }) => deposito.items.add(archivo));
        entrada.files = deposito.files;
    };

    const avisar = (texto) => {
        if (!aviso) {
            return;
        }

        aviso.textContent = texto;
        aviso.classList.toggle('hidden', texto === '');
    };

    const tarjeta = (elemento, indice) => {
        const { archivo, nombre } = elemento;
        const nodo = plantilla.content.firstElementChild.cloneNode(true);
        const miniatura = nodo.querySelector('[data-miniatura]');
        const icono = nodo.querySelector('[data-icono]');
        const campoNombre = nodo.querySelector('[data-nombre-campo]');
        const textoArchivo = nodo.querySelector('[data-archivo]');
        const peso = nodo.querySelector('[data-peso]');

        if (campoNombre) {
            campoNombre.value = nombre;

            // Solo actualiza el modelo: repintar en cada tecla dejaría el
            // cursor saltando al final de la caja.
            campoNombre.addEventListener('input', () => {
                elemento.nombre = campoNombre.value;
            });
        }

        // Con nombre editable, el archivo original pasa a ser el subtítulo;
        // sin él, es el título de la tarjeta.
        textoArchivo.textContent = campoNombre
            ? archivo.name + ' · ' + pesoLegible(archivo.size)
            : archivo.name;

        if (peso) {
            peso.textContent = pesoLegible(archivo.size);
        }

        const esImagen = archivo.type.startsWith('image/');

        if (esImagen) {
            const url = URL.createObjectURL(archivo);
            urls.push(url);
            miniatura.src = url;
            miniatura.classList.remove('hidden');
        }

        // Un PDF no tiene miniatura que enseñar: va su icono en el hueco.
        icono.classList.toggle('hidden', esImagen);
        icono.classList.toggle('flex', !esImagen);

        nodo.querySelector('[data-quitar]').addEventListener('click', () => quitar(indice));

        return nodo;
    };

    const pintar = () => {
        soltarUrls();
        lista.replaceChildren();

        seleccion.forEach((elemento, indice) => lista.appendChild(tarjeta(elemento, indice)));

        alternar(lista, seleccion.length > 0);

        // Con varios, la forma de elegir se queda mientras quepan más. Con
        // uno solo la tarjeta la sustituye, que ya no hay nada que añadir.
        const cabenMas = varios && seleccion.length < maximo;

        alternar(zona, cabenMas || seleccion.length === 0);
        alternar(selectores, cabenMas || seleccion.length === 0, 'grid');

        if (resumen) {
            const total = seleccion.reduce((suma, { archivo }) => suma + archivo.size, 0);

            resumen.textContent = varios && seleccion.length > 0
                ? seleccion.length
                    + (maximo === Infinity ? '' : ' de ' + maximo)
                    + (seleccion.length === 1 && maximo === Infinity ? ' archivo · ' : ' archivos · ')
                    + pesoLegible(total)
                : '';
            resumen.classList.toggle('hidden', resumen.textContent === '');
        }
    };

    const quitar = (indice) => {
        seleccion.splice(indice, 1);
        sincronizarEntrada();
        avisar('');
        pintar();
    };

    const agregar = (nuevos) => {
        const entrantes = Array.from(nuevos).map((archivo) => ({
            archivo,
            nombre: sinExtension(archivo.name),
        }));

        if (entrantes.length === 0) {
            return;
        }

        if (!varios) {
            seleccion = [entrantes[0]];
            sincronizarEntrada();
            avisar('');
            pintar();
            autocompletar(campo, seleccion);

            return;
        }

        // Se suman a lo que ya había, sin repetir: elegir en dos tandas es
        // lo normal cuando los archivos están en carpetas distintas, o
        // cuando se toman varias fotos seguidas.
        const sinRepetir = entrantes.filter(
            (nuevo) => !seleccion.some((previo) => esElMismo(previo.archivo, nuevo.archivo)),
        );

        const hueco = Math.max(0, maximo - seleccion.length);
        const descartados = sinRepetir.length - hueco;

        seleccion = seleccion.concat(sinRepetir.slice(0, hueco));

        avisar(descartados > 0
            ? 'Solo se pueden enviar ' + maximo + ' archivos. ' + (descartados === 1 ? 'Se dejó 1 fuera.' : 'Se dejaron ' + descartados + ' fuera.')
            : '');

        sincronizarEntrada();
        pintar();
        autocompletar(campo, seleccion);
    };

    /** Abre el campo pidiendo cámara, o el selector de archivos de siempre. */
    const abrir = (conCamara) => {
        if (conCamara) {
            // 'environment' es la cámara trasera, la de fotografiar un papel.
            entrada.setAttribute('capture', 'environment');
            entrada.setAttribute('accept', 'image/*');
        } else {
            entrada.removeAttribute('capture');
            entrada.setAttribute('accept', formatos);
        }

        entrada.click();
    };

    campo.querySelector('[data-camara]')?.addEventListener('click', () => abrir(true));
    campo.querySelector('[data-galeria]')?.addEventListener('click', () => abrir(false));

    zona?.addEventListener('click', () => abrir(false));

    // El navegador ya reemplazó la lista del input por lo recién elegido:
    // se toma de ahí y se vuelve a componer con lo que hubiera antes.
    entrada.addEventListener('change', () => agregar(entrada.files));

    if (zona) {
        // El resaltado al arrastrar lo trae 'liquid-dropzone.active' del CSS
        // institucional: ya sabe verse bien en los dos temas.
        ['dragenter', 'dragover'].forEach((evento) =>
            zona.addEventListener(evento, (e) => {
                e.preventDefault();
                zona.classList.add('active');
            }),
        );

        ['dragleave', 'drop'].forEach((evento) =>
            zona.addEventListener(evento, (e) => {
                e.preventDefault();
                zona.classList.remove('active');
            }),
        );

        zona.addEventListener('drop', (e) => agregar(e.dataTransfer.files));
    }
}

function iniciarTodos() {
    document.querySelectorAll('[data-campo-archivo]').forEach(iniciar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
} else {
    iniciarTodos();
}
