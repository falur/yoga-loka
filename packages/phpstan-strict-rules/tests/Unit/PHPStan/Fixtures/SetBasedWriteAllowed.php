<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan\Fixtures;

use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;

final class SetBasedWriteAllowed implements SetBasedWriteTestMarker
{
    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function markAll(object $entity): void
    {
        // Разрешено: класс реализует маркер SetBasedWrite.
        $this->database->update('notifications');

        // Не set-based запись: delete() на EntityManager, а не на DatabaseInterface.
        $this->entityManager->delete($entity);
    }
}
