## Qué hace

Documenta pasa a ser el único sitio donde se dan de alta las personas. Al crear un usuario aquí se crea también su identidad en Authentik —con el documento de `username` y la contraseña que puso el administrador— para que entre con lo mismo que le dijeron, sin que nadie tenga que crearla dos veces.

El ingreso queda solo por Authentik: la pantalla de acceso pierde los campos de usuario y contraseña, y el botón «Ingresar» lleva directo al proveedor.

## Cómo queda el flujo

```
Alta en Documenta              →  POST /api/v3/core/users/
  documento 1122334455             username:  1122334455
  nombre    Nueva Auxiliar         name:      Nueva Auxiliar
  clave     ••••••••               is_active: true
  correo    (opcional)             email:     solo si lo hay
                                →  POST /core/users/{pk}/set_password/

Ingreso                        ←  preferred_username: 1122334455
                                  → User::where('documento', …)
```

Editar la ficha también sincroniza: corregir el documento cambia el `username`, cambiar la clave la cambia en las dos puntas, y apagar la cuenta aquí la apaga allá.

## Emparejamiento al volver del SSO

1. `preferred_username` normalizado contra `users.documento`.
2. Si no hay coincidencia y la identidad trae correo, `users.email` exacto.

El respaldo por correo existe para las cuentas que ya estaban en Authentik con un `username` que no es una cédula. Se puede quitar cuando no quede ninguna.

## Cuando Authentik no está

Nunca bloquea la gestión de usuarios. Si no responde, el alta local se completa igual y el fallo se ve en tres sitios: un aviso rojo en pantalla, un registro en la auditoría, y una marca permanente **«Sin acceso · falta en Authentik»** en el listado. Volver a guardar la ficha con una contraseña es el reintento.

## Sobre la contraseña

Viaja a Authentik, en una llamada aparte y nunca en el cuerpo del alta. Es una decisión tomada a conciencia, en contra de lo que planteaba el prompt original: la alternativa —que cada quien defina su clave por un enlace de invitación— exige un correo por persona y un servidor de correo funcionando, y aquí el correo es opcional y mucha gente no tiene. A cambio, la misma clave queda en dos sitios.

## Configuración

Una variable nueva, `AUTHENTIK_API_TOKEN`. La cuenta de servicio necesita cuatro permisos globales sobre el modelo User: `add_user`, `change_user`, `view_user` y `reset_user_password`. Sin el último, el usuario se crea pero la clave no funciona.

Sin token configurado la aplicación funciona igual y no intenta ninguna llamada.

## De paso

`users.email` llevaba índice único desde la primera migración sin que la validación lo exigiera: un correo repetido reventaba la petición con un error 500 en vez de marcar el campo.

## Pruebas

240 pasan (1783 aserciones), 25 nuevas repartidas en dos archivos:

- `AuthentikProvisioningTest` — el documento como username, la clave fijada aparte, idempotencia, sincronización al editar, y los tres caminos de fallo (sin configurar, Authentik caído, solo la contraseña falla).
- `IngresoAuthentikTest` — emparejamiento por documento con Socialite simulado, documento con puntos, respaldo por correo, y el rechazo de identidades desconocidas o desactivadas.

Probado además contra la instancia real: usuario creado desde Documenta e ingreso con esa misma clave.

## Revisar con ojo

- La contraseña en dos sitios, explicada arriba.
- `POST /ingresar` sigue activo aunque ninguna pantalla lo enlaza, y `/perfil` todavía deja cambiar la clave local. Son los dos restos del ingreso por contraseña; se pueden cerrar en otro PR.
- No hay backfill: las cuentas anteriores tienen `authentik_id` nulo hasta que alguien guarde su ficha con una contraseña.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
