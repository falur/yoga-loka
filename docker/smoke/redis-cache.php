<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Core\Container;
use Spiral\Core\Options;
use App\Shared\Infrastructure\Spiral\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$root = \dirname(__DIR__, 2);

$options = new Options();
$options->allowSingletonsRebinding = false;
$options->validateArguments = false;
$container = new Container(options: $options);

$kernel = Kernel::create(
    directories: [
        'root' => $root,
    ],
    container: $container,
);

$kernel->run();

$cache = $container
    ->get(CacheStorageProviderInterface::class)
    ->storage('redis');

if (!$cache instanceof CacheInterface) {
    throw new RuntimeException('Redis cache storage не реализует PSR-16 cache.');
}

$key = 'docker-smoke-cache';
$cache->set($key, 'ok', 60);

if ($cache->get($key) !== 'ok') {
    throw new RuntimeException('Redis cache storage не вернул записанное значение.');
}

$cache->delete($key);

echo "Redis cache smoke завершён успешно\n";
