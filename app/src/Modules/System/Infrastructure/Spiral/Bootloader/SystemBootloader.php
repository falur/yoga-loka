<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Bootloader;

use App\Modules\System\Infrastructure\Spiral\Console\OpenApiGenerateCommand;
use App\Modules\System\Infrastructure\Spiral\Console\OpenApiPublishAssetsCommand;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\Views\Bootloader\ViewsBootloader;

/**
 * Каркас модуля System. Регистрирует view-шаблоны модуля (Swagger UI) под namespace `system`:
 * шаблоны лежат в Infrastructure/Spiral/Resources/views, ссылка на шаблон — `system:<имя>`, — и консольные команды
 * модуля.
 */
final class SystemBootloader extends Bootloader
{
    /**
     * Namespace представлений модуля System (ссылка на шаблон: `system:<имя>`).
     */
    public const string VIEW_NAMESPACE = 'system';

    /**
     * @return array<int, class-string>
     */
    public function defineDependencies(): array
    {
        return [ViewsBootloader::class, ConsoleBootloader::class];
    }

    public function init(ViewsBootloader $views, ConsoleBootloader $console): void
    {
        $views->addDirectory(
            namespace: self::VIEW_NAMESPACE,
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/views', \dirname(path: __DIR__, levels: 3)),
        );

        $console->addCommand(OpenApiGenerateCommand::class);
        $console->addCommand(OpenApiPublishAssetsCommand::class);
    }
}
