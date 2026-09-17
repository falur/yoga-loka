<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CreatePost;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
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
        private PostRepository $postRepository,
        private PostContentComposer $composer,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CreatePostCommand $command): CreatePostResult
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

        $media = $this->composer->attachMedia(post: $post, mediaIds: $command->mediaIds, ownerUserId: $command->authUserId);
        $tags = $this->composer->attachTags(post: $post, tagIds: $tagIds);
        $mentions = $this->composer->attachPostMentions(post: $post, mentionIds: $command->mentions, actorUserId: $command->authUserId);

        $this->postRepository->saveWithAttachments(post: $post, media: $media, tags: $tags, mentions: $mentions);

        $this->logger->debug(message: 'Запись создана.', context: [
            'postId' => $post->id->value(),
            'status' => $post->status->value,
        ]);

        return new CreatePostResult(postId: $post->id->value());
    }
}
