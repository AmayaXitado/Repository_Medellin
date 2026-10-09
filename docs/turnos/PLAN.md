# Plan: módulo de turnos por componente

> **Estado: planificación.** Nada de esto está construido.
> El reparto del trabajo está en [`TAREAS-EDWAR.md`](TAREAS-EDWAR.md) y
> [`TAREAS-JOSE.md`](TAREAS-JOSE.md). Este documento es el **contrato común**: si algo
> de aquí cambia, se avisa a los dos.

**En una frase:** las personas de campo abren un enlace compartido (QR) y escriben su
cédula. La primera vez llenan sus datos; desde ahí, solo la cédula. Con ese mismo
enlace marcan entrada y salida. Con la misma cédula, sus envíos de evidencias quedan
identificados sin volver a llenar nada. Todo va atado a un **componente**: Calle hoy,
Básica y Estabilización después, sin refactorizar.

---

## 1. Principios

1. **Calle es un dato, no código.** Ningún `if ($componente === 'calle')`. Lo que cambia
   entre componentes vive en `componentes.config`.
2. **Las personas de campo no son usuarios de Documenta.** No inician sesión. Van en
   `colaboradores` y se identifican por cédula. `users` sigue siendo solo para quien
   entra al sistema.
3. **Los datos se llenan una sola vez.** La cédula es la llave. Si ya está registrada,
   no se pide nada más, ni para marcar turno ni para subir evidencias.
4. **Las marcaciones son inmutables.** Cada entrada o salida es una fila nueva. Una
   corrección es otra fila, con motivo y autor. Nunca se edita ni se borra.
5. **El servidor decide entrada o salida.** El botón no lo decide: si hay una entrada
   abierta, lo siguiente es su salida.
6. **No se bloquea por falta de señal.** Sin ubicación se marca igual, y queda como
   novedad.

## 2. Flujo de la persona de campo

```
Abre el QR del componente ──► escribe su cédula
        │
        ├─ cédula nueva ──► formulario corto, UNA vez (nombre, correo, nodo…) ─┐
        │                                                                     │
        └─ cédula conocida ──► «Hola, Jos…»  ◄────────────────────────────────┘
                 │
                 ├─ sin entrada abierta ──► [Registrar entrada]  (ubicación + foto)
                 └─ con entrada abierta ──► [Registrar salida · llevas 5 h 20 min]
```

En el **enlace de evidencias** pasa lo mismo: cédula, y si ya existe, se pasa directo
a adjuntar archivos. Nombre, correo, entidad y nodo salen de `colaboradores`.

### Privacidad: obligatorio, no opcional

El enlace es compartido. Cualquiera que lo tenga puede escribir cédulas al azar, así
que **el servidor nunca devuelve datos personales al navegador**:
- Al reconocer una cédula solo se muestra el primer nombre recortado («Hola, Jos…»).
  Nunca el correo, el teléfono ni el nombre completo.
- La consulta por cédula tiene límite de intentos por IP y por enlace (`throttle`).
- La cédula y los datos viajan en el envío y se resuelven en el servidor.

Suplantación (alguien marca con la cédula de otra persona): la defensa práctica es la
foto con sello de hora y ubicación. Si no alcanza, el paso siguiente es un PIN de 4
dígitos por persona (decisión abierta, sección 7).

## 3. Modelo de datos

```
dependencias ─┬─ componentes ─┬─ nodos
              │               ├─ enlaces_turno   (QR compartido por componente o nodo)
              │               └─ colaboradores   (personas de campo, llave: cédula)
              │                        │
              │                        ├─ marcaciones  (inmutable)
              │                        └─ recepciones  (+ colaborador_id)
              └─ enlaces_carga (+ componente_id)
```

### `componentes`
`id`, `dependencia_id`, `nombre` («Calle»), `slug` (`calle`), `activo`, `config` (JSON):

```json
{
  "foto_obligatoria": true,
  "ubicacion_obligatoria": false,
  "tolerancia_min": 15,
  "horas_max_turno": 14,
  "autoregistro": true
}
```

### `nodos`
`id`, `componente_id`, `nombre` («Nodo 1»), `orden`, `lat`, `lng`, `radio_m`
(los tres últimos opcionales, para marcar *fuera de zona*), `activo`.

