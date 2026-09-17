<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Unit\Domain\Enum;

use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use PHPUnit\Framework\TestCase;

final class PostsEnumTest extends TestCase
{
    public function testPostStatusCasesAndBackingValues(): void
    {
        self::assertSame(['draft', 'published', 'blocked'], \array_map(
            static fn(PostStatus $status): string => $status->value,
            PostStatus::cases(),
        ));
        self::assertSame(PostStatus::Draft, PostStatus::from('draft'));
        self::assertSame(PostStatus::Published, PostStatus::from('published'));
        self::assertSame(PostStatus::Blocked, PostStatus::from('blocked'));
    }

    public function testAttachmentTypeCasesAndBackingValues(): void
    {
        self::assertSame(['none', 'media', 'lesson', 'practice'], \array_map(
            static fn(AttachmentType $type): string => $type->value,
            AttachmentType::cases(),
        ));
        self::assertSame(AttachmentType::None, AttachmentType::from('none'));
        self::assertSame(AttachmentType::Media, AttachmentType::from('media'));
        self::assertSame(AttachmentType::Lesson, AttachmentType::from('lesson'));
        self::assertSame(AttachmentType::Practice, AttachmentType::from('practice'));
    }
}
