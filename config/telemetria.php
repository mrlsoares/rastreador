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

    /*
    |--------------------------------------------------------------------------
    | Empresa padrão da ingestão
    |--------------------------------------------------------------------------
    | Dispositivos/rastreadores criados automaticamente na ingestão (sem usuário
    | autenticado) recebem esta empresa. id=1 é a "Empresa Padrão".
    */
    'default_empresa_id' => (int) env('TELEMETRIA_DEFAULT_EMPRESA_ID', 1),
];
