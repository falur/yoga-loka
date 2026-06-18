<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PostBlockEntityTest extends TestCase
{
    public function testCreateIsActiveBlock(): void
    {
        $block = $this->createBlock();

        self::assertTrue(AbstractUuidV7Id::isUuidV7($block->id->value()));
        self::assertSame('Нарушение', $block->reason->value());
        self::assertTrue($block->isActive());
        self::assertFalse($block->unblockedAt->isUnblocked());
        self::assertTrue($block->unblockedBy->isEmpty());
        self::assertTrue($block->unblockedReason->isEmpty());
    }

    public function testMarkUnblockedDeactivatesBlock(): void
    {
        $block = $this->createBlock();
        $unblockedBy = UserId::generate();
        $unblockedAt = new \DateTimeImmutable('2026-06-17 12:00:00');

        $block->markUnblocked(
            unblockedBy: BlockUnblockedBy::by($unblockedBy),
            unblockedAt: BlockUnblockedAt::at($unblockedAt),
            unblockedReason: BlockUnblockedReason::of('Ошибочная блокировка'),
        );

        self::assertFalse($block->isActive());
        self::assertSame($unblockedBy->value(), $block->unblockedBy->value());
        self::assertSame($unblockedAt, $block->unblockedAt->value());
        self::assertSame('Ошибочная блокировка', $block->unblockedReason->value());
    }

    public function testMarkUnblockedRejectsEmptyDate(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->createBlock()->markUnblocked(
            unblockedBy: BlockUnblockedBy::by(UserId::generate()),
            unblockedAt: BlockUnblockedAt::notUnblocked(),
            unblockedReason: BlockUnblockedReason::none(),
        );
    }

    public function testMarkUnblockedRejectsEmptyUser(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->createBlock()->markUnblocked(
            unblockedBy: BlockUnblockedBy::none(),
            unblockedAt: BlockUnblockedAt::at(new \DateTimeImmutable('2026-06-17 12:00:00')),
            unblockedReason: BlockUnblockedReason::none(),
        );
    }

    private function createBlock(): PostBlock
    {
        return PostBlock::create(
            postId: PostId::generate(),
            reason: BlockReason::fromString('Нарушение'),
            blockedBy: UserId::generate(),
        );
    }
}