### `colaboradores`
| Columna | Notas |
|---|---|
| `dependencia_id` | Aislamiento |
| `documento` | Cédula **normalizada** con `User::normalizarDocumento()`. Única por dependencia |
| `nombre`, `correo`, `telefono`, `entidad`, `cargo` | Se llenan una vez |
| `componente_id`, `nodo_id` | Componente y nodo habituales. Se pueden cambiar al marcar |
| `origen` | `autoregistro` (por el enlace) o `admin` |
| `verificado_at`, `verificado_por` | Coordinación confirma los autorregistros |
| `activo` | Inactivo = el enlace no lo reconoce |

### `enlaces_turno`
`id`, `dependencia_id`, `componente_id`, `nodo_id` (opcional: QR de un nodo concreto),
`token_hash`, `token_cifrado`, `nombre`, `activo`, `expira_at`, `creado_por`.
Comparte la lógica del token con `enlaces_carga` mediante el trait `TieneTokenSecreto`.

### `marcaciones` (inmutable)
| Columna | Notas |
|---|---|
| `dependencia_id`, `colaborador_id`, `componente_id`, `nodo_id`, `enlace_turno_id` | |
| `tipo` | `entrada` / `salida` |
| `marcada_at` | Hora del **servidor**, la que vale |
| `declarada_at` | Hora del teléfono, solo como referencia |
| `lat`, `lng`, `precision_m`, `municipio` | Nulos si no hubo señal |
| `documento_id` | La foto, guardada como documento del repositorio |
| `origen` | `enlace` / `manual` / `cierre_automatico` |
| `motivo`, `registrada_por` (user_id) | Solo `manual` |
| `fuera_de_zona`, `fuera_de_turno`, `sin_ubicacion` | Juicio congelado al marcar |
| `ip`, `agente` | Cadena de custodia |

### Cambios a tablas existentes
- `recepciones.colaborador_id` (nullable). Las columnas `remitente_*` se siguen llenando,
  copiadas del colaborador, para que todo lo que ya existe siga funcionando.
- `enlaces_carga.componente_id` (nullable): de qué componente son los nodos que ofrece.
- `recepciones.nodo` sigue siendo un número **en la fase 0**. Pasarlo a `nodo_id` es una
  migración aparte, más adelante.

## 4. Reparto de archivos: así no hay conflictos

La regla es simple: **cada archivo tiene un dueño.** Si necesitas cambiar un archivo
que no es tuyo, se lo pides a su dueño o lo acuerdan por chat antes.

| Archivo o carpeta | Dueño |
|---|---|
| `database/migrations/*turnos*`, `*colaboradores*`, `*componentes*`, `*nodos*` | **Edwar** (fase 0) |
| `app/Models/{Componente,Nodo,Colaborador,EnlaceTurno,Marcacion}.php` | **Edwar** (fase 0) |
| `app/Models/Concerns/TieneTokenSecreto.php` | **Edwar** (fase 0) |
| `app/Enums/AccionAuditoria.php` | **Edwar**: agrega **todas** las acciones en la fase 0 |
| `routes/turnos-admin.php` | **Edwar** |
| `routes/turnos-publico.php` | **José** |
| `bootstrap/app.php` (registrar los dos archivos de rutas) | **Edwar**, una vez, en la fase 0 |
| `app/Http/Controllers/Admin/Turnos/*`, `app/Policies/*Turno*`, `*Colaborador*` | **Edwar** |
| `resources/views/admin/turnos/*`, `resources/views/components/sidebar.blade.php` | **Edwar** |
| `app/Services/Turnos/Marcador.php`, `IdentificadorColaborador.php` | **José** |
| `app/Http/Controllers/Publico/*`, `EnvioPublicoController.php` | **José** |
| `resources/views/publico/*`, `resources/views/components/campos/*` | **José** |
| `resources/js/*` | **José** |
| `app/Providers/AppServiceProvider.php` (límites de la vía pública) | **José** |
| `tests/Feature/Turnos/Admin*` | **Edwar** |
| `tests/Feature/Turnos/Publico*` | **José** |
| `docs/turnos/*` | Los dos, avisando |

Las ramas siguen siendo `eduDev` y `josedev`, y el destino de los PR es `develop`.

## 5. Contrato entre los dos (lo que uno le promete al otro)

