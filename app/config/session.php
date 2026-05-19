<?php

declare(strict_types=1);

use Spiral\Core\Container\Autowire;
use Spiral\Session\Handler\CacheHandler;

/**
 * Конфигурация сессий.
 * @link https://spiral.dev/docs/basics-session
 */
return [
    'lifetime' => (int) \env('SESSION_LIFETIME', 86400),
    'cookie' => \env('SESSION_COOKIE', 'sid'),
    'secure' => true,
    'sameSite' => null,
    'handler' => new Autowire(
        CacheHandler::class,
        [
            'storage' => \env('SESSION_CACHE_STORAGE', 'redis'),
            'ttl' => (int) \env('SESSION_LIFETIME', 86400),
            'prefix' => \env('SESSION_CACHE_PREFIX', 'session:'),
        ],
    ),
];
