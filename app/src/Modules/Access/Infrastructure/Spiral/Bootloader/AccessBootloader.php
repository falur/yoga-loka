<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Spiral\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;

/**
 * Точка подключения модуля Access к приложению.
 *
 * Связывать сегодня нечего: у модуля нет ни своих реализаций контрактов, ни своих шаблонов,
 * ни своей секции конфигурации в собственном владении. Следующие волны переезда добавят сюда
 * конфигурацию модуля, путь его миграций, переводы и привязки контрактов к реализациям.
 */
final class AccessBootloader extends Bootloader {}
