<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Application\Data;

use App\Modules\Posts\Application\Data\PostData;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Фабрика данных чтения: ряд выборки превращается в PostData здесь и только здесь, поэтому
 * проверяются и удачный разбор ряда, и каждая проверка типа значения ряда. Значения статуса, типа
 * вложения и времени создания приходят уже типизированными (typecast колонок сущности), остальные
 * поля — скаляры; ряд с другим типом значения означает сломанный контракт выборки, а не ошибку
 * пользователя, поэтому ожидается LogicException.
 */
final class PostDataTest extends TestCase
{
    public function testBuildsPostDataFromRow(): void
    {
        $createdAt = new \DateTimeImmutable('2026-09-16 12:00:00');

        $postData = PostData::fromDatabaseRow(
            row: self::row(),
            mediaIds: ['media-1', 'media-2'],
            tagIds: ['tag-1'],
        );

        self::assertSame('post-1', $postData->id);
        self::assertSame('текст записи', $postData->text);
        self::assertSame(PostStatus::Published, $postData->status);
        self::assertSame(AttachmentType::Media, $postData->attachmentType);
        self::assertSame('user-1', $postData->authorId);
        self::assertSame(3, $postData->likesCount);
        self::assertSame(2, $postData->repostsCount);
        self::assertSame(1, $postData->commentsCount);
        self::assertSame('parent-1', $postData->original);
        self::assertEquals($createdAt, $postData->createdAt);
        self::assertSame(['media-1', 'media-2'], $postData->mediaIds);
        self::assertSame(['tag-1'], $postData->tagIds);
    }

    /**
     * Граничный случай: запись без текста и без исходной записи репоста — оба поля ряда null.
     */
    public function testBuildsPostDataWithNullableFieldsMissing(): void
    {
        $postData = PostData::fromDatabaseRow(
            row: ['text' => null, 'parentPostId' => null] + self::row(),
            mediaIds: [],
            tagIds: [],
        );

        self::assertNull($postData->text);
        self::assertNull($postData->original);
    }

    public function testReadsIdOfRow(): void
    {
        self::assertSame('post-1', PostData::idFromDatabaseRow(self::row()));
    }

    /**
     * @param array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null> $row
     */
    #[DataProvider('brokenRowProvider')]
    public function testRejectsRowWithUnexpectedValueType(array $row, string $expectedMessage): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($expectedMessage);

        PostData::fromDatabaseRow(row: $row, mediaIds: [], tagIds: []);
    }

    /**
     * @return array<string, array{array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null>, string}>
     */
    public static function brokenRowProvider(): array
    {
        return [
            'id не строка' => [
                ['id' => 42] + self::row(),
                'Ожидалась строка в ключе "id" ряда выборки Post.',
            ],
            'text не строка и не null' => [
                ['text' => 42] + self::row(),
                'Ожидалась строка или null в ключе "text" ряда выборки Post.',
            ],
            'status не статус записи' => [
                ['status' => 'published'] + self::row(),
                'Ожидался статус записи в ключе "status" ряда выборки Post.',
            ],
            'attachmentType не тип вложения' => [
                ['attachmentType' => 'media'] + self::row(),
                'Ожидался тип вложения в ключе "attachmentType" ряда выборки Post.',
            ],
            'likesCount не целое число' => [
                ['likesCount' => '3'] + self::row(),
                'Ожидалось целое число в ключе "likesCount" ряда выборки Post.',
            ],
            'createdAt не дата' => [
                ['createdAt' => '2026-09-16 12:00:00'] + self::row(),
                'Ожидалась дата создания в ключе "createdAt" ряда выборки Post.',
            ],
        ];
    }

    /**
     * @return array<non-empty-string, scalar|PostStatus|AttachmentType|\DateTimeImmutable|null>
     */
    private static function row(): array
    {
        return [
            'id' => 'post-1',
            'text' => 'текст записи',
            'status' => PostStatus::Published,
            'attachmentType' => AttachmentType::Media,
            'userId' => 'user-1',
            'likesCount' => 3,
            'repostsCount' => 2,
            'commentsCount' => 1,
            'parentPostId' => 'parent-1',
            'createdAt' => new \DateTimeImmutable('2026-09-16 12:00:00'),
        ];
    }
}
