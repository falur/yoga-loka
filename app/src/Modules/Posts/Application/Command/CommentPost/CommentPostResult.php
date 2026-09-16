<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CommentPost;

/**
 * Минимальный результат команды: идентификатор созданного комментария. Command handler не
 * обращается к Reader — полную форму ответа контроллер дочитывает отдельным GetCommentQuery.
 */
final readonly class CommentPostResult
{
    public function __construct(
        public string $commentId,
    ) {}
}
