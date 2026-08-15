<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    description: "API dedicada à extração e consumo de dados de rastreamento de frotas.",
    title: "API de Telemetria GPS"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer"
)]
#[OA\SecurityScheme(
    securityScheme: "apiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-API-KEY",
    description: "Chave de bootstrap (TELEMETRIA_API_KEY) para provisionamento máquina-a-máquina."
)]
#[OA\SecurityScheme(
    securityScheme: "deviceToken",
    type: "apiKey",
    in: "header",
    name: "X-DEVICE-TOKEN",
    description: "Token de ingestão por dispositivo (provisionado via /esp32/provision)."
)]
class SwaggerDef {}
