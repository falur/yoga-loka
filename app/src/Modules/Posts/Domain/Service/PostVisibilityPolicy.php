<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Service;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Правило видимости и доступности записи: связывает запись, статус и зрителя, поэтому не
 * принадлежит ни записи, ни пользователю. Зависимостей нет (ни Repository, ни Reader, ни Public
 * соседей) и быть не должно: сценарий получает политику экземпляром через конструктор.
 */
final readonly class PostVisibilityPolicy
{
    /**
     * Запись видна: не удалена и опубликована, либо это черновик её владельца. Заблокированная
     * (модерация) не видна никому.
     */
    public function isVisibleTo(Post $post, UserId $viewer): bool
    {
        if ($post->deletion->isDeleted()) {
            return false;
        }

        return match ($post->status) {
            PostStatus::Published => true,
            PostStatus::Draft => $post->userId->equals($viewer),
            PostStatus::Blocked => false,
        };
    }

    /**
     * Над записью допустимо действие (лайк/комментарий/репост) только если она опубликована и не
     * удалена; черновик и заблокированная — нет.
     */
    public function isActionable(Post $post): bool
    {
        return !$post->deletion->isDeleted() && $post->status === PostStatus::Published;
    }
}
