# Tarea: tres mejoras al repositorio documental

Trabaja sobre el proyecto Laravel en esta carpeta. Léelo antes de escribir nada:
empieza por `INICIO.md`, luego `routes/web.php`, `app/Models/Documento.php` y
`resources/views/layouts/app.blade.php`. No inventes estructura: el proyecto ya
tiene convenciones y hay que respetarlas.

---

## Contexto del proyecto

Repositorio documental centralizado para la dependencia de Inclusión Social de la
Alcaldía de Medellín. Multitenant: la misma instalación sirve a varias dependencias
con datos aislados.

**Stack:** Laravel 12 · PHP 8.2 · Blade · Tailwind 4 vía Vite · MySQL (MariaDB de XAMPP)
· Windows.

**Convenciones que NO se cambian:**

- El dominio se nombra en español (`Documento`, `Carpeta`, `dependencia_id`). Sigue
  esa línea en todo lo nuevo.
- Autorización con Policies nativas de Laravel en `app/Policies/`. No instales
  `spatie/laravel-permission` ni ningún paquete de permisos.
- Tres roles en el enum `App\Enums\RolDependencia`: `lectura`, `edicion`,
  `administracion`. El rol vive en la tabla pivote `dependencia_usuario`.
- Ocultar cosas se hace con columnas booleanas (`activo`, `activa`), **nunca con
  SoftDeletes**. Es un requisito de auditoría del cliente.
- Aislamiento entre dependencias por Global Scope en
  `app/Models/Scopes/DependenciaScope.php`.
- Acciones relevantes se registran con el servicio `App\Services\Auditor`, usando el
  enum `App\Enums\AccionAuditoria`.
- Sin frameworks de JavaScript. No instales Alpine, Livewire, React ni Vue. JS
  vanilla, inline en la vista o en `resources/js/app.js`.
- No hay Breeze ni registro público. Los usuarios los crea un administrador.
- Autenticación propia en `app/Http/Controllers/Auth/SesionController.php`.

---

## Mejora 1 — Restringir los tipos de archivo a PDF e imágenes

### Objetivo

Hoy se aceptan 20 extensiones (Word, Excel, PowerPoint, ZIP…). Deben aceptarse
**solo PDF y formatos de fotografía**: `pdf`, `jpg`, `jpeg`, `png`, `webp`.

### Archivos a tocar

| Archivo | Qué hacer |
|---|---|
| `config/repositorio.php` | Reducir `extensiones_permitidas` a esas cinco |
| `app/Http/Requests/GuardarDocumentoRequest.php` | Endurecer la validación (ver abajo) |
| `app/Http/Requests/SubirVersionRequest.php` | Lo mismo |
| `resources/views/documentos/create.blade.php` | Texto de ayuda de la zona de carga + atributo `accept` en el input |
| `resources/views/documentos/show.blade.php` | Atributo `accept` en el formulario de nueva versión |

### Detalles técnicos

1. Los dos Form Requests ya construyen la regla `mimes:` desde el config. Mantén ese
   patrón — la lista se define en un solo lugar.

2. **Agrega además la regla `mimetypes:`.** `mimes:` valida por extensión, y renombrar
   un `.exe` a `.pdf` la pasa. `mimetypes:` inspecciona el contenido real:

   ```
   'mimetypes:application/pdf,image/jpeg,image/png,image/webp'
   ```

   Ponla como constante o entrada nueva en `config/repositorio.php`
   (`mimetypes_permitidos`), para no repetirla en los dos Requests.

3. **El input de archivo no tiene atributo `accept`.** Agrégalo, generado desde el
   config, para que el explorador de Windows filtre desde el principio:

   ```blade
   accept="{{ collect(config('repositorio.extensiones_permitidas'))->map(fn ($e) => '.'.$e)->join(',') }}"
   ```

   El `accept` es comodidad, no seguridad: la validación del servidor sigue siendo
   la que manda.

4. Actualiza el mensaje de error de `archivo.mimes` para que diga qué se acepta:
   «Solo se permiten archivos PDF e imágenes (JPG, PNG, WEBP).»

5. Revisa `Documento::esPrevisualizable()`. Ya cubre PDF e imágenes, así que ahora
   **todo** documento será previsualizable. Confirma que la vista previa de
   `documentos/show.blade.php` se ve bien para los cinco formatos.

