# Tarea: suite de pruebas automatizadas

Escribe las pruebas del repositorio documental. Antes de tocar nada, lee `INICIO.md`,
`routes/web.php`, `app/Policies/` y `app/Models/Documento.php`.

El objetivo no es "cubrir código". Es **demostrar que ciertas cosas son imposibles**:
que un lector no pueda subir, que una dependencia no pueda ver la otra, que una
actualización no destruya la versión anterior. Si una prueba no puede fallar por un bug
real, no la escribas.

---

## Contexto

Laravel 12 · PHP 8.2 · PHPUnit 11 · MySQL en producción, Windows con XAMPP en
desarrollo. Ya existen `phpunit.xml` y la carpeta `tests/`. No hay Pest — usa PHPUnit,
que es lo que el proyecto trae.

Sistema multitenant: cada documento pertenece a una dependencia, y tres roles
(`lectura`, `edicion`, `administracion`) definidos en `App\Enums\RolDependencia`. El rol
vive en la tabla pivote `dependencia_usuario`, así que **la misma persona puede tener
roles distintos en dependencias distintas**.

---

## Antes de escribir pruebas: lo que falta

### 1. Base de datos de pruebas

Revisa `phpunit.xml`. Si trae `DB_CONNECTION=sqlite` con `:memory:`, úsalo: las pruebas
corren en segundos y no tocan MySQL.

Verifica que las 13 migraciones corran en SQLite. Si alguna falla —lo más probable es
la columna `json` de `auditorias` o alguna llave foránea— avísame antes de cambiar el
esquema. La alternativa es una base MySQL aparte llamada `repository_medellin_test`.

Usa el trait `RefreshDatabase` en todas las pruebas que toquen la base.

### 2. Factories

Solo existe `UserFactory`. Crea las que faltan en `database/factories/`:

`DependenciaFactory`, `CarpetaFactory`, `DocumentoFactory`, `DocumentoVersionFactory`,
`TipoDocumentoFactory`, `EtiquetaFactory`.

Que sean coherentes con el modelo: un `Documento` necesita `dependencia_id`, y su
`uuid` lo genera el propio modelo en `creating` — no lo pongas en la factory.

Agrega estados útiles: `DocumentoFactory::inactivo()`, `CarpetaFactory::raiz()`.

### 3. Un helper para crear usuarios con rol

Se va a repetir en cada prueba. Ponlo en `tests/TestCase.php`:

```php
protected function usuarioCon(RolDependencia $rol, ?Dependencia $dependencia = null): User
```

Que cree el usuario, la dependencia si no se pasa, y los enlace con ese rol en la pivote.

---

## El detalle que más te va a costar

`app/Models/Scopes/DependenciaScope.php` es un Global Scope que filtra por
`dependencia_id`. **Lee la dependencia activa del servicio
`App\Services\ContextoDependencia`, que llena el middleware `EstablecerDependencia`
durante la petición HTTP.**

Consecuencia directa para tus pruebas:

- En pruebas de **feature** (`$this->actingAs($usuario)->get('/documentos')`) el
  middleware corre y el scope filtra. Todo normal.
- En pruebas **unitarias**, creando modelos con factories sin pasar por HTTP, el
  contexto está vacío y **el scope no filtra nada**. `Documento::count()` devuelve los
  de todas las dependencias.

Eso no es un bug: es deliberado, para que seeders y comandos de consola funcionen. Pero
si escribes una prueba unitaria esperando aislamiento, va a fallar y vas a perseguir un
fantasma.

**Escribe una prueba que documente explícitamente ese comportamiento**, para que quien
venga después lo entienda en vez de descubrirlo a golpes.

---

## Qué probar, en orden de importancia

### Prioridad 1 — Permisos (`tests/Feature/PermisosTest.php`)

Aquí vive la seguridad del sistema. Es lo primero.

Para cada uno de los tres roles, verifica qué puede y qué no:

