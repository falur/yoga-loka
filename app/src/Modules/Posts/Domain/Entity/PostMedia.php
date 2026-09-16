<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMediaId;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_media',
    table: 'post_media',
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class PostMedia
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostMediaId::class)]
    public private(set) PostMediaId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    /**
     * Ссылка на медиа соседнего модуля — собственный объект-значение Posts в колонке media_id,
     * без ORM-связи, навигации и внешнего ключа на таблицу media: чужая таблица принадлежит
     * чужому модулю (docs/arch.md, «Владение данными»). Данные медиа для ответа Posts дочитывает
     * одним пакетным вызовом публичного контракта соседа (PostViewAssembler -> MediaContract::urlsByIds);
     * недоступное медиа в ответ контракта не приходит и вложение мягко исключается из ответа.
     */
    #[Column(type: 'uuid', name: 'media_id', typecast: PostMediaReference::class)]
    public private(set) PostMediaReference $mediaId;

    #[Column(type: 'integer', typecast: MediaPosition::class)]
    public private(set) MediaPosition $position;

    #[BelongsTo(target: Post::class, innerKey: 'post_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Post $post;

    public static function create(Post $post, PostMediaReference $mediaId, MediaPosition $position): self
    {
        $postMedia = new self();
        $postMedia->id = PostMediaId::generate();
        $postMedia->post = $post;
        $postMedia->postId = $post->id;
        $postMedia->mediaId = $mediaId;
        $postMedia->position = $position;
        $postMedia->initializeTimestamps();

        return $postMedia;
    }
}