### Criterios de aceptación

- Subir un `.docx` es rechazado con mensaje claro, no con error 500.
- Un `.exe` renombrado a `.pdf` es rechazado.
- Subir un `.pdf` y un `.jpg` funciona, y ambos se previsualizan en la ficha.
- La lista aparece en un solo lugar del código.

---

## Mejora 2 — Menú lateral izquierdo

### Objetivo

Hoy `resources/views/layouts/app.blade.php` tiene una barra superior oscura con la
navegación horizontal debajo. Conviértela en un **menú lateral fijo a la izquierda**.

### Qué debe conservar

Todo lo que ya hace la barra actual. No pierdas nada de esto:

- Marca del sistema con su ícono.
- **Selector de dependencia** cuando el usuario tiene más de una asignada (hoy es un
  `<select>` que envía un formulario `PUT` a `dependencia.cambiar`). Si solo tiene una,
  muestra el nombre.
- Buscador que envía `GET` a `documentos.index` con el parámetro `q`.
- Enlaces de navegación **con su control de rol**: «Documentos» siempre;
  «Usuarios», «Tipos de documento» y «Auditoría» solo si
  `$rolActual?->puedeAdministrar()`.
- Avatar con iniciales que lleva al perfil, y botón de salir (formulario `POST` a
  `logout`).
- El pie con la dependencia y el rol actual.

### Detalles técnicos

1. Las variables `$dependenciaActual`, `$dependenciasDisponibles` y `$rolActual` las
   comparte el middleware `App\Http\Middleware\EstablecerDependencia`. **No las
   recalcules en la vista.**

2. Estructura sugerida: sidebar fijo de unos `w-64`, contenido principal con el
   margen correspondiente. Usa `flex` o `grid`, no posicionamiento absoluto.

3. **Responsive es obligatorio.** Por debajo de `md` el sidebar se oculta y aparece
   un botón hamburguesa que lo despliega. JS vanilla, sin librerías. Cierra el menú
   al hacer clic fuera y con la tecla `Escape`.

4. El buscador se mueve al sidebar o a una barra superior delgada — decide tú, pero
   que siga siendo visible en todas las pantallas.

5. Marca el enlace activo con `request()->routeIs()`, como ya se hace hoy.

6. Accesibilidad: el botón hamburguesa necesita `aria-expanded` y `aria-controls`,
   y el sidebar un `aria-label`. Estado de foco visible en todos los enlaces.

### Criterios de aceptación

- Las 13 pantallas se ven bien con el sidebar, sin desbordes horizontales.
- En móvil el menú abre y cierra correctamente.
- Un usuario con rol `lectura` NO ve los enlaces de administración.
- Cambiar de dependencia sigue funcionando.

---

## Mejora 3 — Tema claro u oscuro elegido por el usuario

### Objetivo

Cada usuario elige cómo ver la interfaz: **claro**, **oscuro** o **según el sistema**.
La preferencia se guarda en su cuenta, así que lo acompaña en cualquier equipo donde
inicie sesión.

### Archivos a crear

- `app/Enums/TemaInterfaz.php` — casos `Claro`, `Oscuro`, `Sistema`, con método
  `etiqueta()`, siguiendo el estilo de `RolDependencia`.
- Migración `add_tema_to_users_table` — columna `string('tema')->default('sistema')`.

### Archivos a tocar

- `app/Models/User.php` — agregar `tema` a `$fillable` y castearlo al enum.
- `routes/web.php` — ruta `PUT perfil/tema`, nombre `perfil.tema`, dentro del grupo
  autenticado.
- `app/Http/Controllers/Auth/PerfilController.php` — método `actualizarTema`.
- `resources/views/perfil/edit.blade.php` — selector de las tres opciones.
- `resources/css/app.css` y `resources/views/layouts/app.blade.php` — lo de abajo.
- Las 17 vistas de `resources/views/` — variantes `dark:`.

### Detalles técnicos — lee esto con cuidado

