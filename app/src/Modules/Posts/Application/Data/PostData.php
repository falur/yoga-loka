<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Data;

use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;

/**
 * Запись как она лежит в своих таблицах: собственные поля и идентификаторы чужих/внутренних частей
 * (автор — чужой модуль, вложения и метки — внутренние сущности агрегата, признак вне поля Post не
 * читается: страница ленты идёт через Reader целиком, чтобы не смешивать Repository- и
 * Reader-чтение одной страницы). likedByMe и имя/аватар автора здесь нет — их дочитывает handler
 * через PostViewerReader и User/Public.
 */
final readonly class PostData
{
    /**
     * @param list<string> $mediaIds вложения записи в порядке возрастания их позиции
     * @param list<string> $tagIds
     */
    public function __construct(
        public string $id,
        public string|null $text,
        public PostStatus $status,
        public AttachmentType $attachmentType,
        public string $authorId,
        public int $likesCount,
        public int $repostsCount,
        public int $commentsCount,
        public string|null $original,
        public \DateTimeImmutable $createdAt,
        public array $mediaIds,
        public array $tagIds,
    ) {}

    /**
     * Единственное место, где ряд выборки превращается в объект. Ключ ряда — имя поля Cycle Entity
     * (`userId`, `parentPostId`), а не имя колонки (`user_id`, `parent_post_id`). Значения статуса,
     * типа вложения и времени создания приходят уже типизированными: `fetchData()` применяет
     * typecast-правила колонок сущности (backed enum и `datetime` — те же правила, что и при
     * загрузке полноценной Entity), поэтому Reader не дублирует их вручную. Тип контракта — общая
     * карта значений ряда (не array shape, запрещённый правилами проекта), поэтому каждое поле
     * сужается при чтении.
     *
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     * @param list<string> $mediaIds
     * @param list<string> $tagIds
     */
    public static function fromDatabaseRow(array $row, array $mediaIds, array $tagIds): self
    {
        return new self(
            id: self::stringField(row: $row, key: 'id'),
            text: self::nullableStringField(row: $row, key: 'text'),
            status: self::statusField(row: $row, key: 'status'),
            attachmentType: self::attachmentTypeField(row: $row, key: 'attachmentType'),
            authorId: self::stringField(row: $row, key: 'userId'),
            likesCount: self::intField(row: $row, key: 'likesCount'),
            repostsCount: self::intField(row: $row, key: 'repostsCount'),
            commentsCount: self::intField(row: $row, key: 'commentsCount'),
            original: self::nullableStringField(row: $row, key: 'parentPostId'),
            createdAt: self::dateTimeField(row: $row, key: 'createdAt'),
            mediaIds: $mediaIds,
            tagIds: $tagIds,
        );
    }

    /**
     * Идентификатор записи из ряда выборки: единственное место, где ключ `id` ряда сужается до
     * строки, поэтому Reader и коллекция читают его отсюда, а не проверяют тип у себя.
     *
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    public static function idFromDatabaseRow(array $row): string
    {
        return self::stringField(row: $row, key: 'id');
    }

    /**
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    private static function stringField(array $row, string $key): string
    {
        $value = $row[$key];

        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('Ожидалась строка в ключе "%s" ряда выборки Post.', $key));
        }

        return $value;
    }

    /**
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    private static function nullableStringField(array $row, string $key): string|null
    {
        $value = $row[$key];

        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('Ожидалась строка или null в ключе "%s" ряда выборки Post.', $key));
        }

        return $value;
    }

    /**
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    private static function intField(array $row, string $key): int
    {
        $value = $row[$key];

        if (!\is_int($value)) {
            throw new \LogicException(\sprintf('Ожидалось целое число в ключе "%s" ряда выборки Post.', $key));
        }

        return $value;
    }

    /**
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    private static function statusField(array $row, string $key): PostStatus
    {
        $value = $row[$key];

        if (!$value instanceof PostStatus) {
            throw new \LogicException(\sprintf('Ожидался статус записи в ключе "%s" ряда выборки Post.', $key));
        }

        return $value;
    }

    /**
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    private static function attachmentTypeField(array $row, string $key): AttachmentType
    {
        $value = $row[$key];

        if (!$value instanceof AttachmentType) {
            throw new \LogicException(\sprintf('Ожидался тип вложения в ключе "%s" ряда выборки Post.', $key));
        }

        return $value;
    }

    /**
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    private static function dateTimeField(array $row, string $key): \DateTimeImmutable
    {
        $value = $row[$key];

        if (!$value instanceof \DateTimeImmutable) {
            throw new \LogicException(\sprintf('Ожидалась дата создания в ключе "%s" ряда выборки Post.', $key));
        }

        return $value;
    }
}
