# Módulo de turnos

**En una frase:** las personas de campo marcan entrada y salida con el QR o el link
de su componente, solo con su cédula; Coordinación ve quién está en turno, valida lo
que se sale de lo esperado y descarga el reporte para Excel.

> Estado: **implementado** (octubre 2026). El plan original, con las decisiones y el
> reparto, está en [`PLAN.md`](PLAN.md).

---

## 1. Para la persona de campo

1. Escanea el QR (o abre el link que le compartieron).
2. Escribe su **cédula**. La primera vez llena sus datos: nombre, nodo y cargo. Desde
   ahí, solo la cédula: el teléfono la recuerda.
3. Toca **Registrar entrada** al llegar y **Registrar salida** al irse. El botón dice
   cuál toca: lo decide el sistema, no la persona.

Si el componente lo exige, se toma una **foto** (con sello de hora y lugar). Sin señal
de GPS se marca igual, y queda como novedad para Coordinación.

**Con el turno del día cerrado** (entrada y salida ya marcadas), el enlace no deja
marcar otra vez: el botón dice «Ya registraste tu turno de hoy». Si la hora quedó mal,
no se arregla marcando de nuevo: la corrige Coordinación o Edición. Cuántos turnos se
permiten al día lo decide la regla «Turnos por día» del componente.

En los **enlaces de evidencias** de un componente pasa lo mismo: la cédula basta, y
los datos de quien envía salen de su ficha.

## 2. Para Coordinación y Edición

El grupo **Turnos** del menú va en el orden del trabajo:

| Menú | Para qué | Quién |
|---|---|---|
| **Componentes y nodos** | Configurar Calle, Básica…: nodos, cargos y reglas | Administración |
| **Enlaces de turno** | Crear el enlace de un componente o de un nodo, copiar el link, imprimir el QR, revocarlo | Coordinación |
| **Personas de campo** | Buscar por cédula (con o sin puntos) o nombre; registrar, editar, verificar, desactivar. En la ficha: sus marcaciones, sus evidencias y **agregar una marca que faltó** | Coordinación |
| **En turno ahora** | Quién marcó entrada y no salida, dónde y desde cuándo. En rojo si pasó del máximo | Desde Edición |
| **Novedades** | Turno corto, horas extra, sin salida, sin ubicación, fuera de zona. **Aprobar, rechazar** (pide comentario) **o corregir la hora**. Llegan por la campana | Desde Edición |
| **Marcaciones y reporte** | Todas las marcas, con foto y mapa, filtros y **Corregir hora**. **Descargar reporte (Excel)** | Desde Edición |

### Corregir una hora

En Marcaciones, en Novedades o en la ficha: **Corregir hora** → la hora correcta y el
motivo. Para quien revisa es editar; por debajo, la marca original **queda anulada**
(tachada, con quién la corrigió y por qué) y la reemplaza la nueva. Las novedades que
dependían de ella pasan a **Corregida** y el turno se vuelve a juzgar con la hora buena:
si con la corrección cumple su jornada, no queda novedad; si ahora tiene horas extra,
aparece la novedad nueva. No se permite dejar una salida antes de su entrada, ni una
hora del futuro, ni corregir dos veces la misma marca (se corrige la que la reemplazó).

### Horas extra, de punta a punta

Lo que se compara con la jornada es el **trabajo real**: el tiempo en el sitio menos el
**descanso** no remunerado. El descanso va por tramos (Talento Humano; doctrina laboral
de Actualícese y Gerencie):

| Tiempo en el sitio | Se descuenta | Por qué |
|---|---|---|
| Hasta 4 h | Nada | Una pausa corta (café, hidratación) se paga como trabajo efectivo |
| Más de 4 h y hasta 6 h | 30 min | El desayuno |
| Más de 6 h | 1 h | Desayuno y almuerzo: no se computan en la jornada |

Los tres tramos se ajustan por componente («Descanso no remunerado» en Componentes y
nodos); si Talento Humano fija el turno largo en 5 h, se cambia ahí.

| | Ejemplo |
|---|---|
| Permanencia (entrada → salida) | 9 h 46 min |
| Descanso | 1 h |
| **Trabajo real** | **8 h 46 min** |
| Jornada esperada | 8 h |
| Horas extra | **46 min** (pasa de la tolerancia de 15 min) |

1. El trabajo real pasa de la **jornada esperada** más la **tolerancia**.
2. Al marcar la salida nace la novedad **Horas extra** (pendiente) y llega por la campana.
3. Coordinación o Edición la **aprueba**, la **rechaza** con motivo, o **corrige la hora**
   si fue un error de marcación.
4. El **reporte** la refleja así:

| Permanencia | Descanso | Horas trabajadas | Jornada | Horas ordinarias | Horas extra | Extras aprobadas |
|---|---|---|---|---|---|---|
| 9,77 | 1,00 | 8,77 | 8,00 | 8,00 | 0,77 | SI / NO / PENDIENTE |

Dentro de la tolerancia no hay horas extra: 9 h 10 min en el sitio son 8 h 10 min de
trabajo, 10 min de más (menos de 15): sale 0,00 extra. Un turno de 8 h en el sitio, con
el descanso, son 7 h de trabajo: **turno corto**. Lo que se paga lo decide la nómina con
esas columnas.

## 3. Para Administración

| Menú | Para qué |
|---|---|
| **Componentes y nodos** | Crear Básica, Estabilización… con sus nodos, sus cargos y sus reglas. Sin tocar código |
| **Enviar aviso** | Mensajes por la campana a la dependencia, o solo a ciertos roles |

