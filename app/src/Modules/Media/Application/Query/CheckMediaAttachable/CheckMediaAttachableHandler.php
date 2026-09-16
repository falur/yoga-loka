<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\CheckMediaAttachable;

use App\Modules\Media\Domain\Exception\MediaAccessDeniedException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Exception\MediaNotReadyForAttachmentException;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Проверяет, можно ли вложить набор медиа в запись: каждое должно существовать (иначе 404),
 * принадлежать владельцу (иначе 403) и быть обработанным/готовым (иначе 422). Набор обходится в
 * порядке передачи, поэтому ошибку даёт первое непригодное медиа. Вызывается до MakeMediaPermanent,
 * поэтому тот всегда получает готовые медиа.
 */
final readonly class CheckMediaAttachableHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
    ) {}

    #[LogOperation]
    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
    {
        $owner = UserId::fromString($query->ownerUserId);

        foreach ($query->mediaIds as $mediaId) {
            $media = $this->mediaRepository->findById(MediaId::fromString($mediaId))
                ?? throw new MediaNotFoundException();

            if (!$media->uploadedById->equals($owner)) {
                throw new MediaAccessDeniedException();
            }

            if (!$media->isReady()) {
                throw new MediaNotReadyForAttachmentException();
            }
        }

        return new MediaAttachableResult(mediaIds: $query->mediaIds);
    }
}
