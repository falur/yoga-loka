<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractRangedIntegerValue;

final readonly class RepostsCount extends AbstractRangedIntegerValue
{
    protected const int MIN = 0;
    protected const int MAX = PHP_INT_MAX;
    protected const string NAME = 'Количество репостов';

    public static function zero(): self
    {
        return self::fromInt(0);
    }

    public function increment(): self
    {
        if ($this->value() >= self::MAX) {
            throw new InvalidDomainValueException('Количество репостов превышено.');
        }

        return self::fromInt($this->value() + 1);
    }

    public function decrement(): self
    {
        if ($this->value() <= self::MIN) {
            throw new InvalidDomainValueException('Количество репостов не может быть отрицательным.');
        }

        return self::fromInt($this->value() - 1);
    }
}
