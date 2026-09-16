<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\PublishPost;

/**
 * Минимальный результат команды: идентификатор опубликованной записи. Command handler не
 * обращается к Reader — полную форму ответа контроллер дочитывает отдельным GetPostQuery.
 */
final readonly class PublishPostResult
{
    public function __construct(
        public string $postId,
    ) {}
}
