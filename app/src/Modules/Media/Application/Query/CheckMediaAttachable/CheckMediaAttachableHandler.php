<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Проверяет, можно ли вложить медиа в запись: оно должно существовать (иначе 404), принадлежать
 * владельцу (иначе 403) и быть обработанным/готовым (иначе 422). Вызывается до MakeMediaPermanent,
 * поэтому тот всегда получает готовое медиа.
 */
final readonly class CheckMediaAttachableHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    #[LogOperation]
    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($query->ownerUserId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if (!$media->isReady()) {
            throw new ValidationException('app.media.not_ready');
        }

        return new MediaAttachableResult(mediaId: $media->id->value());
    }
}
