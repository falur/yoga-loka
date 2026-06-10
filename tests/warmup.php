<?php

declare(strict_types=1);

use Cycle\ORM\SchemaInterface;
use Spiral\Core\Container;
use Tests\App\TestKernel;
use Tests\TestRuntime;

// Прогрев выполняется вне PHPUnit, поэтому флаги тестового окружения и
// кэширования выставляем явно до подключения автозагрузчика и kernel.
foreach ([
    'APP_ENV' => 'testing',
    'CYCLE_SCHEMA_CACHE' => 'true',
    'TOKENIZER_CACHE_TARGETS' => 'true',
] as $name => $value) {
    \putenv(\sprintf('%s=%s', $name, $value));
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require \dirname(__DIR__) . '/vendor/autoload.php';

$root = \dirname(__DIR__);
$runtime = TestRuntime::runtimeDirectory($root);
$token = \getenv('TEST_TOKEN');
$label = $token === false || $token === '' ? 'базовый режим' : \sprintf('worker %s', $token);

\fwrite(\STDOUT, \sprintf("[warmup] Прогрев Cycle schema: %s, runtime=%s\n", $label, $runtime));

$container = new Container();
$kernel = TestKernel::create(
    directories: TestRuntime::directories($root),
    container: $container,
);

if ($kernel->run() === null) {
    \fwrite(\STDERR, "[warmup] Не удалось загрузить тестовый kernel для прогрева Cycle schema\n");

    exit(1);
}

// Принудительно получаем схему, чтобы Cycle скомпилировал и записал её в cache.
$container->get(SchemaInterface::class);

$cacheFile = $runtime . '/cache/cycle.php';

if (!\is_file($cacheFile)) {
    \fwrite(\STDERR, \sprintf("[warmup] Cycle schema cache не создан: %s\n", $cacheFile));

    exit(1);
}

\fwrite(\STDOUT, \sprintf(
    "[warmup] Cycle schema cache готов: %s (%d байт)\n",
    $cacheFile,
    (int) \filesize($cacheFile),
));
