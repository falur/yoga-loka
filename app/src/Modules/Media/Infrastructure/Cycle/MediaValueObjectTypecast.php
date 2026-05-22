<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Cycle;

use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\ORM\Parser\CastableInterface;
use Cycle\ORM\Parser\UncastableInterface;

final class MediaValueObjectTypecast implements CastableInterface, UncastableInterface
{
    private readonly ValueObjectCast $valueObjectCast;

    public function __construct()
    {
        $this->valueObjectCast = new ValueObjectCast();
    }

    /**
     * @param array<non-empty-string, mixed> $rules
     * @return array<non-empty-string, mixed>
     */
    #[\Override]
    public function setRules(array $rules): array
    {
        return $this->valueObjectCast->setRules($rules);
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
