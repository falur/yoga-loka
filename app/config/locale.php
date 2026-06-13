<?php

declare(strict_types=1);

/**
 * Конфигурация локали запроса.
 *
 * supported — белый список поддерживаемых локалей, с которым пересекается Accept-Language.
 * default — базовая локаль, когда совпадений с supported нет. Читается из той же переменной
 * LOCALE, что и translator, чтобы базовая локаль приложения была единым источником.
 */
return [
    'supported' => ['ru', 'en'],
    'default' => \env('LOCALE', 'ru'),
];
