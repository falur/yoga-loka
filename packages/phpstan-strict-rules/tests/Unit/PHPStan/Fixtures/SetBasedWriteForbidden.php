<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan\Fixtures;

use Cycle\Database\DatabaseInterface;

final class SetBasedWriteForbidden
{
    public function __construct(
        private readonly DatabaseInterface $database,
    ) {}

    public function run(): void
    {
        $this->database->update('notifications');
        $this->database->insert('notifications');
        $this->database->delete('notifications');
    }
}
