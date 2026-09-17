<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Cycle;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostBlockRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentLikeEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CycleCommentMentionEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostBlockEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostLikeEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMediaEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostMentionEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity\CyclePostTagEntity;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\CommentMapper;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\PostBlockMapper;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\PostMapper;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Entity\CycleTagEntity;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper\TagMapper;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Entity\CycleUserEntity;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов модуля Posts: реальные строки User/Media/Tag для FK, persist-хелпер
 * с немедленным flush в нужном порядке и доступ к репозиториям модуля.
 *
 * User, Media, Tag и все 9 сущностей Posts — чистые доменные сущности без Cycle-разметки, поэтому
 * не могут быть сохранены через generic entityManager()->persist(): EntityManager не знает их роль.
 * persist()/stage()/delete() переводят их в Cycle Entity через Mapper соответствующего модуля перед
 * постановкой в очередь EntityManager — тот же приём, что применяют TagsRepositoryTestCase (фаза 1)
 * и трейт PersistsMedia модуля Notifications для своих модулей. Перед каждым сохранением
 * существующая строка ищется по PK (findCycleEntityByClass()), чтобы повторный persist() уже
 * сохранённой сущности выполнял UPDATE, а не падал на дубликате первичного ключа — родная identity
 * map Cycle доступна только внутри одного findById(), домен её больше не наследует.
 */
abstract class PostsRepositoryTestCase extends DatabaseTestCase
{
    private int $userCounter = 0;

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Сохраняет одну сущность и сразу делает flush, чтобы кросс-табличные FK
     * (без Cycle relation) выполнялись в правильном порядке вставок.
     */
    protected function persist(object $entity): void
    {
        $this->stage($entity);
        $this->entityManager()->run();
    }

    /**
     * Ставит сущность в очередь EntityManager без прогона — для сценариев, которым нужно накопить
     * несколько сущностей перед одним общим run() (например, проверка уникального индекса).
     */
    protected function stage(object $entity): void
    {
        $this->entityManager()->persist($this->toCycleEntity($entity));
    }

    /**
     * Удаляет сущность по её текущей строке в базе (ищет Cycle Entity по PK, домен больше не хранит
     * трекнутый Cycle-объект) и сразу делает flush.
     */
    protected function delete(object $entity): void
    {
        $cycleEntity = $this->findExistingCycleEntity($entity);

        if ($cycleEntity !== null) {
            $this->entityManager()->delete($cycleEntity);
        }

        $this->entityManager()->run();
    }

    protected function createUser(): User
    {
        $this->userCounter++;

        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('post.user%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('yoga.user%d', $this->userCounter)),
            locale: Locale::Ru,
        );
    }

    protected function createMedia(UserId $uploadedBy): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: $uploadedBy,
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    protected function postRepository(): PostRepository
    {
        return $this->getContainer()->get(PostRepository::class);
    }

    protected function commentRepository(): CommentRepository
    {
        return $this->getContainer()->get(CommentRepository::class);
    }

    protected function postBlockRepository(): PostBlockRepository
    {
        return $this->getContainer()->get(PostBlockRepository::class);
    }

    private function toCycleEntity(object $entity): object
    {
        return match (true) {
            $entity instanceof User => $this->getContainer()->get(UserMapper::class)->toCycleEntity(
                user: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleUserEntity::class, $entity->id->value()),
            ),
            $entity instanceof Media => $this->getContainer()->get(MediaMapper::class)->toCycleEntity(
                media: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleMediaEntity::class, $entity->id->value()),
            ),
            $entity instanceof Tag => $this->getContainer()->get(TagMapper::class)->toCycleEntity(
                tag: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleTagEntity::class, $entity->id->value()),
            ),
            $entity instanceof Post => $this->getContainer()->get(PostMapper::class)->toCycleEntity(
                post: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostEntity::class, $entity->id->value()),
            ),
            $entity instanceof Comment => $this->getContainer()->get(CommentMapper::class)->toCycleEntity(
                comment: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleCommentEntity::class, $entity->id->value()),
            ),
            $entity instanceof PostMedia => $this->getContainer()->get(PostMapper::class)->toPostMediaCycleEntity(
                postMedia: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostMediaEntity::class, $entity->id->value()),
            ),
            $entity instanceof PostTag => $this->getContainer()->get(PostMapper::class)->toPostTagCycleEntity(
                postTag: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostTagEntity::class, $entity->id->value()),
            ),
            $entity instanceof PostMention => $this->getContainer()->get(PostMapper::class)->toPostMentionCycleEntity(
                postMention: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostMentionEntity::class, $entity->id->value()),
            ),
            $entity instanceof PostLike => $this->getContainer()->get(PostMapper::class)->toPostLikeCycleEntity(
                postLike: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostLikeEntity::class, $entity->id->value()),
            ),
            $entity instanceof CommentLike => $this->getContainer()->get(CommentMapper::class)->toCommentLikeCycleEntity(
                commentLike: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleCommentLikeEntity::class, $entity->id->value()),
            ),
            $entity instanceof CommentMention => $this->getContainer()->get(CommentMapper::class)->toCommentMentionCycleEntity(
                commentMention: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleCommentMentionEntity::class, $entity->id->value()),
            ),
            $entity instanceof PostBlock => $this->getContainer()->get(PostBlockMapper::class)->toCycleEntity(
                postBlock: $entity,
                cycleEntity: $this->findCycleEntityByClass(CyclePostBlockEntity::class, $entity->id->value()),
            ),
            default => throw new \LogicException(\sprintf('Не настроено сохранение сущности %s в тестах.', $entity::class)),
        };
    }

    private function findExistingCycleEntity(object $entity): object|null
    {
        return match (true) {
            $entity instanceof User => $this->findCycleEntityByClass(CycleUserEntity::class, $entity->id->value()),
            $entity instanceof Media => $this->findCycleEntityByClass(CycleMediaEntity::class, $entity->id->value()),
            $entity instanceof Tag => $this->findCycleEntityByClass(CycleTagEntity::class, $entity->id->value()),
            $entity instanceof Post => $this->findCycleEntityByClass(CyclePostEntity::class, $entity->id->value()),
            $entity instanceof Comment => $this->findCycleEntityByClass(CycleCommentEntity::class, $entity->id->value()),
            default => throw new \LogicException(\sprintf('Не настроено удаление сущности %s в тестах.', $entity::class)),
        };
    }

    /**
     * @template TCycleEntity of object
     *
     * @param class-string<TCycleEntity> $cycleClass
     *
     * @return TCycleEntity|null
     */
    private function findCycleEntityByClass(string $cycleClass, string $id): object|null
    {
        /** @var ORMInterface $orm */
        $orm = $this->getContainer()->get(ORMInterface::class);

        /** @var Select<TCycleEntity> $select */
        $select = new Select($orm, $cycleClass);

        return $select->wherePK($id)->fetchOne();
    }
}
