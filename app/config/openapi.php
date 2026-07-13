<?php

declare(strict_types=1);

return [
    'enabled' => (bool) \env('OPENAPI_ENABLED', true),
    'swaggerEnabled' => (bool) \env('OPENAPI_SWAGGER_ENABLED', \env('APP_ENV') !== 'production'),
    'sourcePaths' => [
        'app/src/Modules/*/Presentation/Http',
        // Доменные enum-ы включены в скан, потому что API-ресурсы отдают их напрямую как
        // закрытый набор значений контракта (вид/тип конверсии медиа). Схемы строятся только для
        // enum-ов, на которые реально ссылается Presentation-слой; остальные лишь резолвятся.
        'app/src/Modules/*/Domain/Enum',
        // Общие конкретные ресурсы нескольких модулей (MediaResource и вложенные) живут в Shared
        // (docs/arch.md «Одно понятие API — один Resource»), генератору нужно их резолвить.
        'app/src/Shared/Presentation/Http/Resource',
    ],
    'apiNamespace' => 'App',
    'routePrefix' => '/api/v1',
    'outputFile' => 'public/openapi/openapi.yml',
    'title' => 'YogaLoka API',
    'version' => '1.0.0',
    'debug' => (bool) \env('OPENAPI_DEBUG', true),
];
