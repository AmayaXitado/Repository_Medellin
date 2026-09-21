# Prompt para Code: aprovisionamiento automático de usuarios hacia Authentik

## Contexto (dáselo a Code tal cual)

Esto es la **segunda fase** de la integración con Authentik, después de
`PROMPT-AUTHENTIK.md` (que ya cubre el login SSO: Documenta acepta
identidades de Authentik para usuarios que YA existen localmente).

Ahora se agrega el sentido contrario: cuando un administrador crea un
usuario **en Documenta**, ese usuario debe aparecer automáticamente en
Authentik también, sin que nadie tenga que crearlo a mano dos veces. La
persona recibe un link para definir su propia contraseña en Authentik
— Documenta nunca maneja ni fija esa contraseña.

Documenta sigue siendo la única fuente de verdad de "quién tiene
acceso y con qué rol/dependencia" (tabla `usuarios` +
`dependencia_usuario`). Authentik solo refleja identidades para poder
autenticarlas.

## 0. Requisitos previos en Authentik (esto lo haces tú, no Code)

1. **Crear una cuenta de servicio dedicada** para esta integración:
   `Directory → Users → New User → Service Account`, nombre sugerido
   `documenta-api`. Documenta no debe usar tu cuenta personal de
   administrador para esto.
2. **Generar su token de API**: `Directory → Tokens and App passwords →
   Create`, con `Intent = API Token` y `User = documenta-api`. Ese
   valor va al `.env` del VPS como `AUTHENTIK_API_TOKEN` — nunca a git.
3. **Darle solo los permisos que necesita** (crear/editar usuarios,
   crear invitaciones) — no lo dejes como admin global.
4. **Crear el flujo de enrolamiento** que van a usar las invitaciones
   (puedes usar el asistente en `Directory → Invitations`, que crea el
   flujo y la primera invitación juntos). Anota el **slug** del flujo
   resultante — lo vas a necesitar en el `.env`.

## 1. Variables de entorno nuevas

Sumar a las que ya existen de `PROMPT-AUTHENTIK.md`:

```
AUTHENTIK_API_TOKEN=
AUTHENTIK_ENROLLMENT_FLOW_SLUG=
```

## 2. Migración

Agregar una columna a `usuarios` para poder referenciar la identidad en
Authentik después (actualizarla, desactivarla, evitar duplicados):

```php
$table->string('authentik_id')->nullable()->after('identificador');
```

## 3. Servicio `AuthentikProvisioner`

Crear `app/Services/AuthentikProvisioner.php` con estos métodos:

- **`crear(Usuario $usuario): void`**
  - Si `$usuario->authentik_id` ya tiene valor, no hacer nada (evitar
    duplicados — este método debe ser idempotente).
  - `POST {AUTHENTIK_BASE_URL}/api/v3/core/users/` con
    `Authorization: Bearer {AUTHENTIK_API_TOKEN}`, body con al menos
    `username`, `email`, `name` e `is_active: true`. **Sin
    contraseña.**
  - Guardar el `pk`/`id` que responde Authentik en
    `usuario->authentik_id`.
  - `POST {AUTHENTIK_BASE_URL}/api/v3/stages/invitation/invitations/`
    referenciando el flujo de `AUTHENTIK_ENROLLMENT_FLOW_SLUG`, con
    los datos del usuario precargados (`fixed_data`) y marcada como
    uso único (`single_use`), con una expiración razonable (3–7 días).
  - Con el token de invitación que responde ese endpoint, armar el
    link: `{AUTHENTIK_BASE_URL}/if/flow/{flow-slug}/?itoken={uuid}` y
    enviarlo por el sistema de correo que ya usa Documenta (o, si se
    prefiere que Authentik lo mande directo, usar el endpoint de envío
    de la invitación — confirmar su ruta exacta contra el schema en el
    paso 5 antes de usarlo).

- **`inactivar(Usuario $usuario): void`**
  - Si no tiene `authentik_id`, no hacer nada.
  - `PATCH {AUTHENTIK_BASE_URL}/api/v3/core/users/{authentik_id}/` con
    `is_active: false`. Esto es tan importante como la creación: si
    alguien pierde el acceso en Documenta (columna `activo` en falso)
    pero su cuenta sigue viva en Authentik, queda con login SSO
    huérfano — un hueco de seguridad.

- **Manejo de errores en ambos métodos:** si la llamada a Authentik
  falla (red caída, token vencido, etc.), la operación local
  (crear/inactivar el usuario en Documenta) **debe completarse
  igual** — Authentik es un complemento, nunca debe bloquear la
  gestión de usuarios de la app. Registrar el fallo con el servicio
  `Auditor`/logs para que un administrador lo reintente a mano.

## 4. Dónde se dispara

Enganchar `crear()` e `inactivar()` en el flujo actual de
administración de usuarios de Documenta (donde hoy se crea/inactiva un
`Usuario`) — puede ser un Observer del modelo o una llamada explícita
en el controlador correspondiente, lo que mejor encaje con el código
existente. Confirmar con el equipo cuál de los dos patrones ya se usa
para casos similares en el proyecto antes de decidir.

## 5. Antes de escribir el código de las llamadas HTTP

Los nombres de campo de arriba (`username`, `fixed_data`,
`single_use`, etc.) son los que documenta Authentik, pero pueden variar
entre versiones. **Confirmar el esquema exacto contra la instancia
real** antes de dar por buena la integración:

```
{AUTHENTIK_BASE_URL}/api/v3/schema/
```

o la interfaz interactiva en `/api/v3/swagger-ui/`. No asumir los
campos a ciegas — validarlos ahí primero.

## 6. Seguridad

- Nunca generar, transmitir ni fijar una contraseña desde Laravel hacia
  Authentik — el usuario siempre la define él mismo por el link de
  invitación.
- `AUTHENTIK_API_TOKEN` solo en `.env` de cada entorno, nunca en el
  repositorio.
- El token de la cuenta de servicio debe tener el mínimo permiso
  necesario (usuarios + invitaciones), no acceso de administrador
  completo.

## 7. Pruebas

Usar `Http::fake()` para simular Authentik, sin llamarlo de verdad, y
cubrir al menos:

- Crear un usuario guarda su `authentik_id` cuando Authentik responde
  bien.
- Si Authentik falla, el usuario local se crea de todas formas y el
  fallo queda registrado.
- Llamar `crear()` dos veces sobre el mismo usuario no genera un
  duplicado en Authentik (idempotencia).
- Inactivar un usuario local dispara el `PATCH is_active: false`
  correspondiente en Authentik.