1. **Tailwind 4 no activa el modo oscuro manual por defecto.** Solo responde a
   `prefers-color-scheme`. Para que un interruptor funcione hay que declararlo en
   `resources/css/app.css`, justo después del `@import`:

   ```css
   @import 'tailwindcss';

   @custom-variant dark (&:where(.dark, .dark *));
   ```

   Sin esa línea las clases `dark:` **no harán nada** cuando el usuario elija oscuro
   a mano. Es el error más fácil de cometer aquí.

2. La clase `dark` va en la etiqueta `<html>`.

3. **Evita el parpadeo de tema equivocado.** El navegador pinta antes de que corra
   cualquier JS al final del body, así que un script tardío produce un destello
   blanco al cargar. Pon un script **inline en el `<head>`, antes de cualquier CSS**,
   que lea la preferencia del usuario ya renderizada por Blade:

   ```blade
   <script>
     (function () {
       const tema = @json(auth()->user()?->tema?->value ?? 'sistema');
       const oscuro = tema === 'oscuro' ||
         (tema === 'sistema' && window.matchMedia('(prefers-color-scheme: dark)').matches);
       document.documentElement.classList.toggle('dark', oscuro);
     })();
   </script>
   ```

4. Con `sistema`, escucha también los cambios en vivo:
   `window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', …)`.

5. **Aplicar `dark:` a las 17 vistas es el grueso del trabajo.** Hazlo de forma
   sistemática, no a ojo. Las equivalencias del proyecto:

   | Claro | Oscuro |
   |---|---|
   | `bg-slate-100` (fondo) | `dark:bg-slate-900` |
   | `bg-white` (tarjetas) | `dark:bg-slate-800` |
   | `text-slate-900` | `dark:text-slate-100` |
   | `text-slate-600` / `text-slate-500` | `dark:text-slate-400` |
   | `ring-slate-200` / `border-slate-200` | `dark:ring-slate-700` / `dark:border-slate-700` |
   | `bg-slate-50` (encabezados de tabla) | `dark:bg-slate-800/50` |
   | `border-slate-300` (campos) | `dark:border-slate-600 dark:bg-slate-900` |

   Cuida los casos con color: las filas de documento inactivo (`bg-rose-50`), las
   etiquetas y las alertas de `partials/alertas.blade.php` necesitan su versión
   oscura y deben mantener el contraste legible. **No inviertas mecánicamente** —
   revisa que el texto siga leyéndose sobre cada fondo.

6. La pantalla de ingreso (`auth/login.blade.php`) no usa el layout principal.
   Trátala aparte, respetando `prefers-color-scheme` (no hay usuario todavía).

### Criterios de aceptación

- El usuario cambia el tema en su perfil y persiste al cerrar y volver a entrar.
- Con `sistema`, cambiar el tema de Windows cambia la app sin recargar.
- No hay parpadeo blanco al cargar en modo oscuro.
- Las 13 pantallas son legibles en ambos temas. Ninguna queda con texto de un tema
  sobre fondo del otro.
- El selector de tema es accesible por teclado.

---

## Orden de trabajo

Hazlo en tres tandas, no todo de una. Detente después de cada una para que pueda
revisar y probar antes de seguir.

1. **Mejora 1** — es pequeña y aislada. Empieza por ahí.
2. **Mejora 2** — el sidebar toca el layout, que es lo que la mejora 3 va a modificar
   otra vez. Hacerlo antes evita trabajo repetido.
3. **Mejora 3** — la más extensa, sobre un layout ya estabilizado.

## Verificación al terminar cada tanda

```
php artisan route:list
php artisan migrate
npm run build
```

Y prueba a mano entrando con los tres roles. Puedes crear usuarios de prueba desde
`/admin/usuarios` o con un seeder temporal.

**Nota sobre este entorno:** es Windows con XAMPP. `composer run dev` levanta
servidor, cola y Vite; Pail está excluido a propósito porque necesita la extensión
`pcntl`, que no existe en Windows. Para ver logs:

```
Get-Content storage\logs\laravel.log -Wait -Tail 20
```

## No hagas

- No instales paquetes de Composer ni de npm sin preguntarme primero.
- No cambies el modelo de datos más allá de la columna `tema`.
- No reemplaces la validación del servidor por validación del navegador.
- No reorganices carpetas ni renombres clases existentes.
- No agregues comentarios que solo repitan lo que dice el código.
