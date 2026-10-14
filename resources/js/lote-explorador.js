/**
 * Copiar o mover varios a la vez en el explorador. Los checks de carpetas y
 * documentos apuntan al formulario #form-lote; aquí solo se enseña la barra
 * cuando hay algo marcado, se cuenta, y se marca o desmarca todo.
 *
 * Mover pide confirmación: cambia dónde está todo lo marcado y lo verán así
 * los demás. Copiar no, porque no le quita nada a nadie.
 */
function iniciar(barra) {
    const items = () => document.querySelectorAll('[data-lote-item]');
    const cuenta = barra.querySelector('[data-lote-cuenta]');
    const todos = document.querySelector('[data-lote-todos]');

    const actualizar = () => {
        const marcados = Array.from(items()).filter((item) => item.checked).length;
        const documentos = Array.from(document.querySelectorAll('[data-lote-documento]'));

        cuenta.textContent = String(marcados);
        barra.hidden = marcados === 0;

        if (todos) {
            const marcadosDocs = documentos.filter((d) => d.checked).length;
            todos.checked = documentos.length > 0 && marcadosDocs === documentos.length;
            todos.indeterminate = marcadosDocs > 0 && marcadosDocs < documentos.length;
        }
    };

    document.addEventListener('change', (evento) => {
        if (evento.target.matches('[data-lote-item]')) {
            actualizar();
        }
    });

    todos?.addEventListener('change', () => {
        document.querySelectorAll('[data-lote-documento]').forEach((d) => {
            d.checked = todos.checked;
        });
        actualizar();
    });

    barra.querySelector('[data-lote-limpiar]')?.addEventListener('click', () => {
        items().forEach((item) => {
            item.checked = false;
        });
        actualizar();
    });

    barra.addEventListener('submit', (evento) => {
        const boton = evento.submitter;

        if (boton?.matches('[data-lote-mover]')) {
            const destino = barra.querySelector('select[name="destino"]');
            const nombre = destino.selectedOptions[0]?.textContent.trim() ?? 'la carpeta elegida';

            if (!window.confirm(`¿Mover ${cuenta.textContent} a «${nombre}»? Dejarán de estar donde están ahora.`)) {
                evento.preventDefault();
            }
        }
    });

    // Al volver con «atrás», el navegador puede recordar los checks marcados.
    actualizar();
}

const barra = document.querySelector('[data-lote-barra]');

if (barra) {
    iniciar(barra);
}
