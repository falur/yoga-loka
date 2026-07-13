<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Media\Domain\Entity\Media;
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
    public private(set) PostMediaReference $mediaId;

    #[Column(type: 'integer', typecast: MediaPosition::class)]
    public private(set) MediaPosition $position;

    #[BelongsTo(target: Post::class, innerKey: 'post_id', outerKey: 'id', fkOnDelete: 'CASCADE')]
    public private(set) Post $post;

    /**
     * Ссылка на медиа модуля Media. Media — универсальный (foundational) модуль, на сущности которого
     * другим модулям разрешено держать relation на чтение (см. docs/arch.md). cascade: false — Posts
     * не сохраняет и не меняет Media; fkCreate/indexCreate: false — FK media_id уже создан миграцией
     * post_media, повторно его не заводим. Запись идёт по колонке mediaId, связь — для eager-load при
     * сборке URL: лента строит оригинал через MediaUrlService::getUrls (берёт original из набора), тот
     * же метод отдаёт и полный набор оригинал + конверсии (например для FindMediaUrl). Доступ без
     * eager-load вызовет ленивую подгрузку.
     *
     * create() эту связь НЕ инициализирует (в отличие от $post): вложение записывается по колонке mediaId,
     * а сама сущность Media в сценарии создания недоступна (вызывающий держит только идентификатор). Поэтому
     * $media безопасен лишь после ORM-гидрации — чтение через PostMediaRepository с eager-load media.*;
     * обращение к ->media на только что созданном через create() экземпляре до гидрации бросит Error
     * (свойство non-nullable без значения по умолчанию).
     *
     * Устойчивость ленты к отсутствующей строке медиа держится на инварианте схемы, а не на коде: FK
     * media_id стоит с ON DELETE RESTRICT (миграция post_media), поэтому используемое медиа нельзя удалить
     * и связь у гидрированной строки всегда разрешается. Ослабление инварианта (снятие RESTRICT, жёсткое
     * удаление в обход Media-сценария, ручная чистка данных) оставит осиротевший media_id, и обращение к
     * ->media уронит чтение ленты — защита здесь на схеме БД, а не на проверке в коде.
     */
    #[BelongsTo(
        target: Media::class,
        innerKey: 'media_id',
        outerKey: 'id',
        cascade: false,
        fkCreate: false,
        indexCreate: false,
    )]
    public private(set) Media $media;

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
