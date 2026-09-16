<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Для видео нужен ровно один профиль преобразования.
 */
final class MediaVideoConversionProfileRequiredException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.video_conversion_profile_required');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
