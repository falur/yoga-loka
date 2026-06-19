<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Прикладная политика доступа к записи (не доменный инвариант сущности): кому запись видна и над
 * какой записью допустимо действие (лайк/комментарий/репост). Held в Application, потому что это
 * правило сценариев чтения/действий, а не состояние самой записи.
 */
final class PostVisibilityPolicy
{
    /**
     * Запись видна: не удалена и опубликована, либо это черновик её владельца. Заблокированная
     * (модерация) не видна никому.
     */
    public static function isVisibleTo(Post $post, UserId $viewer): bool
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
    public static function isActionable(Post $post): bool
    {
        return !$post->deletion->isDeleted() && $post->status === PostStatus::Published;
    }
}
