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
            'namespace' => 'Shared\\Infrastructure\\Framework\\Bootloader',
        ],
        Declaration\ConfigDeclaration::TYPE => [
            'namespace' => 'Shared\\Infrastructure\\Configuration',
        ],
        Declaration\ControllerDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Presentation\\Http\\Controller',
        ],
        Declaration\FilterDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Presentation\\Http\\Filter',
        ],
        Declaration\MiddlewareDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Presentation\\Http\\Middleware',
        ],
        Declaration\CommandDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Presentation\\Console',
        ],
        Declaration\JobHandlerDeclaration::TYPE => [
            'namespace' => 'Modules\\System\\Presentation\\Job',
        ],
    ],
];
