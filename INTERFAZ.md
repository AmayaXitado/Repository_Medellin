# Interfaz: responsivo, avatar y cabecera

Lo que se cambió en la capa visual, por qué, y cómo comprobarlo sin abrir el
navegador a ojo. Complementa a [`INICIO.md`](INICIO.md); lo de las fotos con hora
y lugar está en [`GEOLOCALIZACION.md`](GEOLOCALIZACION.md).

---

## El scroll horizontal que no se veía

**Síntoma:** en la ficha del documento aparecía una barra de scroll horizontal, en
teléfono y en escritorio. Parecía culpa de las fichas de datos, porque es donde hay
textos largos (correo, huella SHA-256, nombre del archivo).

**Causa real:** el `<input type="file">` que el script esconde. Al inicializarse, el
campo de archivos le añade `sr-only`, que lo vuelve `position: absolute` y de 1 px.
Pero el input traía `w-full` del HTML —el que necesita cuando el script no corre— y
esa clase le ganaba en el ancho. Quedaba un input **invisible de 375 px, desplazado
31 px a la derecha**, empujando la página entera. El `clip` de `sr-only` lo oculta a
la vista, pero no lo saca del cálculo del scroll.

```js
// resources/js/campo-archivo.js
entrada.classList.remove('w-full');
entrada.classList.add('sr-only');
```

Afectaba a toda pantalla con campo de archivos: subir documento, publicar versión y
el formulario público de envío.

| Ancho | Antes | Después |
|---|---|---|
| 390 (teléfono) | 421 | 390 |
| 768 (tablet) | — | 753 = viewport |
| 1280 (escritorio) | — | 1265 = viewport |

**Regla que queda:** cuando escondas un control con `sr-only`, quítale antes
cualquier clase de ancho. Las dos van en la misma capa de Tailwind y gana la última
que se haya generado, no la que tú creas.

## Fichas de datos que se salían

Dos problemas distintos que se veían igual, los dos resueltos en
`resources/css/app.css` y no fila por fila, porque afectan a las tres fichas
—documento, quién lo envió, enlace— y a cualquiera que se agregue después.

```css
dl dt { flex-shrink: 0; overflow-wrap: normal; }   /* la etiqueta no se parte */
dl dd { min-width: 0; overflow-wrap: anywhere; }   /* el valor sí se acomoda */

@media (width < 40rem) {
    dl > div { flex-direction: column; align-items: stretch; gap: .125rem; }
    dl dd.text-right { text-align: left; }
}
```

- **`min-width: 0`** es la mitad que casi siempre falta: sin ella, un hijo de flex o
  de grid se niega a encogerse por debajo de su contenido, y el corte de palabra no
  llega a aplicarse nunca. Es lo mismo que faltaba en las dos columnas del grid de la
  ficha (`documentos/show.blade.php`), donde la tabla de versiones estiraba la
  columna en vez de usar su propio scroll.
- **La etiqueta nunca se parte.** Antes salía «Cor / reo». Se queda entera y no cede
  ancho; quien se acomoda es el valor.
- **Por debajo de 640 px la fila pasa a bloque**, etiqueta encima y valor debajo a
  todo lo ancho. En dos columnas un correo largo queda en tiras contra el borde.
- El selector es `dl dd.text-right` y no `dl dd`: `text-right` es una clase de
  Tailwind, y a una clase solo la gana otra clase.

## Avatar por rol

`resources/views/components/avatar.blade.php`. Sustituye las iniciales del nombre
(«AD») por un icono del rol que la persona tiene **en la dependencia activa**.

| Rol | Icono | Color |
|---|---|---|
| Solo lectura | ojo | gris (`--muted`) |
| Edición | lápiz | azul institucional (`--primary`) |
| Coordinación | personas | verde (`--success`) |
| Administración | escudo | ámbar (`--warning`) |

Por qué el rol y no la persona: en esta aplicación lo que cambia lo que ves son los
permisos, y «AD» no le dice nada a nadie. El color nunca va solo —cada rol tiene
forma distinta y texto alternativo—, para quien no distingue colores.

