<?php

/*
|--------------------------------------------------------------------------
| Turnos — vía pública (dueño: José)
|--------------------------------------------------------------------------
| Se carga desde bootstrap/app.php con el middleware 'web' y nada más: sin
| sesión de usuario ni dependencia en contexto. La dependencia la da el
| enlace (EnlaceTurno::porToken).
|
| Contrato con administración: la pantalla del enlace se llama 'turno.enlace'
| y recibe {token}. EnlaceTurno::url() depende de ese nombre.
*/