**Edwar entrega en la fase 0, y José arranca encima** (✅ entregado):
- Migraciones y modelos de la sección 3, con relaciones y factories
  (`Componente`, `Nodo`, `Colaborador`, `EnlaceTurno`, `Marcacion`).
- `Colaborador::porDocumento(int $dependenciaId, string $cc): ?Colaborador`. Normaliza la
  cédula y aparta el scope, porque en la vía pública la dependencia la da el enlace.
- `Colaborador::saludo(): string`, que da «Jos…». **Es lo único que la vía pública puede
  mostrar** de una persona.
- `Componente::regla(string $clave, mixed $defecto = null)`. Lee `config` y, si falta la
  clave, cae en `Componente::REGLAS`. (Se llama `regla` y no `config` porque `config` es
  el nombre de la columna.)
- El trait `TieneTokenSecreto` (`generarToken`, `hashDe`, `porToken`, `token`), aplicado
  a `EnlaceCarga` y `EnlaceTurno`. `EnlaceTurno` además tiene `estaVigente()`.
- `EnlaceTurno::url()` usa la ruta **`turno.enlace`** con `{token}`. **José la define** en
  `routes/turnos-publico.php`.
- Enums: `TipoMarcacion` (entrada, salida), `OrigenMarcacion` (enlace, manual,
  cierre_automatico) y `OrigenColaborador` (autoregistro, admin).
- Calle con Nodo 1 a Nodo 6: `CalleSeeder`, que también corre como migración para que
  exista en producción.
- `Marcacion` lanza `LogicException` si se intenta editar o borrar.
- `Componente::cargos(): array`: los cargos del componente, que se eligen de una lista.
  Calle trae Conductor camioneta, Conductor microbús, Operador terapéutico y Monitor de
  ruta. Administración los edita, uno por línea, en «Componentes y nodos».

**José entrega, y Edwar lo usa en su parte de administración:**
- `Marcador::marcar(Colaborador $c, array $datos): Marcacion`. Decide entrada o salida.
- `Marcador::registrarManual(Colaborador $c, string $tipo, CarbonInterface $cuando, string $motivo, User $autor): Marcacion`
- `Marcador::cerrarAbiertas(): int`, para la tarea programada.
- `Marcador::estadoDe(Colaborador $c): ?Marcacion`, que devuelve la entrada abierta o null.

Mientras José no entregue el `Marcador`, Edwar programa contra estas firmas y lo
reemplaza por un *stub* en sus tests.

## 6. Orden de trabajo

| Paso | Quién | Entrega | Bloquea a |
|---|---|---|---|
| **0** | Edwar | Migraciones, modelos, trait, seeder Calle, rutas registradas, acciones de auditoría | José |
| **1a** | José | Identificación por cédula y autorregistro (componente reutilizable) | — |
| **1b** | Edwar | CRUD de colaboradores con búsqueda por cédula; componentes y nodos | — |
| **2a** | José | Enlace de turno: entrada y salida, foto y ubicación, `Marcador` | Edwar (corrección manual) |
| **2b** | Edwar | Enlaces de turno (crear, QR, revocar); panel «En turno ahora» | — |
| **3a** | José | El enlace de evidencias pide solo la cédula | — |
| **3b** | Edwar | Marcaciones con filtros, novedades, corrección manual, reporte CSV | — |
| **4** | Los dos | Prueba completa en Calle con personas reales | — |

Los pasos **a** y **b** de cada fila van en paralelo.

## 7. Decisiones abiertas

1. **Campos del formulario de Forms actual.** Hay que pasarlos a la lista de
   `colaboradores` y del registro de turno. Hace falta un pantallazo o un export del
   formulario: no se puede leer sin iniciar sesión.
2. **¿Autorregistro libre, o Coordinación crea a cada persona?** El plan propone
   autorregistro con verificación posterior (`verificado_at`).
3. **¿Un QR por componente o uno por nodo?** El plan soporta los dos.
4. **¿Foto obligatoria en entrada y salida, o solo en entrada?**
5. **¿PIN de 4 dígitos además de la cédula?** Recomendado si se ve suplantación.
6. **¿Una persona puede estar en dos componentes?** Hoy el plan le da uno habitual,
   pero puede marcar en cualquiera que tenga un enlace.
7. **Formato del reporte que necesitan (el Excel).**
