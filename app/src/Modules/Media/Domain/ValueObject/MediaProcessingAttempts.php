<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaProcessingAttempts extends AbstractIntegerValue
{
    protected const int MIN = 0;
    protected const int MAX = 100;
    protected const string NAME = 'Количество попыток обработки';

    public static function zero(): self
    {
        return self::fromInt(0);
    }

    public function increment(): self
    {
        if ($this->value() >= self::MAX) {
            throw new InvalidDomainValueException('Количество попыток обработки превышено.');
        }

        return self::fromInt($this->value() + 1);
    }
}
