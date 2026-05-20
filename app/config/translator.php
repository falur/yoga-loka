<?php

declare(strict_types=1);

/**
 * Конфигурация переводчика.
 *
 * @link https://spiral.dev/docs/advanced-i18n#configuration
 */
return [
    'locale' => \env('LOCALE', 'ru'),
    'fallbackLocale' => \env('LOCALE', 'en'),
    'directory' => \directory('locale'),
    'autoRegister' => \env('DEBUG', true),
];
