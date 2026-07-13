<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\FindMediaUrl;

use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Repository\MediaRepository;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Best-effort разрешение всех URL медиа по его id: оригинал (если не удалён) и все конверсии.
 * Грузит медиа вместе с конверсиями одним набором запросов и делегирует построение URL в
 * MediaUrlService. Возвращает null, если медиа нет или оно не финализировано, чтобы вызывающий
 * подставил значение по умолчанию (аватар в профиле) без try-catch. Оговорка: невалидный переданный
 * presignedTtlSeconds (например, явный 0) — ошибка входа, она бросается, а не превращается в null.
 *
 * Для модулей, которые уже держат сущность Media загруженной (например, лента Posts через relation),
 * есть прямой путь MediaUrlService::getUrls(Media) — без повторной загрузки.
 *
 * Аватар профиля (User) разрешает ссылки через этот путь: набор MediaUrlsResult (оригинал + все
 * конверсии) отдаётся одним свойством avatar. Лента Posts строит такой же полный набор из уже
 * загруженной сущности напрямую через MediaUrlService::getUrls.
 */
final readonly class FindMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaUrlServiceContract $mediaUrlService,
    ) {}

    #[LogOperation]
    public function handle(FindMediaUrlQuery $query): MediaUrlsResult|null
    {
        $media = $this->mediaRepository->findByIdWithConversions(MediaId::fromString($query->mediaId));

        if ($media === null) {
            return null;
        }

        return $this->mediaUrlService->getUrls(media: $media, presignedTtlSeconds: $query->presignedTtlSeconds);
    }
}
