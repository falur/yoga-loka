<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Bootloader;

use App\Modules\System\Infrastructure\Spiral\Console\OpenApiGenerateCommand;
use App\Modules\System\Infrastructure\Spiral\Console\OpenApiPublishAssetsCommand;
use App\Shared\Infrastructure\Spiral\Bootloader\ConfigBootloader;
use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Config\ConfiguratorInterface;
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

    /** @param ConfiguratorInterface<object> $config */
    public function init(
        ViewsBootloader $views,
        ConsoleBootloader $console,
        I18nBootloader $i18n,
        ConfigBootloader $configBootloader,
        ConfiguratorInterface $config,
    ): void {
        $views->addDirectory(
            namespace: self::VIEW_NAMESPACE,
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/views', \dirname(path: __DIR__, levels: 3)),
        );

        $console->addCommand(OpenApiGenerateCommand::class);
        $console->addCommand(OpenApiPublishAssetsCommand::class);

        // Переводы модуля лежат внутри модуля: удаление модуля не оставляет переводов в чужих папках.
        $i18n->addDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/locale', \dirname(path: __DIR__, levels: 3)),
        );

        // Типизированный конфиг модуля лежит внутри модуля: удаление модуля не оставляет
        // конфигурации в чужих папках.
        $configBootloader->addConfigurationDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Configuration', \dirname(path: __DIR__, levels: 3)),
        );
        $config->setDefaults(
            section: 'openapi',
            data: ConfigArrayFile::read(path: \sprintf(
                '%s/Infrastructure/Spiral/Configuration/openapi.php',
                \dirname(path: __DIR__, levels: 3),
            )),
        );
    }
}
