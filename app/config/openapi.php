<?php

declare(strict_types=1);

return [
    'enabled' => (bool) \env('OPENAPI_ENABLED', true),
    'swaggerEnabled' => (bool) \env('OPENAPI_SWAGGER_ENABLED', \env('APP_ENV') !== 'production'),
    'sourcePaths' => [
        'app/src/Modules/*/Infrastructure/Spiral/Http',
        // Доменные enum-ы включены в скан, потому что API-ресурсы отдают их напрямую как
        // закрытый набор значений контракта (вид/тип конверсии медиа). Схемы строятся только для
        // enum-ов, на которые реально ссылается HTTP-слой; остальные лишь резолвятся.
        'app/src/Modules/*/Domain/Enum',
        // Публичные enum модулей — по той же причине: ресурсы соседей строятся из публичных DTO и
        // ссылаются на публичные дубликаты enum (вид и тип конверсии медиа) напрямую.
        'app/src/Modules/*/Public/Enum',
        // Базовый AbstractResource живёт в общей части: генератору нужно его резолвить, чтобы
        // разбирать наследников из модулей.
        'app/src/Shared/Infrastructure/Spiral/Http/Resource',
    ],
    'apiNamespace' => 'App',
    'routePrefix' => '/api/v1',
    'outputFile' => 'public/openapi/openapi.yml',
    'title' => 'YogaLoka API',
    'version' => '1.0.0',
    'debug' => (bool) \env('OPENAPI_DEBUG', true),
];
