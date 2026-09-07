{{--
    Overlay de carga compartido entre el login y el dashboard. Se muestra con
    JS (clase 'hidden' de Tailwind) al enviar un formulario o navegar a otra
    página; como aquí no hay SPA, desaparece solo cuando la página siguiente
    termina de cargar.
--}}
<div id="cargando-pagina" role="status" aria-live="polite" aria-label="Cargando"
     class="fixed inset-0 z-[999] hidden"
     style="background-color: color-mix(in srgb, var(--bg, #f3f5f7) 85%, transparent);">
    <span class="loader"></span>
</div>
