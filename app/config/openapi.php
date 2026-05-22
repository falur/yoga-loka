<?php

declare(strict_types=1);

return [
    'enabled' => (bool) \env('OPENAPI_ENABLED', true),
    'swaggerEnabled' => (bool) \env('OPENAPI_SWAGGER_ENABLED', \env('APP_ENV') !== 'production'),
    'sourcePath' => 'app/src/Modules/System/Presentation/Http',
    'apiNamespace' => 'App\\Modules\\System\\Presentation\\Http',
    'routePrefix' => '/api/v1',
    'outputFile' => 'public/openapi/openapi.yml',
    'title' => 'YogaLoka API',
    'version' => '1.0.0',
    'debug' => (bool) \env('OPENAPI_DEBUG', true),
];
