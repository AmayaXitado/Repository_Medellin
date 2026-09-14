# Tarea: política de tratamiento de datos, registro de horarios y nombre del sistema

Tres cambios sobre el repositorio documental. Lee primero `INICIO.md`,
`PROMPT-BANDEJA.md`, `app/Models/EnlaceCarga.php`, `app/Models/Recepcion.php` y
`routes/web.php`. Respeta las convenciones que ya existen.

Trabaja en tres tandas, deteniéndote entre cada una.

---

## 1. Política de tratamiento de datos personales

### Contexto

El sistema recibe documentos de personas externas por enlaces públicos, y guarda de
ellas nombre, correo, entidad e IP. Eso es tratamiento de datos personales, y en
Colombia lo regula la Ley 1581 de 2012 con su Decreto 1377 de 2013.

Lo que hay que construir es **el mecanismo**: publicar la política, pedir autorización
antes de recibir, y dejar constancia verificable de esa autorización.

> **El texto legal NO lo escribes tú.** Deja un marcador claro y visible en la vista,
> del tipo «PENDIENTE: texto por definir por la oficina jurídica de la Alcaldía», con
> los títulos de las secciones que la ley exige pero sin inventar el contenido. Una
> política redactada por una IA y publicada como oficial es un problema, no una
> entrega. Yo le paso el archivo a jurídica para que lo llenen.

### Modelo de datos

**Tabla `politicas_datos`** — la política es versionada, porque tienes que poder
demostrar años después *qué texto exacto* aceptó cada persona:

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint | |
| `version` | string(20) único | «1.0», «1.1» |
| `contenido` | longText | El texto completo, tal como se publicó |
| `resumen` | string | Una línea para mostrar junto a la casilla de aceptación |
| `vigente_desde` | date | |
| `activa` | boolean | Solo una activa a la vez |
| `creado_por` | FK users nullable | |
| timestamps | | |

**Columnas nuevas en `recepciones`** — la constancia de cada autorización:

| Columna | Tipo | Notas |
|---|---|---|
| `politica_datos_id` | FK nullable | Qué versión aceptó |
| `autorizacion_at` | timestamp nullable | Cuándo la aceptó |

La IP y el agente ya se guardan en `recepciones`. Con eso la constancia queda completa:
quién, qué texto, cuándo y desde dónde.

No hagas una tabla aparte de autorizaciones: la autorización se da por cada envío, y
tenerla en la misma fila que el documento la hace más fácil de exhibir si alguien la
reclama.

### Comportamiento

1. **Página pública** en `/politica-de-datos`, sin autenticación, que muestra la versión
   activa. Enlázala desde el pie de la aplicación y desde el formulario público.

2. **Casilla de autorización en el formulario público de envío**, con estas reglas —
   no son de estilo, son de validez jurídica:
   - **Desmarcada por defecto.** Una casilla premarcada no es autorización válida.
   - **Obligatoria**: sin ella no se recibe el archivo. Regla `accepted`.
   - Junto a ella, el `resumen` de la política y un enlace que abra el texto completo
     en pestaña nueva, sin perder el archivo que la persona ya seleccionó.

3. **Al recibir**, guarda `politica_datos_id` con la versión activa **en ese momento** y
   `autorizacion_at`. No guardes solo un booleano: si la política cambia mañana, un
   booleano no dice qué aceptó la persona ayer.

4. **En la ficha de la recepción**, muestra al funcionario qué versión autorizó el
   remitente y cuándo, con enlace al texto de esa versión — no a la vigente.

5. **Seeder** con la versión «1.0» y el contenido marcado como pendiente, para que el
   sistema arranque sin quedar bloqueado.

6. Registra en `AccionAuditoria` una acción `politica.publicada` para cuando se active
   una versión nueva.

### Criterios de aceptación

- Enviar sin marcar la casilla se rechaza con mensaje claro en español.
- La recepción guarda el id de la versión, no un booleano.
- Publicar una versión nueva no altera lo que ya autorizaron las anteriores.
- `/politica-de-datos` abre sin sesión.

---

## 2. Registro de horarios

### Objetivo

Poder responder dos preguntas: **en qué horario trabajan los funcionarios** y **cuánto
tarda un remitente en responder** desde que se le entrega el enlace.

Por ahora solo hay que **guardar los datos y mostrarlos**. Los informes vienen después:
no construyas tableros ni gráficas.

### Modelo de datos

**En `enlaces_carga`:**

| Columna | Tipo | Notas |
|---|---|---|
| `enviado_at` | timestamp nullable | Cuándo se entregó el enlace al remitente |

