# Prompt para Code: aceptar archivos JFIF

## Contexto

Documenta limita hoy los tipos de archivo permitidos a PDF e imágenes. Los
archivos `.jfif` están quedando rechazados y hay que aceptarlos. Un `.jfif`
es un JPEG: es el mismo formato, solo con otra extensión, que Windows y
Chrome usan a veces al guardar imágenes. Su MIME real es `image/jpeg`.

## Qué hacer

**1. Encontrar todos los puntos donde se define la lista de tipos
permitidos.** Casi siempre hay más de uno, y por eso este tipo de cambio
suele quedar a medias. Revisar al menos:

- La regla de validación en el FormRequest o el controlador
  (`mimes:`, `mimetypes:` o `extensions:`).
- Un archivo de configuración, si la lista vive ahí (por ejemplo
  `config/repositorio.php`).
- El atributo `accept` del input de archivo en las vistas Blade.
- Cualquier validación del lado del cliente en JavaScript.
- El formulario público de carga por token (la bandeja de recepciones),
  que es un camino distinto al de los usuarios internos y tiene su propia
  validación.

**2. Antes de cambiar nada, identificar cuál de esas capas es la que está
rechazando el archivo, y decírmelo.** Importa la diferencia: si la
validación usa `mimes:`, Laravel compara contra la extensión deducida del
contenido del archivo y no contra el nombre, así que un `.jfif` ya debería
pasar por ahí. Pero si en algún punto se compara
`getClientOriginalExtension()` contra una lista blanca, ese es el que lo
bloquea y es donde hay que agregar `jfif`.

**3. Agregar el soporte en todas las capas que correspondan.** En el
`accept` del input, `.jfif` junto con `image/jpeg`.

**4. Revisar la descarga y la vista previa.** Si el archivo se sirve con el
Content-Type deducido de la extensión, un `.jfif` puede terminar
entregándose como `application/octet-stream` y descargándose en vez de
mostrarse. Asegurar que se sirva como `image/jpeg`.

**5. No convertir ni renombrar el archivo original.** Se guarda tal como
llegó, igual que todos los demás.

## Pruebas

- Un test que suba un `.jfif` y confirme que se acepta:
  `UploadedFile::fake()->create('foto.jfif', 500, 'image/jpeg')`.
- Un test que confirme que un tipo no permitido (por ejemplo `.exe`) sigue
  siendo rechazado, para asegurar que al abrir `jfif` no se aflojó la
  restricción general.
- Correr la suite completa.

## Nota

Si encuentras otras extensiones que son el mismo formato y están en el
mismo caso (`.jpe`, `.jfi`, `.jif`), dime cuáles son antes de agregarlas.
Prefiero decidir yo la lista y no que crezca sola.
