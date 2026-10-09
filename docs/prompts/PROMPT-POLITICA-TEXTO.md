# Anexo: el texto real de la política ya está disponible

Sustituye lo que decía `PROMPT-DATOS-Y-HORARIOS.md` sobre dejar un marcador.
**El texto legal ya existe y está en el repositorio.** No redactes nada.

## Archivos que ya te dejé

| Archivo | Qué es |
|---|---|
| `database/data/politica-datos-v01.html` | El texto completo, convertido del PDF de jurídica a HTML semántico: 15 secciones, 106 párrafos, 94 ítems de lista |
| `database/seeders/PoliticaDatosSeeder.php` | Carga esa versión y la marca activa |

Datos del documento oficial:

- **Código:** PO-GJ-JUR-001
- **Versión:** 01
- **Fecha:** 01/08/2026
- **Responsable:** COMITÉ DE ESTUDIOS MÉDICOS S.A.S. — NIT 900.294.794-5
- **Canal habeas data:** datospersonales@comitedeestudiosmedicos.com
  y Calle 16AA Sur No. 42-91 Piso E (16), Medellín, Antioquia

## Ajustes al modelo

El seeder usa cuatro columnas que no estaban en el plan original. Agrégalas a la
migración de `politicas_datos`:

| Columna | Tipo | Para qué |
|---|---|---|
| `codigo` | string(30) nullable | «PO-GJ-JUR-001». Es como jurídica identifica el documento |
| `titulo` | string | Título oficial |
| `responsable` | string | Quién responde por el tratamiento |
| `canal_habeas_data` | string | Correo para consultas y reclamos |

`responsable` y `canal_habeas_data` se guardan **en la fila de la política**, no en
configuración: si mañana cambia el responsable, las autorizaciones viejas deben seguir
mostrando quién era el responsable cuando se dieron.

## Cómo mostrarlo

El contenido es HTML ya confiable —lo generé yo desde el PDF, no viene de un usuario—,
así que en la vista va con `{!! !!}`. Envuélvelo en un contenedor con estilos propios
para `h2`, `p`, `ul` y `ol`, porque el HTML no trae clases:

```blade
<article class="politica-texto">
    {!! $politica->contenido !!}
</article>
```

Requisitos de la página `/politica-de-datos`:

- Encabezado con código, versión y fecha de vigencia. Un documento legal sin esos tres
  datos no sirve como referencia.
- Índice de secciones al inicio, con enlaces internos. Son 15 secciones y 19 páginas;
  sin índice nadie lo lee.
- Legible en móvil y con estilos de impresión razonables — la gente imprime estas cosas.
- Los correos y teléfonos del texto, enlazados con `mailto:` y `tel:`.

## Verificación

- `php artisan db:seed --class=PoliticaDatosSeeder` deja una fila activa.
- `/politica-de-datos` abre sin sesión y muestra las 15 secciones completas.
- Correr el seeder dos veces no duplica la fila (usa `updateOrCreate` por versión).
- El resumen aparece junto a la casilla del formulario público, con enlace al texto
  completo que abre en pestaña nueva.

## Importante: revisa que el texto esté completo

Convertí un PDF de 19 páginas. La estructura quedó bien, pero **compara el HTML contra
el PDF original** antes de dar esto por cerrado, sobre todo:

- La sección **V. TRATAMIENTO Y FINALIDADES**, que es la más larga y la que más listas
  tiene.
- La sección **III. DEFINICIONES**.
- Que no se haya perdido ninguna tabla del original — la conversión a texto plano no
  conserva tablas, y si el PDF tenía alguna, se habrá aplanado.

Si encuentras algo mutilado, dímelo en vez de reescribirlo tú.
