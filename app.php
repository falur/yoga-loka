<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Spiral\DirectoryAlias;
use App\Shared\Infrastructure\Spiral\Kernel;
use Spiral\Core\Container;
use Spiral\Core\Options;

// Базовые настройки окружения для локального запуска.
\mb_internal_encoding('UTF-8');
\error_reporting(E_ALL ^ E_DEPRECATED);
\ini_set('display_errors', 'stderr');

// Регистрируем автозагрузчик Composer.
require __DIR__ . '/vendor/autoload.php';

// Инициализируем общий контейнер, биндинги и директории.
$options = new Options();
$options->allowSingletonsRebinding = false;
$options->validateArguments = false;
$container = new Container(options: $options);
$app = Kernel::create(
    directories: [DirectoryAlias::Root->value => __DIR__],
    container: $container,
)->run();

if ($app === null) {
    exit(255);
}

$code = (int) $app->serve();
exit($code);
