<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Conexión por defecto
    |--------------------------------------------------------------------------
    |
    | Nombre de la conexión que usa la fachada cuando no se indica otra:
    | Sendify::textMessageTo(...) usa esta, Sendify::connection('otra') no.
    |
    */
    'default' => env('SENDIFY_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Conexiones
    |--------------------------------------------------------------------------
    |
    | url      Base del servicio Sendify, por ejemplo https://sendify.miempresa.mx
    | client   API key de la instancia (snd_live_...), va en el header X-API-Key
    | instance ID numérico o nombre de la instancia de WhatsApp
    |
    | Se aceptan las variables con o sin guion bajo: SENDIFY_URL o SENDIFYURL.
    |
    */
    'connections' => [
        'default' => [
            'url' => env('SENDIFY_URL', env('SENDIFYURL', 'http://localhost:3333')),
            'client' => env('SENDIFY_CLIENT', env('SENDIFYCLIENT')),
            'instance' => env('SENDIFY_INSTANCE', env('SENDIFYINSTANCE')),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | retries son los intentos extra cuando la instancia está hibernando y
    | responde 503 con Retry-After, o cuando se topa el límite de peticiones.
    |
    */
    'timeout' => (int) env('SENDIFY_TIMEOUT', 30),
    'connect_timeout' => (int) env('SENDIFY_CONNECT_TIMEOUT', 10),
    'retries' => (int) env('SENDIFY_RETRIES', 1),
    'verify_ssl' => (bool) env('SENDIFY_VERIFY_SSL', true),
];
