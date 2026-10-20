/**
 * Un buscador encima de un <select data-buscable>: con cientos de carpetas,
 * recorrer la lista a ojo no sirve. Se escribe parte del nombre o de la ruta
 * y la lista se queda con lo que coincide (sin importar tildes ni mayúsculas).
 *
 * Se quitan y se ponen <option> de verdad, no se ocultan: en iPhone una
 * opción oculta sigue saliendo en el selector nativo.
 */

const normalizar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function iniciar(select) {
    const todas = [...select.options];

    if (todas.length < 8) {
        return; // Con pocas opciones, el buscador estorba.
    }

    // La primera opción sin valor («Raíz», «Selecciona…») se queda siempre.
    const fija = todas[0]?.value === '' ? todas[0] : null;

    const buscador = document.createElement('input');
    buscador.type = 'search';
    buscador.placeholder = 'Buscar carpeta por nombre o ruta…';
    buscador.setAttribute('aria-label', 'Buscar carpeta');
    buscador.className = 'liquid-input mb-1.5 w-full text-sm';

    const cuenta = document.createElement('p');
    cuenta.className = 'mt-1 hidden text-xs text-[var(--muted)]';

    select.before(buscador);
    select.after(cuenta);

    buscador.addEventListener('input', () => {
        const busqueda = normalizar(buscador.value.trim());
        const elegida = select.value;
        const coinciden = todas.filter((o) => o !== fija && normalizar(o.textContent).includes(busqueda));

        select.replaceChildren(...(fija ? [fija] : []), ...coinciden);

        // Si la elegida sigue en la lista se respeta; si no, la primera que coincide.
        if (coinciden.some((o) => o.value === elegida)) {
            select.value = elegida;
        } else if (busqueda && coinciden.length) {
            select.value = coinciden[0].value;
        }

        cuenta.textContent = busqueda
            ? (coinciden.length ? `${coinciden.length} carpeta(s) coinciden` : 'Ninguna carpeta coincide')
            : '';
        cuenta.classList.toggle('hidden', !busqueda);
    });

    // Enter en el buscador no envía el formulario.
    buscador.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            select.focus();
        }
    });
}

function iniciarTodos() {
    document.querySelectorAll('select[data-buscable]').forEach(iniciar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarTodos);
} else {
    iniciarTodos();
}
