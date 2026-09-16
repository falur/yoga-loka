<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * В списке упоминаний есть пользователь, которого не существует.
 */
final class MentionedUserNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.posts.mention_user_not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
