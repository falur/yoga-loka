<?php

declare(strict_types=1);

return [
    'enabled' => (bool) \env('OPENAPI_ENABLED', true),
    'swaggerEnabled' => (bool) \env('OPENAPI_SWAGGER_ENABLED', \env('APP_ENV') !== 'production'),
    'sourcePath' => 'app/src/Endpoint/Api/V1',
    'apiNamespace' => 'App\\Endpoint\\Api\\V1',
    'routePrefix' => '/api/v1',
    'outputFile' => 'public/openapi/openapi.yml',
    'title' => 'YogaLoka API',
    'version' => '1.0.0',
    'debug' => (bool) \env('OPENAPI_DEBUG', true),
];
