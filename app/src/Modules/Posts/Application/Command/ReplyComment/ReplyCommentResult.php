<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\ReplyComment;

/**
 * Минимальный результат команды: идентификатор созданного ответа. Command handler не обращается к
 * Reader — полную форму ответа контроллер дочитывает отдельным GetCommentQuery.
 */
final readonly class ReplyCommentResult
{
    public function __construct(
        public string $commentId,
    ) {}
}
