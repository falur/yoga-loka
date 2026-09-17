<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Для аудио нужен ровно один профиль преобразования.
 */
final class MediaAudioConversionProfileRequiredException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.audio_conversion_profile_required');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
