<?php

declare(strict_types=1);

/**
 * Bootstrap PHPUnit/ParaTest.
 *
 * Выполняется до подключения автозагрузчика и до загрузки приложения. По
 * `TEST_TOKEN` выбирает изолированные ресурсы worker-а ParaTest:
 * базу PostgreSQL и MinIO bucket. Без `TEST_TOKEN` остаются базовые ресурсы
 * `yoga_loka_test` / `yoga-loka-test`.
 *
 * Переменные выставляются через putenv()/$_ENV/$_SERVER, потому что Spiral
 * Environment читает их при создании kernel и не перетирает значениями из .env.
 */

$token = \getenv('TEST_TOKEN');

if ($token === false) {
    $token = '';
}

if ($token !== '' && !\in_array($token, ['1', '2', '3', '4'], true)) {
    \fwrite(
        \STDERR,
        \sprintf("TEST_TOKEN должен быть пустым или числом от 1 до 4, получено: %s\n", $token),
    );

    exit(1);
}

$suffix = $token === '' ? '' : '_' . $token;
$bucketSuffix = $token === '' ? '' : '-' . $token;

// Кэши Cycle schema и tokenizer targets обязаны быть включены в тестах: warmup
// прогревает их один раз, иначе каждый boot kernel пере-сканирует app/src (~2 с).
// Форсируем здесь, потому что .env держит их выключенными для dev-автообновления,
// а docker-compose грузит .env в окружение контейнера и phpunit.xml без force его
// не перетирает.
$testEnv = [
    'TOKENIZER_CACHE_TARGETS' => 'true',
    'CYCLE_SCHEMA_CACHE' => 'true',
    'DB_DATABASE' => 'yoga_loka_test' . $suffix,
    'S3_BUCKET' => 'yoga-loka-test' . $bucketSuffix,
    'S3_TEST_BUCKET' => 'yoga-loka-test' . $bucketSuffix,
];

foreach ($testEnv as $name => $value) {
    \putenv(\sprintf('%s=%s', $name, $value));
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require \dirname(__DIR__) . '/vendor/autoload.php';
