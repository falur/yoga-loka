<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Действие над записью или комментарием доступно только их автору.
 */
final class NotAuthorException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.posts.forbidden');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 403;
    }
}
