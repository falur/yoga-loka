<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrls;

use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Application\Dto\MediaUrlsResultCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Пакетный аналог FindMediaUrl: грузит набор медиа вместе с конверсиями одним набором запросов и
 * строит URL для каждого через MediaUrlService. Возвращает коллекцию, ключ — id медиа; недоступные
 * медиа (не найдены или не финализированы) в набор не попадают, вызывающий трактует их отсутствие как
 * «медиа недоступно» и подставляет значение по умолчанию (аватара нет) без try-catch.
 */
final readonly class FindMediaUrlsHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaUrlServiceContract $mediaUrlService,
    ) {}

    #[LogOperation]
    public function handle(FindMediaUrlsQuery $query): MediaUrlsResultCollection
    {
        if ($query->mediaIds === []) {
            return new MediaUrlsResultCollection();
        }

        $media = $this->mediaRepository->findByIdsWithConversions(...\array_map(
            static fn(string $mediaId): MediaId => MediaId::fromString($mediaId),
            $query->mediaIds,
        ));

        return new MediaUrlsResultCollection(
            $media->toBase()->mapWithKeys(
                /**
                 * @return array<string, MediaUrlsResult>
                 */
                function (Media $media) use ($query): array {
                    $urls = $this->mediaUrlService->getUrls(
                        media: $media,
                        presignedTtlSeconds: $query->presignedTtlSeconds,
                    );

                    return $urls === null ? [] : [$media->id->value() => $urls];
                },
            ),
        );
    }
}
