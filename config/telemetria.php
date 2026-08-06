<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chave de ingestão máquina-a-máquina
    |--------------------------------------------------------------------------
    | Comparada contra o header X-API-KEY no middleware CheckApiKey.
    | Ficar em config (e não em env() direto) garante que continue funcionando
    | após `php artisan config:cache`.
    */
    'api_key' => env('TELEMETRIA_API_KEY'),
];
