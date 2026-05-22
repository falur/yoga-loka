<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Bootloader;

use App\Modules\System\Presentation\Console\OpenApiGenerateCommand;
use App\Modules\System\Presentation\Console\OpenApiPublishAssetsCommand;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;

final class OpenApiBootloader extends Bootloader
{
    public function init(ConsoleBootloader $console): void
    {
        $console->addCommand(OpenApiGenerateCommand::class);
        $console->addCommand(OpenApiPublishAssetsCommand::class);
    }
}
