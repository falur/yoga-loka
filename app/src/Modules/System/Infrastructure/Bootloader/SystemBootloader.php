<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Views\Bootloader\ViewsBootloader;

/**
 * Каркас модуля System. Регистрирует view-шаблоны модуля (Swagger UI) под namespace `system`:
 * шаблоны лежат в Presentation/views, ссылка на шаблон — `system:<имя>`.
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
        return [ViewsBootloader::class];
    }

    public function init(ViewsBootloader $views): void
    {
        $views->addDirectory(
            namespace: self::VIEW_NAMESPACE,
            directory: \sprintf('%s/Presentation/views', \dirname(path: __DIR__, levels: 2)),
        );
    }
}
