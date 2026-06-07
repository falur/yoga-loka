<?php

declare(strict_types=1);

namespace App\Shared\Domain\Trait;

trait ComparesDateTimeToMicroseconds
{
    /**
     * Единый критерий равенства момента времени: совпадение секунд и микросекунд.
     * Вынесен в общий трейт, чтобы доменные обёртки времени сравнивались одинаково
     * и точность сравнения менялась в одном месте.
     */
    private static function dateTimeEqualsToMicroseconds(
        \DateTimeImmutable $value,
        \DateTimeImmutable $other,
    ): bool {
        return $value->getTimestamp() === $other->getTimestamp()
            && $value->format('u') === $other->format('u');
    }
}
