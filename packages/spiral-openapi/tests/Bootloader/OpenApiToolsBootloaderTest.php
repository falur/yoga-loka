<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Bootloader;

use GianTiaga\SpiralOpenApi\Bootloader\OpenApiToolsBootloader;
use PHPUnit\Framework\TestCase;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Config\ConfigManager;
use Spiral\Config\Loader\DirectoryLoader;
use Spiral\Translator\Config\TranslatorConfig;

final class OpenApiToolsBootloaderTest extends TestCase
{
    public function testBootloaderRegistersPackageLocaleDirectory(): void
    {
        $config = new ConfigManager(loader: new DirectoryLoader(directory: __DIR__));
        $config->setDefaults(section: TranslatorConfig::CONFIG, data: ['directories' => []]);
        $i18n = new I18nBootloader(config: $config);
        (new OpenApiToolsBootloader())->init(i18n: $i18n);
        $translatorConfig = new TranslatorConfig($config->getConfig(section: TranslatorConfig::CONFIG));
        self::assertSame(\realpath(__DIR__ . '/../../locale'), \realpath($translatorConfig->getDirectories()[0]));
    }
}