Van dibujados en SVG y no como archivos en `public/images/avatars/`: heredan el color
del tema, se ven nítidos en cualquier pantalla y no cuestan una petición más. Si
algún día hay foto de perfil por persona, va delante y el icono del rol queda de
respaldo.

```blade
<x-avatar :rol="$rolActual" />              {{-- md, por defecto --}}
<x-avatar :rol="$rolActual" tamano="sm" />  {{-- sm · md · lg --}}
```

Sin rol —un superadmin que todavía no está parado en ninguna dependencia— sale el
contorno de persona de siempre, que no promete permisos que no se sabe si hay.

## Cabecera y menú lateral

- **Arriba solo el avatar y la flecha.** El nombre completo y el rol ocupaban media
  cabecera y se truncaban igual («Administrador del repos…»).
- **El nombre, el rol y la dependencia pasaron al desplegable**, con el avatar en
  grande. Sigue siendo un `<details>` y no un menú montado con JavaScript: se abre
  igual si el script falla, que para llegar a «cerrar sesión» no es un lujo.
- **El pie del menú lateral quedó centrado** y se compone de dos fuentes distintas:
  `APP_NAME` (el producto) y la dependencia activa.

```blade
{{ config('app.name') }}@if($dependenciaActual) - {{ $dependenciaActual->nombre }}@endif
```

Antes la dependencia estaba escrita dentro de `APP_NAME`, así que la línea decía
«Documenta Inclusión Social · Inclusión Social» y no cambiaba al cambiar de
dependencia. Ahora `APP_NAME="Documenta"` y el resto sale de la sesión.

> Efecto colateral: el título de la pantalla de ingreso partía `APP_NAME` en dos para
> pintar la segunda palabra en el color de acento. Con una sola palabra ese acento ya
> no aparece.

## El tema, fuera del `<script>`

El valor del tema se interpolaba dentro del script del `<head>`:

```blade
let tema = @json(auth()->user()?->tema?->value ?? 'sistema');   {{-- antes --}}
```

El editor lo marcaba como error de sintaxis («Decorators are not valid here»), porque
analiza el contenido de `<script>` como JavaScript y `@json(...)` no lo es. Ahora
viaja en un atributo y el bloque es JavaScript válido tal cual, que es además como el
resto del proyecto le pasa datos al JS (`data-maximo`, `data-copiar`):

```blade
<html ... data-tema="{{ auth()->user()?->tema?->value ?? 'sistema' }}">
```
```js
let tema = raiz.dataset.tema || 'sistema';
```

Sigue resolviéndose antes de pintar, así que no hay destello blanco al cargar en modo
oscuro. Lo cubre `MenuLateralTemporalTest`.

---

## Cómo medir el desbordamiento sin ojo clínico

Adivinar cuál elemento saca el scroll cuesta más que medirlo. Con Chrome sin interfaz
y el protocolo de DevTools se sabe en segundos, y Node 22 ya trae `WebSocket`, así que
no hace falta instalar nada.

```bash
chrome.exe --headless=new --remote-debugging-port=9222 --user-data-dir=<temporal> about:blank
```

Después, contra `http://127.0.0.1:9222/json/list`, basta con evaluar esto en la página:

```js
const d = document.documentElement;
({
  desborda: d.scrollWidth > d.clientWidth,
  culpables: [...document.querySelectorAll('*')]
    .filter(e => e.getBoundingClientRect().right > d.clientWidth + 1)
    .map(e => `${e.tagName}.${e.className}`),
});
```

Dos advertencias aprendidas midiendo esto:

- **Descarta los falsos positivos.** Lo que esté dentro de un contenedor con
  `overflow-x-auto` aparece como que se sale, y no es cierto: hace scroll dentro de su
  tarjeta. La tabla de versiones es el caso típico.
- **Confirma el culpable escondiéndolo.** `elemento.style.display = 'none'` y vuelve a
  leer `scrollWidth`: si baja al ancho del viewport, ese era. Así se encontró el input
  invisible.

Para renderizar una pantalla que exige sesión sin inventarse credenciales, lo más
corto es una prueba temporal con `actingAs(...)` que vuelque el HTML a `public/`, con
un `<base href>` apuntando al servidor de desarrollo para que el CSS y el JS carguen.
Se borra al terminar.
