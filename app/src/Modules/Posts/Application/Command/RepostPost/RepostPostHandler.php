<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\RepostPost;

use App\Modules\Posts\Application\Command\CreatePost\PostContentComposer;
use App\Modules\Posts\Application\Command\CreatePost\PostNotificationType;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Репост = цитата: новая опубликованная запись со ссылкой на оригинал и своим текстом/медиа/тегами.
 * Цель должна быть видимой и доступной для действия (опубликована, не удалена) — иначе 404. У
 * оригинала увеличивается счётчик репостов; автору оригинала (кроме self) стейджится post_repost.
 */
final readonly class RepostPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private PostContentComposer $composer,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RepostPostCommand $command): RepostPostResult
    {
        $authUserId = UserId::fromString($command->authUserId);

        $original = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new PostNotFoundException();

        if (!PostVisibilityPolicy::isActionable($original)) {
            throw new PostNotFoundException();
        }

        $tagIds = $this->composer->resolveTags(texts: $command->tags, creatorUserId: $command->authUserId);

        $post = Post::create(
            userId: $authUserId,
            text: $command->text !== null ? PostText::fromString($command->text) : PostText::none(),
            status: PostStatus::Published,
            attachmentType: $command->mediaIds === [] ? AttachmentType::None : AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );

        $media = $this->composer->attachMedia(post: $post, mediaIds: $command->mediaIds, ownerUserId: $command->authUserId);
        $tags = $this->composer->attachTags(post: $post, tagIds: $tagIds);
        $mentions = $this->composer->attachPostMentions(post: $post, mentionIds: $command->mentions, actorUserId: $command->authUserId);

        $original->incrementReposts();

        $this->postRepository->saveRepost(post: $post, media: $media, tags: $tags, mentions: $mentions, original: $original);

        $this->composer->notifyPostAuthor(
            type: PostNotificationType::PostRepost,
            postAuthor: $original->userId,
            actorUserId: $command->authUserId,
            postId: $post->id->value(),
        );

        $this->logger->debug(message: 'Создан репост.', context: [
            'postId' => $post->id->value(),
            'originalPostId' => $original->id->value(),
        ]);

        return new RepostPostResult(postId: $post->id->value());
    }
}
