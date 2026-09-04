<?php

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
    | El repositorio solo recibe PDF y fotografía. Las dos listas describen el
    | mismo conjunto por dos caminos distintos: 'extensiones_permitidas'
    | alimenta la regla mimes: y el atributo accept de los formularios;
    | 'mimetypes_permitidos' alimenta la regla mimetypes:, que mira el
    | contenido real del archivo y no se deja engañar por un renombrado.
    | Cambiar el conjunto se hace aquí y solo aquí.
    */

    'extensiones_permitidas' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],

    'mimetypes_permitidos' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    */

    'por_pagina' => 25,

];
