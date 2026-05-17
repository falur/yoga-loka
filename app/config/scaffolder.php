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
            'namespace' => 'Infrastructure\\Framework\\Bootloader',
        ],
        Declaration\ConfigDeclaration::TYPE => [
            'namespace' => 'Infrastructure\\Configuration',
        ],
        Declaration\ControllerDeclaration::TYPE => [
            'namespace' => 'Endpoint\\Web',
        ],
        Declaration\FilterDeclaration::TYPE => [
            'namespace' => 'Endpoint\\Web\\Filter',
        ],
        Declaration\MiddlewareDeclaration::TYPE => [
            'namespace' => 'Endpoint\\Web\\Middleware',
        ],
        Declaration\CommandDeclaration::TYPE => [
            'namespace' => 'Endpoint\\Console',
        ],
        Declaration\JobHandlerDeclaration::TYPE => [
            'namespace' => 'Endpoint\\Job',
        ],
    ],
];
