# Tarea: recepción externa de documentos con bandeja de entrada

Nueva funcionalidad para el repositorio documental. Lee primero `INICIO.md`,
`routes/web.php`, `app/Models/Documento.php`, `app/Services/AlmacenamientoDocumentos.php`
y `app/Policies/DocumentoPolicy.php`. Respeta las convenciones que ya existen; están
descritas en `INICIO.md` y en `PROMPT-MEJORAS.md`.

---

## El problema

Hoy la información llega por enlaces de OneDrive. Quien la envía es gente externa que
**no debe tener acceso al repositorio**. Quien la recibe sí: son seis funcionarios
designados.

Se quiere eliminar OneDrive del circuito. Los remitentes deben poder subir directamente
al sistema, sin cuenta, y que lo enviado caiga en la bandeja del funcionario que
corresponde, ya identificado y auditado desde el primer segundo.

## La solución

**Enlaces de carga con identidad.** Un administrador genera un enlace por remitente. El
enlace es una URL larga e inadivinable que:

- identifica **quién** puede subir por él (nombre, correo y entidad del remitente),
- define **a la bandeja de quién** llega lo que suba,
- puede expirar y puede revocarse en cualquier momento.

El remitente abre la URL, ve un formulario mínimo, sube su archivo y no ve nada más del
sistema. El destinatario encuentra el archivo en su bandeja, lo revisa y lo archiva en
la carpeta que corresponde — momento en el cual, y solo entonces, se convierte en un
documento del repositorio.

## Decisión de diseño que debes respetar

**Lo que llega NO es un documento todavía.** Va a una tabla `recepciones`, aparte de
`documentos`.

No lo cambies por "crear el documento directamente con `activo = false`". La columna
`activo` ya significa otra cosa en este sistema: *retirado por administración, con
motivo y responsable*. Reutilizarla para *recién llegado, sin clasificar* mezclaría dos
estados distintos y arruinaría la auditoría, que es el requisito central del proyecto.

---

## Modelo de datos

### Tabla `enlaces_carga`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `token_hash` | char(64) único | `hash('sha256', $token)`. Es por donde se busca |
| `token_cifrado` | text | El token, con cast `encrypted`. Para volver a mostrar el enlace |
| `dependencia_id` | FK | Aislamiento |
| `destinatario_id` | FK users | A qué bandeja llega |
| `remitente_nombre` | string | Quién es la persona externa |
| `remitente_email` | string nullable | Para notificarle |
| `remitente_entidad` | string nullable | Empresa, contratista, otra dependencia |
| `proposito` | string nullable | «Actas del comité 2026» |
| `activo` | boolean | Revocación inmediata |
| `expira_at` | timestamp nullable | Vencimiento |
| `max_usos` | int nullable | `null` = sin límite |
| `usos` | int default 0 | Contador |
| `creado_por` | FK users | |
| timestamps | | |

### Tabla `recepciones`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `uuid` | uuid único | Para la URL, como en `documentos` |
| `dependencia_id` | FK | |
| `enlace_carga_id` | FK nullable | `nullOnDelete`: la recepción sobrevive al enlace |
| `destinatario_id` | FK users | Dueño de la bandeja. Reasignable |
| `remitente_nombre` | string | **Copiado** del enlace al momento de recibir |
| `remitente_email` | string nullable | También copiado |
| `nombre_original` | string | |
| `ruta` | string | Fuera del directorio público |
| `mime` · `extension` · `tamano` | | |
| `hash` | char(64) | SHA-256 |
| `mensaje` | text nullable | Nota opcional del remitente |
| `estado` | string | `pendiente`, `archivado`, `descartado` |
| `documento_id` | FK nullable | Se llena al archivar |
| `clasificado_por` · `clasificado_at` | | |
| `motivo_descarte` | string nullable | Obligatorio al descartar |
| `ip_remitente` · `agente` | | Cadena de custodia |
| timestamps | | |

Índices: `(destinatario_id, estado)` — es la consulta de la bandeja, la más frecuente.
Y `(dependencia_id, estado)`.

**Copia el nombre y correo del remitente en la recepción**, no confíes solo en la
llave foránea. Si el enlace se borra, la recepción debe seguir diciendo quién mandó qué.
Eso es cadena de custodia, no redundancia por descuido.

### Generación del token — hazlo exactamente así

El token **no es un identificador, es un secreto**: quien lo tenga puede subir en
nombre de ese remitente.

```php
public static function generarToken(): string
{
    return Str::random(48);   // usa random_bytes(), criptográficamente seguro
}

public static function hashDe(string $token): string
{
    return hash('sha256', $token);
}

public static function porToken(string $token): ?self
{
    return static::withoutGlobalScope(DependenciaScope::class)
        ->where('token_hash', static::hashDe($token))
        ->first();
}

public function url(): string
{
    return route('envio.formulario', ['token' => $this->token_cifrado]);
}
```

