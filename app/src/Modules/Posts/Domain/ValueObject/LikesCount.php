<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class LikesCount extends AbstractIntegerValue
{
    protected const int MIN = 0;
    protected const int MAX = PHP_INT_MAX;
    protected const string NAME = 'Количество лайков';

    public static function zero(): self
    {
        return self::fromInt(0);
    }

    public function increment(): self
    {
        if ($this->value() >= self::MAX) {
            throw new InvalidDomainValueException('Количество лайков превышено.');
        }

        return self::fromInt($this->value() + 1);
    }

    public function decrement(): self
    {
        if ($this->value() <= self::MIN) {
            throw new InvalidDomainValueException('Количество лайков не может быть отрицательным.');
        }

        return self::fromInt($this->value() - 1);
    }
}
