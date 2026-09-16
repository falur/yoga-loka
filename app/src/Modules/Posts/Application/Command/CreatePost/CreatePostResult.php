<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CreatePost;

/**
 * Минимальный результат команды: идентификатор созданной записи. Command handler не обращается к
 * Reader — полную форму ответа контроллер дочитывает отдельным GetPostQuery.
 */
final readonly class CreatePostResult
{
    public function __construct(
        public string $postId,
    ) {}
}
