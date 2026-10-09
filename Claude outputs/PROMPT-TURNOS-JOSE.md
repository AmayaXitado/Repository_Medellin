# Prompt para Code: módulo de turnos — parte de José (vía pública)

## Contexto

En el repo están `PLAN.md`, `TAREAS-JOSE.md` y `TAREAS-EDWAR.md`. **Léelos
primero, en ese orden.** `PLAN.md` es el contrato común entre los dos
desarrolladores: el modelo de datos (§3), el reparto de archivos (§4), el
contrato de métodos entre José y Edwar (§5) y las decisiones que siguen
abiertas (§7).

Yo soy **José**. Vas a desarrollar **únicamente lo que está en
`TAREAS-JOSE.md`**: la vía pública, es decir todo lo que ve la persona de
campo sin iniciar sesión, a la que se llega por los enlaces de QR. Lo de
`TAREAS-EDWAR.md` **no se toca**, ni siquiera para "arreglar algo de paso".

Rama: `josedev`. Los PR van a `develop`.

## Reglas duras (de esto depende que no choquemos)

**Cada archivo tiene un dueño** (`PLAN.md` §4). Los míos:

- `routes/turnos-publico.php`
- `app/Services/Turnos/Marcador.php` e `IdentificadorColaborador.php`
- `app/Http/Controllers/Publico/*` y `EnvioPublicoController.php`
- `resources/views/publico/*` y `resources/views/components/campos/*`
- `resources/js/*`
- `app/Providers/AppServiceProvider.php` (solo los límites de la vía pública)
- `tests/Feature/Turnos/Publico*`

**No son míos, no los edites:** las migraciones, los modelos
(`Componente`, `Nodo`, `Colaborador`, `EnlaceTurno`, `Marcacion`),
`app/Models/Concerns/TieneTokenSecreto.php`, `app/Enums/AccionAuditoria.php`,
`bootstrap/app.php`, `routes/turnos-admin.php`, todo `app/Http/Controllers/Admin/`,
las policies, `resources/views/admin/*`, `resources/views/components/sidebar.blade.php`
y `tests/Feature/Turnos/Admin*`.

Si para avanzar necesitas una columna nueva, un método en un modelo o una
acción de auditoría que no existe: **párate y dímelo**, con el nombre exacto
que necesitas y para qué. Yo se lo pido a Edwar. No lo agregues tú.

Si al correr la suite falla un test que no es mío, tampoco lo arregles:
repórtamelo.

## Antes de empezar

La fase 0 (migraciones, modelos, trait, seeder, rutas registradas, acciones
de auditoría) la entrega Edwar y es mi punto de partida. **Revisa si ya está
mezclada en `develop`.**

- Si ya está: arranca por la fase 1a.
- Si todavía no: puedes adelantar las vistas y el JS de la fase 1a con datos
  de prueba, pero avísame que estás trabajando sin la base.

## Fase 1a — identificarse por cédula

Es una pieza reutilizable: la usan tanto el enlace de turno como el de
evidencias, así que va aparte y no incrustada en una vista.

- `app/Services/Turnos/IdentificadorColaborador.php`:
  - `buscar(int $dependenciaId, string $cc): ?Colaborador` — normaliza la
    cédula (usa `Colaborador::porDocumento()`, que ya normaliza con
    `User::normalizarDocumento()`; no escribas tu propia normalización).
  - `registrar(int $dependenciaId, array $datos): Colaborador` — con
    `origen = autoregistro` y auditoría `colaborador.registrado`.
- Componente Blade `<x-campos.cedula>`: campo con `inputmode="numeric"`, que
  al salir del campo consulta si la cédula existe; si existe muestra
  «Hola, Jos…»; si no, despliega `<x-campos.registro-colaborador>`.
- `<x-campos.registro-colaborador :componente>`: nombre, correo, teléfono,
  entidad, cargo y nodo.
- `<x-campos.selector-nodo :componente>`: reemplaza el `range(1, 6)` fijo que
  hay hoy, leyendo los nodos reales del componente.
- Endpoint `POST /t/{token}/cedula`.
- Recordar la cédula en el teléfono con `localStorage`, todo dentro de
  `try/catch`, con un botón «No soy yo» que la borre.

**La privacidad aquí no es opcional** (`PLAN.md` §2). El enlace es
compartido: cualquiera puede escribir cédulas al azar. El endpoint devuelve
**solo** `{existe: bool, saludo: "Jos…"}`. Nunca el correo, el teléfono ni el
nombre completo, ni siquiera en un campo que la vista no pinte. Y lleva
`throttle` por IP y por token.

## Fase 2a — enlace de turno: entrada y salida con el mismo link

`app/Services/Turnos/Marcador.php`, con las firmas exactas de `PLAN.md` §5,
porque Edwar ya está programando contra ellas:

