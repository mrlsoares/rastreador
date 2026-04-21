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
class SwaggerDef {}
