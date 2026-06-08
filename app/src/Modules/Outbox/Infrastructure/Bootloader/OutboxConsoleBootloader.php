<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Bootloader;

use App\Modules\Outbox\Presentation\Console\OutboxRelayCommand;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;

final class OutboxConsoleBootloader extends Bootloader
{
    public function init(ConsoleBootloader $console): void
    {
        $console->addCommand(OutboxRelayCommand::class);
    }
}
