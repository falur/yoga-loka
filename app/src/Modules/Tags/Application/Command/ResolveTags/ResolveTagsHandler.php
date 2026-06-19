<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Command\ResolveTags;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Repository\TagRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Превращает набор текстов в идентификаторы тегов (find-or-create). check-then-act, не insert+catch:
 * существующие теги переиспользуются, недостающие создаются в той же транзакции одним run(). Редкий
 * конкурентный дубль по уникальному tags.text допустим как 500 (rules.md). Результат пересобирается в
 * порядке входных текстов с дедупликацией: повтор текста в одном запросе не плодит дублей.
 */
final readonly class ResolveTagsHandler
{
    public function __construct(
        private TagRepository $tagRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(ResolveTagsCommand $command): ResolveTagsResult
    {
        $creatorUserId = UserId::fromString($command->creatorUserId);

        // Уникальные тексты в порядке первого появления (ключ — нормализованное значение TagText).
        $orderedTexts = [];

        foreach ($command->texts as $rawText) {
            $text = TagText::fromString($rawText);
            $orderedTexts[$text->value()] ??= $text;
        }

        if ($orderedTexts === []) {
            return new ResolveTagsResult(tagIds: []);
        }

        $existingByText = [];

        foreach ($this->tagRepository->findByTexts(...\array_values($orderedTexts)) as $tag) {
            $existingByText[$tag->text->value()] = $tag;
        }

        $tagIds = [];
        $createdCount = 0;

        foreach ($orderedTexts as $textValue => $text) {
            $tag = $existingByText[$textValue] ?? null;

            if ($tag === null) {
                $tag = Tag::create(text: $text, createdBy: $creatorUserId);
                $this->entityManager->persist($tag);
                $createdCount++;
            }

            $tagIds[] = $tag->id->value();
        }

        if ($createdCount > 0) {
            $this->entityManager->run();
        }

        $this->logger->debug(message: 'Теги разрешены.', context: [
            'requested' => \count($orderedTexts),
            'created' => $createdCount,
        ]);

        return new ResolveTagsResult(tagIds: $tagIds);
    }
}