Reglas que no se negocian:

- **Nunca** `rand()`, `mt_rand()` ni `uniqid()` para generar el token: son predecibles.
- **Guarda dos columnas**, no una. El hash permite buscar por índice; el cifrado permite
  que el administrador vuelva a copiar el enlace cuando el remitente lo pierda. Guardar
  el token en claro significa que cualquiera con acceso a un respaldo de la base puede
  suplantar a cualquier remitente.
- **SHA-256, no bcrypt.** `Hash::make()` usa una sal distinta cada vez, así que no se
  puede consultar con `WHERE`, y es lento a propósito. Eso tiene sentido para
  contraseñas, que son cortas y adivinables; para un token de 286 bits de entropía no
  hay nada que ralentizar. SHA-256 es determinista y consultable por índice.
- **Restringe el formato en la ruta** para descartar basura antes de llegar a la base:

  ```php
  ->where('token', '[A-Za-z0-9]{48}')
  ```

- **Verifica `APP_URL` en producción.** `route()` construye la URL desde ahí. Si queda
  en `http://localhost` generarás enlaces inservibles, y si queda sin `https` el token
  viaja en claro por WhatsApp y correo.

### Modelos

`EnlaceCarga` y `Recepcion` en `app/Models/`. Ambos con el Global Scope
`DependenciaScope`, salvo `EnlaceCarga` cuando se resuelve por token en la ruta pública
—ahí no hay sesión ni dependencia en contexto, así que esa consulta debe usar
`withoutGlobalScope(DependenciaScope::class)` explícitamente.

Enum `App\Enums\EstadoRecepcion` con los tres casos y su `etiqueta()`.

Agrega a `AccionAuditoria`: `recepcion.recibida`, `recepcion.archivada`,
`recepcion.descartada`, `recepcion.reasignada`, `enlace.creado`, `enlace.revocado`.

---

## Rutas

### Públicas — sin autenticación

```
GET  /enviar/{token}    EnvioPublicoController@formulario
POST /enviar/{token}    EnvioPublicoController@recibir
```

Van **fuera** de los grupos `auth` y `dependencia`. Necesitan su propio middleware
`throttle`.

### Internas — bandeja del usuario

```
GET   /bandeja                      BandejaController@index
GET   /bandeja/{recepcion}          BandejaController@show
GET   /bandeja/{recepcion}/archivo  BandejaController@previsualizar
POST  /bandeja/{recepcion}/archivar BandejaController@archivar
PATCH /bandeja/{recepcion}/descartar BandejaController@descartar
PATCH /bandeja/{recepcion}/reasignar BandejaController@reasignar
```

### Administración — gestión de enlaces

```
GET    /admin/enlaces             EnlaceCargaController@index
GET    /admin/enlaces/nuevo       EnlaceCargaController@create
POST   /admin/enlaces             EnlaceCargaController@store
PATCH  /admin/enlaces/{enlace}/revocar EnlaceCargaController@revocar
```

---

## Seguridad — requisitos, no sugerencias

Este endpoint público es la superficie más expuesta del sistema. Trátalo así.

1. **Validación del token.** Búscalo por `token`, y rechaza si: no existe, `activo` es
   falso, `expira_at` ya pasó, o `usos >= max_usos`. **En todos esos casos devuelve la
   misma pantalla genérica** («Este enlace no está disponible»). No distingas entre
   inexistente y vencido: eso permitiría enumerar tokens válidos.

2. **Límite de tasa.** `throttle` por IP en el formulario y, en la carga, un límite por
   token además del de IP. Un enlace legítimo sube unos pocos archivos al día, no
   cientos.

3. **Validación de archivos idéntica a la interna**, reutilizando
   `config/repositorio.php`: `mimes:` y `mimetypes:` juntos, y el límite de tamaño.
   No dupliques la lista.

4. **Nombre en disco generado por el sistema**, nunca el del remitente. Guarda el
   original solo como dato en la base. Un nombre de archivo externo puede traer rutas
   (`../`), caracteres de control o extensiones dobles.

5. **Almacenamiento fuera del directorio público**, bajo `recepciones/{dependencia_id}/`.
   El archivo recibido **no se sirve nunca** por URL pública. Solo el destinatario lo ve,
   por la ruta interna, con verificación de permisos.

6. **Antivirus.** Todo archivo que entra por la vía pública debe escanearse antes de
   quedar disponible. Implementa un servicio `App\Services\EscanerArchivos` con una
   interfaz clara y **dos implementaciones**: una que use ClamAV vía `clamdscan`, y una
   nula para desarrollo, seleccionable por configuración. Si el escaneo falla o no está
   disponible, la recepción queda en estado `pendiente` pero marcada como no verificada,
   y la bandeja lo advierte. No la descartes en silencio ni la des por buena.

   Si ClamAV no se puede instalar en el servidor, dímelo: es una decisión de
   infraestructura, no de código.

