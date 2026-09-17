<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Комментарий не найден или удалён.
 */
final class CommentNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.posts.comment_not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
