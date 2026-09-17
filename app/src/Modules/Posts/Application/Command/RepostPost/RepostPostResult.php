<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\RepostPost;

/**
 * Минимальный результат команды: идентификатор созданного репоста. Command handler не обращается к
 * Reader — полную форму ответа контроллер дочитывает отдельным GetPostQuery.
 */
final readonly class RepostPostResult
{
    public function __construct(
        public string $postId,
    ) {}
}
