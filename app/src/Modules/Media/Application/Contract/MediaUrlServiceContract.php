<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Domain\Entity\Media;

/**
 * Контракт построения URL из УЖЕ загруженной сущности Media. Принимает доменную сущность Media и
 * возвращает Application DTO.
 *
 * Срок presigned-ссылки скачивания: значение по умолчанию читает реализация из конфига, а вызывающий
 * может переопределить его на конкретный вызов через $presignedTtlSeconds.
 *
 * getUrls — полный набор (оригинал, если не удалён, и все конверсии). Потребитель отдаёт набор целиком
 * (лента Posts, аватар профиля), а тот, кому нужна одна ссылка (уведомления, пуш), берёт original.
 *
 * Реализация — App\Modules\Media\Infrastructure\Storage\MediaUrlService.
 */
interface MediaUrlServiceContract
{
    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null;
}