| Acción | lectura | edicion | administracion |
|---|---|---|---|
| Ver el explorador | sí | sí | sí |
| Descargar un documento activo | sí | sí | sí |
| Subir un documento | **403** | sí | sí |
| Crear una carpeta | **403** | sí | sí |
| Editar metadatos | **403** | sí | sí |
| Subir una versión nueva | **403** | sí | sí |
| Inactivar un documento | **403** | **403** | sí |
| Reactivar | **403** | **403** | sí |
| Ver `/admin/usuarios` | **403** | **403** | sí |
| Ver `/auditoria` | **403** | **403** | sí |

Además:

- Un documento **inactivo** no aparece en el listado para lectura ni edición, y
  `GET /documentos/{uuid}` devuelve 403 para ellos. Administración sí lo ve.
- Un usuario **sin sesión** es redirigido a `/ingresar` en todas las rutas protegidas.
- Un documento inactivo **no se puede editar**, ni siquiera por administración
  (revisa `DocumentoPolicy::update`: exige `$documento->activo`). Confirma que ese es
  el comportamiento deseado.

### Prioridad 2 — Aislamiento entre dependencias (`tests/Feature/AislamientoTest.php`)

La otra garantía crítica.

- Un usuario de la dependencia A **no ve** en el listado los documentos de B.
- `GET /documentos/{uuid}` de un documento de B devuelve **403 o 404**, nunca el
  contenido. Prueba con un usuario que sea administrador en A: el rol alto no debe
  abrirle la puerta a otra dependencia.
- Lo mismo con descargar, previsualizar, editar e inactivar.
- Las carpetas de B no aparecen en el selector al subir un documento en A.
- Un usuario con acceso a A **y** a B ve unos u otros según la dependencia activa, y
  cambiarla con `PUT /dependencia/{slug}` cambia lo que ve.
- Intentar cambiar a una dependencia **no asignada** devuelve 403.

### Prioridad 3 — Versionado (`tests/Feature/VersionadoTest.php`)

Es la razón de ser del proyecto: no perder información.

- Subir un documento crea **una** versión, número 1.
- Subir una versión nueva crea la número 2 y **la versión 1 sigue existiendo**, con su
  archivo físico intacto en disco. Esta es la prueba más importante de todo el proyecto.
- `versionActual` devuelve siempre la de número mayor.
- Descargar la versión 1 entrega el archivo de la versión 1, no el de la 2.
- Cada versión guarda su propio `hash`, `tamano` y `subido_por`.
- No pueden existir dos versiones con el mismo número para un documento (índice único).

Usa `Storage::fake(config('repositorio.disco'))` en todas: las pruebas no deben escribir
archivos reales.

### Prioridad 4 — Validación de archivos (`tests/Feature/CargaArchivosTest.php`)

- Un `.pdf` y un `.jpg` se aceptan.
- Un `.docx` o un `.exe` se rechazan con error de validación, no con 500.
- Un archivo que supera `tamano_maximo_kb` se rechaza.
- Subir **sin** archivo al crear un documento falla; al editar metadatos, no es
  obligatorio.
- El archivo se guarda bajo `documentos/{dependencia_id}/{documento_id}/` y **nunca** en
  el disco público.

### Prioridad 5 — Inactivación y auditoría (`tests/Feature/InactivacionTest.php`)

- Inactivar **exige** un motivo: sin él, error de validación.
- Al inactivar se llenan `activo=false`, `motivo_inactivacion`, `inactivado_at` e
  `inactivado_por`.
- **El registro sigue en la base de datos.** Verifícalo con
  `assertDatabaseHas('documentos', ['id' => ..., 'activo' => false])`. Esto es lo que
  distingue inactivar de borrar, y es un requisito del cliente.
- Reactivar limpia esos tres campos.
- Cada acción deja una fila en `auditorias` con el usuario, la acción y la IP.
- Descargar también se audita.

