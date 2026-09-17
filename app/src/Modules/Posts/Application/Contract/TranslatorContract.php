<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Contract;

/**
 * Порт перевода сценариев Posts: текст уведомления собирается через него, а не через
 * фреймворк-интерфейс напрямую. Сигнатура зеркалит метод `trans()` переводчика фреймворка Spiral.
 */
interface TranslatorContract
{
    /**
     * @param array<string, scalar|null> $parameters
     */
    public function trans(string $id, array $parameters, string $domain, string $locale): string;
}
