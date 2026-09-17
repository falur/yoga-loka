<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Запись не найдена, удалена, заблокирована или недоступна зрителю.
 */
final class PostNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.posts.not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
