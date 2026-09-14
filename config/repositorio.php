<?php

/*
| Lo que el repositorio recibe de fuera, por un enlace de carga: PDF y
| fotografía, y nada más. Es la única puerta abierta a internet, así que es
| la lista corta, y se declara arriba para que el conjunto de la aplicación
| se lea abajo como lo que es: este mismo, más lo que se añada.
*/
$formatosPublicos = [
    'extensiones' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

    'mimetypes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de almacenamiento
    |--------------------------------------------------------------------------
    | Los archivos nunca se sirven directamente desde el disco público: pasan
    | siempre por el controlador de descargas, que valida permisos y audita.
    | Cambiar a 's3' aquí basta para migrar a la nube sin tocar el código.
    */

    'disco' => env('REPOSITORIO_DISCO', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Límites de carga
    |--------------------------------------------------------------------------
    | 'tamano_maximo_kb' debe ir de la mano de upload_max_filesize y
    | post_max_size en php.ini, o PHP rechazará el archivo antes que Laravel.
    */

    'tamano_maximo_kb' => env('REPOSITORIO_TAMANO_MAXIMO_KB', 25600),

    /*
    |--------------------------------------------------------------------------
    | Formatos aceptados
    |--------------------------------------------------------------------------
    | Los dos caminos de carga no reciben lo mismo, y por eso son dos listas:
    |
    | - 'publico': lo que envía alguien de fuera por un enlace de carga, sin
    |   cuenta y sin que nadie responda por él. Solo PDF y fotografía.
    |
    | - 'aplicacion': lo que sube alguien que ya entró al repositorio. Lo
    |   anterior más hojas de cálculo, que es trabajo interno y va firmado
    |   por una cuenta con nombre y apellido.
    |
    | Dentro de cada conjunto, las dos claves describen el mismo grupo de
    | formatos por dos caminos distintos: 'extensiones' alimenta la regla
    | mimes: y el atributo accept de los formularios; 'mimetypes' alimenta la
    | regla mimetypes:, que mira el contenido real del archivo y no se deja
    | engañar por un renombrado. Cambiar un conjunto se hace aquí y solo aquí.
    */

    'formatos' => [

        'publico' => $formatosPublicos,

        'aplicacion' => [
            'extensiones' => [
                ...$formatosPublicos['extensiones'],

                // Hojas de cálculo. Se quedan fuera .xlsm y demás formatos
                // con macros: traen VBA ejecutable, y el repositorio los
                // repartiría a todo el equipo tal como llegaron.
                'xlsx',
                'xls',
            ],

            'mimetypes' => [
                ...$formatosPublicos['mimetypes'],

                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Horario hábil
    |--------------------------------------------------------------------------
    | Con qué vara se mide si algo llegó en horario de trabajo. Lo consulta
    | App\Services\CalendarioHabil, y su respuesta se congela en cada
    | recepción: cambiar esto no reescribe lo ya recibido.
    |
    | La zona manda. La aplicación atiende en Medellín pero la base de datos
    | puede estar en UTC, y comparar horas en la zona del servidor correría
    | todo cinco horas sin que nadie lo note hasta que salga un informe torcido.
    */

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

        /*
        | Festivos colombianos, en formato Y-m-d. Va vacía a propósito: los
        | corre la Ley Emiliani al lunes siguiente y no todos, así que una
        | lista calculada por fórmula sale mal y una lista mal calculada es
        | peor que ninguna. Los carga la administración, año por año.
        */
        'no_habiles' => [],

    ],

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    */

    'por_pagina' => 25,

];
