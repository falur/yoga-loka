<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostBlockColumns;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostBlockRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_block',
    table: PostBlockColumns::TABLE,
    repository: CyclePostBlockRepository::class,
    typecast: [Typecast::class],
)]
final class CyclePostBlockEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: PostBlockColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: PostBlockColumns::POST_ID)]
    public string $postId;

    #[Column(type: 'string(500)', name: PostBlockColumns::REASON)]
    public string $reason;

    #[Column(type: 'uuid', name: PostBlockColumns::BLOCKED_BY_ID)]
    public string $blockedById;

    #[Column(type: 'datetime', name: PostBlockColumns::UNBLOCKED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $unblockedAt;

    #[Column(type: 'uuid', name: PostBlockColumns::UNBLOCKED_BY_ID, nullable: true)]
    public string|null $unblockedById;

    #[Column(type: 'string(500)', name: PostBlockColumns::UNBLOCKED_REASON, nullable: true)]
    public string|null $unblockedReason;
}
