<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Автор записи или комментария отсутствует в пакетной карте профилей.
 */
final class PostAuthorNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.posts.author_not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
