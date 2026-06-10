<?php

declare(strict_types=1);

namespace Tests;

use App\Shared\Infrastructure\Framework\DirectoryAlias;

/**
 * Единый источник путей тестового runtime для базового режима и worker-ов ParaTest.
 *
 * Без `TEST_TOKEN` используется базовый каталог `runtime/testing`, с `TEST_TOKEN`
 * — отдельный каталог `runtime/testing-{TEST_TOKEN}`. Тот же выбор делает
 * `tests/warmup.php`, чтобы прогретый Cycle schema cache совпадал с тем, что
 * читают тесты.
 */
final class TestRuntime
{
    public static function runtimeDirectory(string $root): string
    {
        return $root . '/runtime/' . self::suffixed('testing');
    }

    public static function storageDirectory(string $root): string
    {
        return $root . '/runtime/' . self::suffixed('testing-storage');
    }

    /**
     * Директории для тестового kernel: один runtime и cache внутри него,
     * чтобы `cache/cycle.php` лежал в каталоге текущего worker-а.
     *
     * @return array<string, string>
     */
    public static function directories(string $root): array
    {
        $runtime = self::runtimeDirectory($root);

        return [
            DirectoryAlias::Root->value => $root,
            DirectoryAlias::Runtime->value => $runtime,
            DirectoryAlias::Cache->value => $runtime . '/cache',
        ];
    }

    private static function suffixed(string $base): string
    {
        $token = \getenv('TEST_TOKEN');

        if ($token === false || $token === '') {
            return $base;
        }

        return $base . '-' . $token;
    }
}
