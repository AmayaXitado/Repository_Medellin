# Turnos — tareas de José: vía pública (cédula, turnos y evidencias)

Lee primero [`PLAN.md`](PLAN.md): el flujo de la persona de campo (§2), la privacidad
(§2) y el contrato con Edwar (§5) están ahí. Rama: `josedev` → PR a `develop`.

**Tu parte:** todo lo que ve la persona de campo, sin sesión: los enlaces por QR. **No
tocas** migraciones, modelos, `AccionAuditoria`, el menú lateral ni nada de
`admin/`: es de Edwar. Si necesitas una columna o un método en un modelo, pídeselo.

**Arrancas cuando Edwar mezcle la fase 0 en `develop`.** Mientras tanto puedes avanzar
las vistas y el JS de la fase 1a con datos de prueba.

---

## Fase 1a — identificarse por cédula (pieza reutilizable)

La usan el enlace de turno y el de evidencias, así que va como pieza aparte.

- [ ] `app/Services/Turnos/IdentificadorColaborador.php`:
  - `buscar(int $dependenciaId, string $cc): ?Colaborador`, que normaliza la cédula;
  - `registrar(int $dependenciaId, array $datos): Colaborador`, con `origen = autoregistro`
    y auditoría `colaborador.registrado`.
- [ ] Componente Blade `<x-campos.cedula>`:
  - campo de cédula con `inputmode="numeric"`;
  - al salir del campo consulta si existe;
  - si existe muestra «Hola, Jos…»;
  - si no, despliega `<x-campos.registro-colaborador>`.
- [ ] `<x-campos.registro-colaborador :componente>`: nombre, correo, teléfono, entidad,
      cargo y nodo. Los campos definitivos salen del formulario de Forms actual
      (ver `PLAN.md` §7).
- [ ] `<x-campos.selector-nodo :componente>`: reemplaza el `range(1, 6)` fijo.
- [ ] **Cargo como lista, no texto libre:** en `<x-campos.registro-colaborador>`, el
      campo `colaborador[cargo]` pasa a ser un `<select>` con `$componente->cargos()`. Si
      el componente no tiene cargos, queda el texto libre. Al guardar, valida con
      `Rule::in($componente->cargos())`, como en la administración
      (`GuardarColaboradorRequest`).
- [ ] Endpoint de consulta `POST /t/{token}/cedula`:
  - devuelve **solo** `{existe: bool, saludo: "Jos…"}`;
  - nunca devuelve correo, teléfono ni nombre completo;
  - lleva `throttle` por IP y por token.
- [ ] Recordar la cédula en el teléfono (`localStorage`) para no escribirla cada día.
      Todo dentro de `try/catch`, y con un botón «No soy yo».

## Fase 2a — enlace de turno: entrada y salida con el mismo link

- [ ] `app/Services/Turnos/Marcador.php` con el contrato de `PLAN.md` §5:
  - `marcar()`: si hay una entrada abierta registra la salida; si no, la entrada. Toma la
    hora del servidor. Ignora una segunda marca en menos de 2 minutos. Calcula y guarda
    `fuera_de_zona`, `fuera_de_turno` y `sin_ubicacion`. Escribe en la auditoría.
  - `registrarManual()`, `cerrarAbiertas()` y `estadoDe()`. Edwar los usa.
- [ ] Rutas en `routes/turnos-publico.php`:
  - `GET /t/{token}`: pantalla con la cédula;
  - `POST /t/{token}/cedula`: la consulta;
  - `POST /t/{token}/marcar`: registrar la marca.
- [ ] Pantalla de marcar: un solo botón grande, que dice «Registrar entrada» o
      «Registrar salida · llevas 5 h 20 min» según `estadoDe()`. Pide la ubicación y la
      foto según `componentes.config`.
- [ ] Foto: reutiliza `foto-georreferencial.js` y `campo-archivo`. La foto se guarda como
      documento en una carpeta del componente y su id va en `marcaciones.documento_id`.
- [ ] Coordenadas en columnas (`lat`, `lng`, `precision_m`, `municipio`), no solo
      dibujadas en la foto.
- [ ] `throttle` para `/t/*` en `AppServiceProvider`, igual que `envio-carga`.

## Fase 3a — el enlace de evidencias pide solo la cédula

- [ ] En `publico/enviar.blade.php`, reemplaza el bloque «Quién envía» (nombre, correo,
      entidad, nodo) por `<x-campos.cedula>`.
- [ ] En `EnvioPublicoController::recibir()`:
  - resuelve el colaborador por cédula;
  - llena `recepciones.colaborador_id` y **copia** sus datos a `remitente_*` y `nodo`,
    para que la ficha del documento y la cadena de custodia sigan funcionando igual;
  - si la cédula es nueva, registra al colaborador con los datos del formulario en la
    misma transacción.
- [ ] Los tests actuales de `EnvioPublicoTest` se adaptan; no se borran.

## Tests (`tests/Feature/Turnos/Publico*`)

- [ ] La primera vez pide los datos; la segunda, con la misma cédula, no.
- [ ] La consulta de cédula nunca devuelve el correo ni el nombre completo, y se bloquea
      tras N intentos.
- [ ] Entrada → salida → entrada alterna bien; un doble toque no crea dos entradas.
- [ ] Un turno de las 22:00 a las 06:00 cierra con la salida correcta.
- [ ] Sin ubicación se marca igual, con `sin_ubicacion = true`.
- [ ] Un enlace revocado o vencido no marca; un colaborador inactivo no se reconoce.
- [ ] El envío de evidencias con una cédula conocida guarda `colaborador_id` y los
      `remitente_*` copiados.