```php
Marcador::marcar(Colaborador $c, array $datos): Marcacion
Marcador::registrarManual(Colaborador $c, string $tipo, CarbonInterface $cuando, string $motivo, User $autor): Marcacion
Marcador::cerrarAbiertas(): int
Marcador::estadoDe(Colaborador $c): ?Marcacion
```

Reglas de `marcar()`:

- **El servidor decide** si es entrada o salida: si hay una entrada abierta,
  lo siguiente es su salida. El botón no lo decide.
- La hora que vale es la del servidor (`marcada_at`). La del teléfono se
  guarda aparte como referencia (`declarada_at`).
- Una segunda marca en menos de 2 minutos se ignora.
- Calcula y **congela** `fuera_de_zona`, `fuera_de_turno` y `sin_ubicacion`
  en el momento de marcar. Son el juicio de ese instante: no se recalculan
  después aunque cambie la configuración del componente.
- Escribe en la auditoría (`turno.entrada`, `turno.salida`,
  `turno.cierre_automatico`, `turno.correccion`).
- Nunca edita ni borra una marcación. Cada marca es una fila nueva.
- **Sin señal se marca igual**, con `sin_ubicacion = true`. La falta de
  ubicación nunca bloquea el registro.

Rutas, en `routes/turnos-publico.php`:

- `GET /t/{token}` — pantalla de la cédula
- `POST /t/{token}/cedula` — la consulta
- `POST /t/{token}/marcar` — registrar la marca

Pantalla de marcar: **un solo botón grande**, que según `estadoDe()` dice
«Registrar entrada» o «Registrar salida · llevas 5 h 20 min». Pide ubicación
y foto según lo que diga `Componente::config()` (`foto_obligatoria`,
`ubicacion_obligatoria`, `tolerancia_min`, `horas_max_turno`).

La foto reutiliza `foto-georreferencial.js` y `campo-archivo`, se guarda
como documento en una carpeta del componente y su id va en
`marcaciones.documento_id`. Las coordenadas van **en columnas**
(`lat`, `lng`, `precision_m`, `municipio`), no solo dibujadas sobre la foto.

El `throttle` de `/t/*` se declara en `AppServiceProvider`, igual que el de
`envio-carga`.

## Fase 3a — el enlace de evidencias pide solo la cédula

- En `publico/enviar.blade.php`, reemplazar el bloque «Quién envía» (nombre,
  correo, entidad, nodo) por `<x-campos.cedula>`.
- En `EnvioPublicoController::recibir()`: resolver el colaborador por cédula,
  llenar `recepciones.colaborador_id` y **copiar** sus datos a los campos
  `remitente_*` y `nodo`. Esa copia es a propósito: la ficha del documento y
  la cadena de custodia tienen que seguir funcionando exactamente igual que
  hoy. Si la cédula es nueva, el colaborador se registra con los datos del
  formulario **en la misma transacción**.
- Los tests actuales de `EnvioPublicoTest` se **adaptan, no se borran**.

## Tests (`tests/Feature/Turnos/Publico*`)

- La primera vez pide los datos; la segunda, con la misma cédula, no.
- La consulta de cédula nunca devuelve correo ni nombre completo, y se
  bloquea tras N intentos.
- Entrada → salida → entrada alterna bien, y un doble toque no crea dos
  entradas.
- Un turno de las 22:00 a las 06:00 cierra con la salida correcta.
- Sin ubicación se marca igual, con `sin_ubicacion = true`.
- Un enlace revocado o vencido no marca; un colaborador inactivo no se
  reconoce.
- El envío de evidencias con una cédula conocida guarda `colaborador_id` y
  los `remitente_*` copiados.

## Dos principios que no se negocian

1. **Calle es un dato, no código.** Ningún `if ($componente === 'calle')` en
   ninguna parte. Lo que cambia entre componentes sale de
   `componentes.config`, porque después entran Básica y Estabilización sin
   refactorizar nada.
2. **Las personas de campo no son usuarios de Documenta.** No inician sesión,
   viven en `colaboradores` y se identifican por cédula. `users` sigue siendo
   solo para quien entra al sistema.

## Cómo quiero que entregues

- **Una fase a la vez**, con su PR a `develop`. No me traigas 1a, 2a y 3a
  juntas.
- Al empezar cada fase, dime primero qué archivos vas a tocar, para
  confirmar que todos son míos antes de que escribas código.
- Al terminar cada fase, la suite completa tiene que pasar, no solo tus
  tests nuevos.
- Las decisiones abiertas de `PLAN.md` §7 (los campos definitivos del
  formulario de Forms, si la foto es obligatoria en entrada y salida o solo
  en entrada, el PIN de 4 dígitos) **no las resuelvas tú**. Donde te topes
  con una, usa lo que ya está en el plan, déjalo saliendo de
  `componentes.config` si aplica, y márcamela para que yo decida.
- Ten presente al construir que la cámara y la geolocalización del navegador
  exigen HTTPS: funcionan en `localhost`, pero no si pruebas entrando por la
  IP del equipo en la red local. Si te topas con eso, avísame en vez de
  desactivar la validación.
