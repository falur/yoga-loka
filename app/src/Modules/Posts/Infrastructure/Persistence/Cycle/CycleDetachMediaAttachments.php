<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle;

use App\Modules\Posts\Application\Contract\DetachMediaAttachmentsContract;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns\PostMediaColumns;
use App\Shared\Infrastructure\Persistence\Cycle\SetBasedWrite;
use Cycle\Database\DatabaseInterface;

/**
 * Массовое снятие вложений по mediaId одним DELETE. Реализует маркер SetBasedWrite — прямая запись
 * мимо EntityManager допустима только в классах с этим маркером. Не использовать для одиночных
 * изменений вложения: единичное вложение создаётся и удаляется вместе со своей записью через
 * PostRepository.
 *
 * ВНИМАНИЕ: запись идёт в обход identity map ORM. Если строки `post_media` этого mediaId уже
 * загружены как Entity в этом же запросе, копии в памяти устареют — для потребителя интеграционного
 * события (DetachDeletedMediaHandler) это безопасно: он не читает вложения обратно после удаления.
 */
final readonly class CycleDetachMediaAttachments implements DetachMediaAttachmentsContract, SetBasedWrite
{
    public function __construct(
        private DatabaseInterface $database,
    ) {}

    #[\Override]
    public function detachByMediaId(PostMediaReference $mediaId): int
    {
        return $this->database
            ->delete(PostMediaColumns::TABLE)
            ->where(PostMediaColumns::MEDIA_ID, $mediaId->value())
            ->run();
    }
}
