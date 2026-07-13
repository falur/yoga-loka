<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractIntegerValue;

/**
 * Срок жизни presigned-ссылки в секундах. Домен требует лишь положительности: «сколько живёт ссылка»
 * доменного верхнего предела не имеет. Верхнюю границу (лимит подписи хранилища, S3 SigV4 — 7 суток)
 * держит инфраструктура: MediaConfig отвергает превышение при старте.
 */
final readonly class MediaPresignedTtl extends AbstractIntegerValue
{
    protected const string NAME = 'TTL presigned-ссылки';

    #[\Override]
    protected static function assertValid(int $value): void
    {
        if ($value < 1) {
            throw new InvalidDomainValueException(
                \sprintf('%s должно быть положительным.', self::NAME),
            );
        }
    }
}
