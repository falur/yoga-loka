<?php

declare(strict_types=1);

use Spiral\Scaffolder\Declaration;

/**
 * Конфигурация генератора кода.
 * @link https://spiral.dev/docs/basics-scaffolding
 * @see \Spiral\Scaffolder\Config\ScaffolderConfig
 */
return [
    // Базовый namespace для всех деклараций.
    'namespace' => 'App',

    'declarations' => [
        Declaration\BootloaderDeclaration::TYPE => [
            'namespace' => 'Shared\\Infrastructure\\Spiral\\Bootloader',
        ],
        Declaration\ConfigDeclaration::TYPE => [
            'namespace' => 'Shared\\Infrastructure\\Spiral\\Configuration',
        ],
        Declaration\ControllerDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Infrastructure\\Spiral\\Http\\Controller',
        ],
        Declaration\FilterDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Infrastructure\\Spiral\\Http\\Filter',
        ],
        Declaration\MiddlewareDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Infrastructure\\Spiral\\Http\\Middleware',
        ],
        Declaration\CommandDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Infrastructure\\Spiral\\Console',
        ],
        Declaration\JobHandlerDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Infrastructure\\Spiral\\Job',
        ],
    ],
];
