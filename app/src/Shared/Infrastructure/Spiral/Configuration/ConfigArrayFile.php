<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Exception\ConfigArrayFileException;

/**
 * Подключает файл-массив конфигурации модуля (`{name}.php`) и сужает его возврат к однородной карте.
 *
 * `require` типизирован PHPStan как `mixed` (путь вычисляется динамически), поэтому единственная
 * граница, на которой приложение проверяет форму содержимого файла — эта функция, а не безусловный
 * cast к `array` в каждом bootloader-е, который вызывает `setDefaults()`/`modify()` секции.
 */
final class ConfigArrayFile
{
    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function read(string $path): array
    {
        $data = require $path;

        if (!\is_array($data)) {
            throw ConfigArrayFileException::notAnArray(path: $path);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Сужает значение `\env()` (типизировано PHPStan как `mixed`) к `int`: сам вызов `\env()`
     * остаётся в файле-массиве конфигурации (docs/rules.md — `env()` вызывается только там), эта
     * функция лишь превращает уже полученное значение в проектный тип на границе системы.
     */
    public static function int(mixed $value, int $default): int
    {
        return match (true) {
            \is_int($value) => $value,
            \is_string($value) && \is_numeric($value) => \intval($value),
            default => $default,
        };
    }

    /**
     * Сужает значение `\env()` к `string` по тому же принципу, что и {@see self::int()}.
     */
    public static function string(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }
}
