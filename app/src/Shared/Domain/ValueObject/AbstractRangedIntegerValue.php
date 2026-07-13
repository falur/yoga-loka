<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

abstract readonly class AbstractRangedIntegerValue extends AbstractIntegerValue
{
    protected const int MIN = 0;
    protected const int MAX = 0;

    /**
     * Проверяет, попадает ли значение в допустимый доменный диапазон [MIN; MAX] без создания VO.
     * Нужно на границе Application, чтобы перевести невалидный вход в ValidationException (422)
     * до построения VO, которое бросило бы InvalidDomainValueException (500).
     */
    public static function supports(int $value): bool
    {
        return $value >= static::MIN && $value <= static::MAX;
    }

    #[\Override]
    protected static function assertValid(int $value): void
    {
        if ($value < static::MIN || $value > static::MAX) {
            throw new InvalidDomainValueException(
                \sprintf('%s должно быть от %d до %d.', static::NAME, static::MIN, static::MAX),
            );
        }
    }
}