Al crear el enlace, ponlo en `now()` — en la práctica generarlo es entregarlo. Añade en
la pantalla de administración un botón **«Corregir fecha de envío»** para el caso de que
se haya generado un día y entregado otro. El funcionario que lo creó ya queda en
`creado_por`.

**En `recepciones`:**

| Columna | Tipo | Notas |
|---|---|---|
| `fuera_de_horario` | boolean default false | Si llegó fuera del horario hábil |

`created_at` ya registra el instante de llegada; no dupliques esa columna.
`fuera_de_horario` se calcula **al recibir** y se guarda, porque el horario configurado
puede cambiar después y el dato debe quedar congelado tal como era ese día.

### Tiempo de respuesta

No lo guardes en una columna: es un dato derivado y se desincroniza. Ponlo como accesor
en `Recepcion`:

```php
public function minutosDesdeEnvio(): ?int
```

Calculado entre `enlaceCarga->enviado_at` y el `created_at` de la recepción. Devuelve
`null` si el enlace ya no existe.

### Horario hábil

En `config/repositorio.php`:

```php
'horario' => [
    'zona' => 'America/Bogota',
    'dias' => [
        // 1 = lunes … 7 = domingo. Día ausente = no hábil.
        1 => ['07:00', '17:00'],
        2 => ['07:00', '17:00'],
        3 => ['07:00', '17:00'],
        4 => ['07:00', '17:00'],
        5 => ['07:00', '16:00'],
    ],
    // Festivos colombianos, formato Y-m-d. Los carga la administración.
    'no_habiles' => [],
],
```

Y un servicio `App\Services\CalendarioHabil` con `esHabil(Carbon $momento): bool`.

Dos advertencias:

- **La zona horaria manda.** La aplicación corre en `America/Bogota` y MySQL puede estar
  en UTC. Comprueba que `esHabil()` compare en la zona de la configuración, no en la del
  servidor. Un error aquí desplaza todo cinco horas y nadie lo nota hasta que un reporte
  sale mal.
- **No inventes los festivos.** Deja `no_habiles` vacío y documenta que hay que cargarlos.
  Los festivos colombianos se mueven por la Ley Emiliani y una lista mal calculada es
  peor que ninguna.

### Dónde se ve

- **Ficha de la recepción**: hora de llegada, si fue fuera de horario, y cuánto tardó
  desde que se envió el enlace.
- **Bandeja**: una marca discreta en las que llegaron fuera de horario.
- **Administración de enlaces**: columna con la fecha de envío.

### Criterios de aceptación

- Un envío un martes a las 10:00 queda `fuera_de_horario = false`.
- Un envío un sábado, o un martes a las 22:00, queda `true`.
- Cambiar el horario en la configuración **no** altera las recepciones ya guardadas.
- `minutosDesdeEnvio()` devuelve `null` si el enlace fue eliminado.

---

## 3. Nombre del sistema

El nombre oficial es **Documenta Inclusión Social**.

1. En `.env` y `.env.example`:

   ```
   APP_NAME="Documenta Inclusión Social"
   ```

2. Busca y elimina cualquier nombre escrito a mano en las vistas:

   ```
   grep -rn "Documenta Medellín\|Repository Medellin\|Repositorio Medellín" resources/ config/
   ```

   Todo debe salir de `config('app.name')`. En el conflicto reciente del login ya se
   resolvió así; revisa que no queden restos en otras pantallas, sobre todo en el pie de
   página y en los títulos.

3. Revisa el `<title>` de las vistas públicas y el nombre en la página de la política.

> **Aviso para mí, no para ti:** el `.env` del VPS no se toca con el despliegue.
> Recuérdame en tu resumen final que debo cambiar `APP_NAME` allá a mano, o el sitio
> seguirá mostrando el nombre viejo.

---

## Orden y verificación

1. Nombre del sistema — es de minutos y quita ruido de las otras dos.
2. Horarios — modelo pequeño y aislado.
3. Política de datos — la más extensa y la que toca el formulario público.

Después de cada tanda:

```
php artisan migrate
php artisan test
npm run build
```

Pruebas que quiero, siguiendo lo que ya hay en `tests/`:

- Enviar por el enlace público sin marcar la autorización: se rechaza.
- Al recibir se guarda el id de la política activa, no un booleano.
- Publicar la versión 1.1 no cambia lo que aceptaron las recepciones con la 1.0.
- Una recepción de sábado queda marcada fuera de horario.
- Cambiar el horario en configuración no altera recepciones anteriores.

## No hagas

- No redactes el texto legal de la política. Marcador y títulos, nada más.
- No calcules festivos colombianos por fórmula. Lista configurable, vacía por ahora.
- No construyas informes ni gráficas de horarios todavía. Solo guarda y muestra el dato.
- No guardes la autorización como booleano.
- No instales paquetes sin preguntarme.