Las reglas de cada componente:

| Regla | Qué hace |
|---|---|
| Cargos (uno por línea) | La lista de la que se elige el cargo de cada persona |
| Tolerancia (minutos) | Margen antes de contar un turno como corto o con horas extra |
| Jornada esperada (horas) | Trabajo neto que debería tener un turno, sin el descanso |
| Descanso no remunerado | Por tramos: hasta 4 h nada; hasta 6 h, 30 min; más de 6 h, 60 min. Se resta de la permanencia |
| Duración máxima (horas) | Una entrada sin salida pasado esto se cierra sola como «sin salida» |
| Foto obligatoria | Sin foto no se marca |
| Ubicación obligatoria | Sin GPS no se marca (si no, se marca y queda como novedad) |
| Autorregistro | Si una cédula nueva puede registrarse sola por el enlace |
| Turnos por día | Cuántos turnos puede abrir una persona en un día; cumplidos, el enlace ya no deja marcar |

## 4. Despliegue y operación

**Una sola vez, en cada servidor:**

1. **Cron** para las tareas programadas (cierra cada hora los turnos sin salida):
   ```
   * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
   ```
   Sin esto, los turnos sin salida no se cierran solos (todo lo demás funciona).
2. **Etiqueta de versión** en `main`, para que el menú numere desde ahí:
   ```
   git tag v2.0 origin/main
   git push origin v2.0
   ```
3. `php.ini`: `max_file_uploads = 50` (tandas de evidencias).

**En cada despliegue**, `bin/deploy.sh` hace solo:
- las migraciones (incluidas las que crean Calle, sus nodos y sus cargos);
- la **versión** (`VERSION`): `v2.N` en `main`, `v2.(N+1)-beta.K` en `develop`;
- el **aviso de versión**: si `CHANGELOG.md` tiene la sección de la versión que se
  despliega, se anuncia una vez por la campana.

**Antes de fusionar a `main`:** escribe en `CHANGELOG.md` la sección de la versión que
viene (el número es el de la beta en pruebas, sin `-beta`).

## 5. Técnico

### Datos

| Tabla | Qué guarda |
|---|---|
| `componentes`, `nodos` | Calle, Básica…; reglas en `componentes.config` (`Componente::REGLAS`) |
| `colaboradores` | Personas de campo, por cédula normalizada, únicas por dependencia |
| `enlaces_turno` | El QR compartido; token como en `enlaces_carga` (`TieneTokenSecreto`) |
| `marcaciones` | **Inmutable**: solo se puede anular una vez al corregirla (`anulada_at`, `reemplazada_por_id`); cualquier otro cambio o borrado lanza excepción. Lo anulado no cuenta (`Marcacion::vigentes()`) |
| `novedades_turno` | Lo que se salió de lo esperado y su validación (pendiente, aprobada, rechazada, corregida) |
| `avisos` | Avisos enviados por la campana (`version` única) |

### Dónde vive cada cosa

| Pieza | Archivo |
|---|---|
| Decidir entrada o salida, doble toque, foto, fuera de zona, cierre automático, corrección | `app/Services/Turnos/Marcador.php` |
| Detectar novedades y avisar a Coordinación | `app/Services/Turnos/Novedades.php` |
| Trabajo real de un turno (permanencia − descanso por tramos), el mismo para novedades y reporte | `Componente::minutosTrabajados()`, `Componente::descansoPara()` |
| Cédula y autorregistro (turnos y evidencias) | `app/Services/Turnos/IdentificadorColaborador.php` |
| Armar turnos (entrada + salida) para el reporte | `app/Services/Turnos/Jornadas.php` |
| Pantalla pública `/t/{token}` | `app/Http/Controllers/Publico/TurnoController.php`, `resources/views/publico/turno.blade.php`, `resources/js/turno.js` |
| Administración | `app/Http/Controllers/Admin/Turnos/*`, `resources/views/admin/turnos/*`, `routes/turnos-admin.php` |
| Piezas de formulario reutilizables | `resources/views/components/campos/*`, `resources/js/cedula.js`, `resources/js/selects-dependientes.js` |
| Versión y avisos | `config/app.php` (`version`), `bin/deploy.sh`, `app/Services/Avisos.php`, `CHANGELOG.md` |

### Reglas que no hay que romper

- **El enlace es compartido:** la consulta de cédula devuelve solo `{existe, saludo,
  accion}`. Nunca el nombre completo, el correo ni el teléfono. Tiene límite de
  intentos (`cedula-consulta`).
- **La hora que vale es la del servidor.** La del teléfono se guarda aparte
  (`declarada_at`) y una del futuro se descarta.
- **Las marcaciones no se editan.** Corregir es `Marcador::corregir()`: anula la
  original y crea la nueva con `origen = manual`, motivo y autor. Todo lo que cuenta
  horas usa `Marcacion::vigentes()`.
- **Los QR impresos apuntan a `/t/{token}`** (`turno.enlace`): esa ruta no se cambia.
- **Calle es un dato:** nada de `if ($componente->slug === 'calle')`; lo distinto va en
  `Componente::REGLAS`.

### Pruebas

`tests/Feature/Turnos/*` y `tests/Feature/AvisosTest.php`: marcar de punta a punta,
turno nocturno, doble toque, privacidad de la cédula, novedades, cierre automático,
reporte CSV, permisos por rol y aislamiento entre dependencias.
