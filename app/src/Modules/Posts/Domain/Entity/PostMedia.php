<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMediaId;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Repository\PostMediaRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Relation\BelongsTo;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_media',
    table: 'post_media',
    repository: PostMediaRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class PostMedia
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostMediaId::class)]
    public private(set) PostMediaId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    #[Column(type: 'uuid', name: 'media_id', typecast: PostMediaReference::class)]
    public private(set) PostMediaReference $media;

    #[Column(type: 'integer', typecast: MediaPosition::class)]
    public private(set) MediaPosition $position;

    #[BelongsTo(target: Post::class, innerKey: 'post_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Post $post;

    public static function create(Post $post, PostMediaReference $media, MediaPosition $position): self
    {
        $postMedia = new self();
        $postMedia->id = PostMediaId::generate();
        $postMedia->post = $post;
        $postMedia->postId = $post->id;
        $postMedia->media = $media;
        $postMedia->position = $position;
        $postMedia->initializeTimestamps();

        return $postMedia;
    }
}
