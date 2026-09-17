<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMediaId;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Shared\Domain\Trait\HasTimestamps;

/**
 * Внутренняя сущность агрегата Post: существует только у своей записи, создаётся и удаляется
 * вместе с ней, отдельного репозитория не имеет. Ссылка на запись хранится только как postId —
 * объект Post не хранится (в форме хранения это была связь BelongsTo к корню, нужная лишь для
 * каскада и схемы, а не доменному поведению: ни один потребитель Application/Infrastructure не
 * читал это поле, кроме собственного unit-теста, адаптированного вместе с этим изменением).
 *
 * Ссылка на медиа соседнего модуля — собственный объект-значение Posts в колонке media_id,
 * без ORM-связи, навигации и внешнего ключа на таблицу media: чужая таблица принадлежит
 * чужому модулю (docs/arch.md, «Владение данными»). Данные медиа для ответа Posts дочитывает
 * одним пакетным вызовом публичного контракта соседа (GetPostHandler/GetMyFeedHandler ->
 * MediaContract::urlsByIds);
 * недоступное медиа в ответ контракта не приходит и вложение мягко исключается из ответа.
 */
final class PostMedia
{
    use HasTimestamps;

    public private(set) PostMediaId $id;

    public private(set) PostId $postId;

    public private(set) PostMediaReference $mediaId;

    public private(set) MediaPosition $position;

    public static function create(Post $post, PostMediaReference $mediaId, MediaPosition $position): self
    {
        $postMedia = new self();
        $postMedia->id = PostMediaId::generate();
        $postMedia->postId = $post->id;
        $postMedia->mediaId = $mediaId;
        $postMedia->position = $position;
        $postMedia->initializeTimestamps();

        return $postMedia;
    }

    public static function restore(
        PostMediaId $id,
        PostId $postId,
        PostMediaReference $mediaId,
        MediaPosition $position,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $postMedia = new self();
        $postMedia->id = $id;
        $postMedia->postId = $postId;
        $postMedia->mediaId = $mediaId;
        $postMedia->position = $position;
        $postMedia->createdAt = $createdAt;
        $postMedia->updatedAt = $updatedAt;

        return $postMedia;
    }
}
