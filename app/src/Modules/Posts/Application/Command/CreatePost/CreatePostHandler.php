<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CreatePost;

use App\Modules\Posts\Application\Post\PostContentComposer;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Создание записи: сразу опубликованной или как черновик. Теги, вложение медиа (проверка + перевод
 * в permanent) и упоминания выполняются в той же транзакции (вложенный dispatch -> SAVEPOINT,
 * решение 17 — атомарно), поэтому при откате осиротевших тегов/медиа не возникает. Упомянутым
 * (кроме автора) стейджится post_mention.
 */
final readonly class CreatePostHandler
{
    public function __construct(
        private PostContentComposer $composer,
        private PostViewAssembler $postViewAssembler,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CreatePostCommand $command): PostView
    {
        $authUserId = UserId::fromString($command->authUserId);
        $tagIds = $this->composer->resolveTags(texts: $command->tags, creatorUserId: $command->authUserId);

        $post = Post::create(
            userId: $authUserId,
            text: $command->text !== null ? PostText::fromString($command->text) : PostText::none(),
            status: $command->draft ? PostStatus::Draft : PostStatus::Published,
            attachmentType: $command->mediaIds === [] ? AttachmentType::None : AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
        $this->entityManager->persist($post);

        $this->composer->attachMedia(post: $post, mediaIds: $command->mediaIds, ownerUserId: $command->authUserId);
        $this->composer->attachTags(post: $post, tagIds: $tagIds);
        $this->composer->attachPostMentions(post: $post, mentionIds: $command->mentions, actorUserId: $command->authUserId);

        $this->entityManager->run();

        $this->logger->debug(message: 'Запись создана.', context: [
            'postId' => $post->id->value(),
            'status' => $post->status->value,
        ]);

        return $this->postViewAssembler->fromPost(post: $post, viewer: $authUserId);
    }
}