### Prioridad 6 — Autenticación (`tests/Feature/AutenticacionTest.php`)

- Credenciales correctas entran; incorrectas devuelven error sin decir cuál campo falló.
- Un usuario con `activo = false` **no puede** iniciar sesión.
- Un usuario desactivado **mientras tiene sesión abierta** es expulsado en su siguiente
  petición (middleware `VerificarUsuarioActivo`).
- **No existe ruta de registro público.** `POST /register` debe dar 404.
- El límite de 6 intentos por minuto funciona.
- Cambiar la contraseña exige la contraseña actual.

### Prioridad 7 — Unitarias (`tests/Unit/`)

Rápidas, sin base de datos donde se pueda:

- `RolDependencia`: `puedeEditar()` y `puedeAdministrar()` para los tres casos.
- `User::rolEn()` devuelve `null` para una dependencia ajena, y
  `Administracion` si `es_superadmin`.
- `Carpeta::ruta()` arma las migas correctamente y no entra en bucle infinito si
  alguien crea un ciclo a mano en la base.
- `Carpeta::esAncestroDe()`.
- `Etiqueta::resolverDesdeTexto()`: separa por comas, ignora vacíos, no duplica
  «Actas» y «actas», y respeta la dependencia.
- `DocumentoVersion::getTamanoLegibleAttribute()` con 0, 1023, 1024 y varios MB.

---

## Intenta romperlo

Yo escribí este código, así que no confíes en él. Estos son los puntos donde sospecho
que puede fallar. Escribe una prueba para cada uno y **dime cuáles fallan** en vez de
ajustar la prueba para que pase:

1. **`UsuarioController@store` usa `firstOrNew` por correo.** Si das acceso a alguien
   que ya existe en otra dependencia, ¿le sobrescribe el nombre y el estado `activo`?
   ¿Debería?

2. **`Documento::visiblesPara()` consulta el contexto de dependencia.** Con el contexto
   vacío, ¿qué devuelve? Comprueba que sea lo conservador (solo activos) y no lo
   contrario.

3. **`CarpetaController@update` impide mover una carpeta dentro de su propia
   descendencia.** Intenta burlarlo con un árbol de tres o cuatro niveles.

4. **La previsualización sirve contenido del usuario en el navegador.** Sube un SVG o un
   HTML renombrado a `.jpg` y verifica que el `Content-Type` y el
   `Content-Security-Policy` de `DescargaController::previsualizar` impidan que se
   ejecute como script.

5. **Los `uuid` en las URLs.** Confirma que ninguna ruta acepta el `id` numérico como
   alternativa.

6. **Borrar un usuario** que subió documentos: ¿los documentos sobreviven con
   `creado_por` en `null`, o se van en cascada?

---

## Cómo correrlas

```
php artisan test
php artisan test --filter=PermisosTest
```

En Windows con XAMPP, si `php artisan test` no encuentra la base, revisa `phpunit.xml`
antes de tocar `.env` — el archivo de pruebas debe traer su propia configuración.

---

## Criterios de aceptación

- `php artisan test` pasa en verde, o falla **solo** en las pruebas de la sección
  «Intenta romperlo» que hayan encontrado un bug real. En ese caso repórtamelo, no lo
  arregles por tu cuenta.
- Ninguna prueba escribe archivos reales en `storage/`.
- Ninguna prueba depende del orden de ejecución ni de datos de otra.
- Los nombres de las pruebas dicen qué garantizan, en español:
  `un_lector_no_puede_subir_documentos`, no `test_store_403`.

## No hagas

- No instales Pest ni ningún paquete sin preguntarme.
- No cambies código de la aplicación para que una prueba pase. Si encuentras un bug,
  repórtalo.
- No escribas pruebas de getters, setters ni de que Laravel funcione.
- No apuntes las pruebas a la base de datos de desarrollo.
