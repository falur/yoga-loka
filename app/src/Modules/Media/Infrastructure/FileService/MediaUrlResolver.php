<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\FileService;

use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Строит URL объекта медиа в зафиксированном контексте одного запроса. Способ (прямой публичный
 * URL или presigned со сроком) и единый срок истечения определяются один раз при создании резолвера
 * в MediaUrlService::resolverFor(), поэтому вызывающему остаётся передать только пару (storage, path) —
 * одинаково для оригинала и каждой конверсии.
 */
interface MediaUrlResolver
{
    public function resolve(MediaStorage $storage, MediaPath $path): MediaUrlResult;
}
