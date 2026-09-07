<?php

/*
| El proyecto tiene su propio mensaje de credenciales en SesionController,
| deliberadamente genérico para no revelar qué correos tienen cuenta. Estos
| son los del framework, por si alguna ruta llegara a usarlos.
*/

return [

    'failed' => 'Las credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds segundos.',

];
