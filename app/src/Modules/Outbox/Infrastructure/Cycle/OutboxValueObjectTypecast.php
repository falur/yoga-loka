<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Cycle;

use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\ORM\Parser\CastableInterface;
use Cycle\ORM\Parser\UncastableInterface;

final class OutboxValueObjectTypecast implements CastableInterface, UncastableInterface
{
    private readonly ValueObjectCast $valueObjectCast;

    public function __construct()
    {
        $this->valueObjectCast = new ValueObjectCast();
    }

    /**
     * @param array<non-empty-string, bool|int|float|string|object|null> $rules
     * @return array<non-empty-string, bool|int|float|string|object|null>
     */
    #[\Override]
    public function setRules(array $rules): array
    {
        /** @var array<non-empty-string, bool|int|float|string|object|null> $remainingRules */
        $remainingRules = $this->valueObjectCast->setRules($rules);

        return $remainingRules;
    }

    /**
     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<int|string, null|bool|int|float|string|\DateTimeInterface|object>
     */
    #[\Override]
    public function cast(array $data): array
    {
        return $this->valueObjectCast->cast($data);
    }

    /**
     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<int|string, bool|int|float|string|\DateTimeInterface|null>
     */
    #[\Override]
    public function uncast(array $data): array
    {
        return $this->valueObjectCast->uncast($data);
    }
}
