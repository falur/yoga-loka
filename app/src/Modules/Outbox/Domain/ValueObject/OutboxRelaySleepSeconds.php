<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractRangedIntegerValue;

final readonly class OutboxRelaySleepSeconds extends AbstractRangedIntegerValue
{
    // Нижняя граница 1 секунда осознанно запрещает busy-spin: при --loop --sleep=0
    // цикл relay на пустой очереди крутился бы без паузы. Минимум гарантирует паузу.
    protected const int MIN = 1;
    protected const int MAX = 3600;
    protected const string NAME = 'Пауза outbox relay в секундах';
}
