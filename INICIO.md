# Repository-Medellin

Repositorio documental centralizado para la dependencia de **Inclusión Social**
(Alcaldía de Medellín), diseñado desde el inicio para replicarse a otras
dependencias como Salud Mental sin abrir la plataforma a toda la Alcaldía.

**Stack:** Laravel 12 · Blade · Tailwind 4 (Vite) · MySQL · almacenamiento local abstraído

---

## Puesta en marcha

### 1. Configurar el entorno

```bash
copy /Y .env.example .env
php artisan key:generate
```

Ajusta `DB_USERNAME` y `DB_PASSWORD` en `.env` según tu MySQL.

### 2. Crear la base de datos

```bash
mysql -u root -p -e "CREATE DATABASE repository_medellin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php -m | findstr pdo_mysql
```

Si `pdo_mysql` no aparece, descoméntalo en tu `php.ini` y reinicia.

### 3. Migrar, sembrar y compilar

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
```

El seeder crea la dependencia Inclusión Social, cuatro carpetas base,
los tipos de documento generales y el usuario inicial:

```
admin@menteplena.com.co / Cambiar2026
```

> Cambia esa contraseña antes de exponer la plataforma.

### 4. Levantar

```bash
composer run dev
```

http://localhost:8000

---

## Decisiones de arquitectura

Cada una responde a un punto explícito de la propuesta técnica.

### Inactivación en vez de borrado

No se usa `SoftDeletes`. Los documentos tienen una columna booleana `activo`:
cuando pasa a `false`, lectores y editores dejan de verlo, pero el registro
permanece íntegro en la base de datos y administración conserva la
trazabilidad y la opción de reactivarlo. Toda inactivación exige un motivo y
queda en la auditoría con autor, fecha e IP.

### Aislamiento entre dependencias

`app/Models/Scopes/DependenciaScope.php` es un **Global Scope** aplicado a
`Documento` y `Carpeta`. Aunque un controlador olvidara filtrar, un usuario de
Inclusión Social nunca vería documentos de Salud Mental: el filtro va en la
consulta SQL, no en la vista. El scope no actúa en consola ni en seeders, para
no romper el mantenimiento.

### UUID en las URLs

Documentos y carpetas se resuelven por `uuid`, no por `id` autoincremental.
Las URLs no son enumerables, pero las llaves foráneas siguen siendo enteros.

### Roles sin paquete externo

La propuesta sugería `spatie/laravel-permission`; para tres roles fijos
resultaba más maquinaria de la necesaria. El rol vive en la tabla pivote
`dependencia_usuario`, así que **una misma persona puede ser lectora en una
dependencia y administradora en otra**. La autorización se resuelve con
Policies nativas de Laravel.

| Rol | Puede |
|---|---|
| **Solo lectura** | Consultar y descargar documentos activos |
| **Edición** | Lo anterior + crear carpetas, subir documentos y versiones |
| **Administración** | Lo anterior + inactivar/reactivar, gestionar usuarios y ver auditoría |

### Versionado

Subir un archivo nuevo **nunca sobrescribe** el anterior: crea una versión con
su propio archivo físico, su autor y un comentario de qué cambió. El historial
completo queda consultable y descargable. Esto es lo que ataca directamente el
problema de pérdida de información que motiva el proyecto.

### Almacenamiento

Los archivos viven fuera del directorio público y **jamás se sirven por URL
directa**: cada descarga y cada previsualización pasa por un controlador que
valida permisos y registra la acción. Migrar a S3 o MinIO es cambiar `disco`
en `config/repositorio.php`.

### Sin registro público

No se instaló Breeze. En una plataforma institucional los usuarios los crea un
administrador, no se auto-registran, y no hay recuperación de contraseña por
correo. La autenticación es propia y mínima: login, logout y cambio de
contraseña propia.

---

## Mapa del código

```
app/
├── Enums/
│   ├── RolDependencia.php          Los 3 roles y qué puede cada uno
│   └── AccionAuditoria.php         Catálogo de acciones auditables
├── Models/
│   ├── Dependencia.php             La unidad multitenant
│   ├── Carpeta.php                 Árbol de carpetas (auto-referenciado)
│   ├── Documento.php               Metadatos; el archivo vive en las versiones
│   ├── DocumentoVersion.php        Cada carga física, nunca se sobrescribe
│   ├── TipoDocumento.php           Catálogo, global o por dependencia
│   ├── Etiqueta.php                Etiquetas de búsqueda libre
│   ├── Auditoria.php               Quién hizo qué y cuándo
│   └── Scopes/DependenciaScope.php Aislamiento multitenant
├── Policies/                       Quién puede ver, editar e inactivar
├── Services/
│   ├── ContextoDependencia.php     Dependencia activa de la petición
│   ├── AlmacenamientoDocumentos.php Única puerta a los archivos físicos
│   └── Auditor.php                 Registro de auditoría
└── Http/
    ├── Middleware/                 Dependencia activa · usuario activo
    ├── Requests/                   Validación de entrada
    └── Controllers/                Explorador, versiones, descargas, admin
```

---

## Fronteras del software

Deliberadamente **fuera de alcance**, para mantenerlo ligero: edición en línea
de documentos, chats internos, firmas digitales y flujos de aprobación.

---

## Fase 2: agregar una dependencia

El seeder ya deja creada Salud Mental, inactiva. Para habilitarla:

1. Marcar `activa = true` en la tabla `dependencias`.
2. Crear su usuario administrador y asignarle el rol en esa dependencia.
3. Ese administrador da acceso a su propio equipo desde la interfaz.

No se toca código. Los usuarios con varias dependencias asignadas cambian
entre ellas con el selector del encabezado.

---

## Pendientes antes de producción

- [ ] Cambiar la contraseña del usuario inicial
- [ ] `APP_DEBUG=false` y `APP_ENV=production`
- [ ] Ajustar `upload_max_filesize` y `post_max_size` en `php.ini` para que
      acompañen a `tamano_maximo_kb` (25 MB por defecto)
- [ ] Respaldos de la base de datos y de `storage/app/private/documentos`
- [ ] Pruebas automatizadas de las Policies (es donde vive la seguridad)