7. **Auditoría completa.** Cada recepción registra IP, agente y token usado. Registra
   también los intentos rechazados por token inválido o vencido.

8. **CSRF** en el formulario público — Laravel lo hace solo, pero verifica que la ruta
   no quede excluida.

9. **Sin información del sistema en la pantalla pública.** No muestres el nombre del
   destinatario, la dependencia, ni nada de la estructura interna. Solo el propósito del
   enlace y el formulario.

---

## Flujo de archivado

Es la operación central. Cuando el destinatario archiva una recepción:

1. Pide los metadatos que faltan: nombre, carpeta, tipo de documento, fecha y etiquetas.
   Reutiliza el parcial `resources/views/documentos/_formulario.blade.php` — ya tiene
   todos esos campos.
2. Crea el `Documento` en la dependencia y carpeta elegidas.
3. Crea su **versión 1** a partir del archivo recibido. **Mueve** el archivo de
   `recepciones/` a `documentos/{dependencia_id}/{documento_id}/`, no lo dupliques.
4. Marca la recepción como `archivado`, con `documento_id`, `clasificado_por` y
   `clasificado_at`.
5. Audita la acción.

Todo dentro de una transacción. Si algo falla, el archivo no debe quedar movido a medias
ni la recepción marcada como archivada sin documento.

Reutiliza `AlmacenamientoDocumentos`. Si necesita un método nuevo para adoptar un archivo
ya presente en disco en vez de un `UploadedFile`, agrégalo ahí, no en el controlador.

**Descartar** exige motivo, cambia el estado a `descartado` y conserva el archivo
—no lo borres— por si fue un error.

---

## Permisos

- **Cada quien ve solo su propia bandeja.** `/bandeja` lista únicamente las recepciones
  donde `destinatario_id` es el usuario autenticado.
- **Administración ve todas** las de su dependencia, y puede reasignar.
- Archivar exige rol de **edición** como mínimo: crea un documento en el repositorio.
- Solo **administración** crea y revoca enlaces de carga.
- Crea una `RecepcionPolicy` en `app/Policies/` con `view`, `archivar`, `descartar` y
  `reasignar`. No metas la lógica en el controlador.

---

## Interfaz

- **Formulario público** (`resources/views/publico/enviar.blade.php`): página propia, sin
  el layout de la app. Solo el propósito del enlace, el campo de archivo con arrastrar y
  soltar, un campo de mensaje opcional y el botón. Pantalla de confirmación clara al
  terminar, y una pantalla genérica para enlace no disponible.
- **Bandeja** (`resources/views/bandeja/index.blade.php`): lista de pendientes con
  remitente, archivo, tamaño, fecha y estado del escaneo. Contador de pendientes visible
  en la navegación principal — es lo que hace que la gente la revise.
- **Ficha de recepción**: vista previa del archivo, datos del remitente, y las tres
  acciones (archivar, descartar, reasignar).
- **Administración de enlaces**: tabla con remitente, destinatario, usos, vencimiento y
  estado. Al crear un enlace, muestra la URL completa **una sola vez**, con un botón de
  copiar.

---

## Orden de trabajo

Detente después de cada punto para que revise.

1. Migraciones, modelos, enum y factories. Sin interfaz todavía.
2. Ruta pública con toda la validación de seguridad, y su formulario. Pruébala con un
   token generado a mano.
3. Bandeja: listar, ver, previsualizar.
4. Archivar y descartar, con la transacción y el movimiento de archivo.
5. Administración de enlaces.
6. Escáner de archivos.

## Verificación

Escribe pruebas de esto, siguiendo lo que ya hay en `tests/`:

- Un token inválido, revocado o vencido devuelve la misma respuesta genérica.
- Un `.exe` renombrado a `.pdf` se rechaza en la ruta pública.
- El usuario A no ve la bandeja del usuario B, ni entrando por URL directa.
- Archivar crea el documento con su versión 1 y deja la recepción en `archivado`.
- Si la creación del documento falla, el archivo no se movió y la recepción sigue
  `pendiente`.
- Una recepción de la dependencia A no es visible desde la B.

## No hagas

- No integres la API de Microsoft Graph ni OneDrive. Se decidió sacarlo del circuito.
- No instales paquetes sin preguntarme.
- No permitas que el remitente elija carpeta, tipo ni destinatario. Eso lo decide quien
  clasifica.
- No sirvas archivos recibidos por URL pública bajo ninguna circunstancia.
