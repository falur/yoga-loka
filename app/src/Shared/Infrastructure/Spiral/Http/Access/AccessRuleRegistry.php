<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Http\Access;

/**
 * Реестр правил доступа: связывает класс объявления доступа с правилом, которое его проверяет.
 *
 * Реестр наполняет bootloader модуля-владельца доступа, поэтому общая часть HTTP-границы
 * не знает ни одного бизнес-модуля.
 */
final class AccessRuleRegistry
{
    /** @var array<class-string, AccessRule> */
    private array $rules = [];

    /**
     * @param class-string $declarationClass класс атрибута-объявления доступа
     */
    public function register(string $declarationClass, AccessRule $rule): void
    {
        $this->rules[$declarationClass] = $rule;
    }

    /**
     * Правило объявления или `null`, если такой атрибут объявлением доступа не является.
     */
    public function ruleFor(string $declarationClass): AccessRule|null
    {
        return $this->rules[$declarationClass] ?? null;
    }
}
