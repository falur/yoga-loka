<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Translator;

use App\Infrastructure\Configuration\TypedConfig;
use Symfony\Component\Translation\Dumper\DumperInterface;
use Symfony\Component\Translation\Loader\LoaderInterface;

final readonly class TranslatorConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'translator';
    }

    /**
     * @param array<int|string, string> $directories
     * @param array<string, class-string<LoaderInterface>> $loaders
     * @param array<string, class-string<DumperInterface>> $dumpers
     * @param array<string, TranslatorDomainConfig> $domains
     */
    public function __construct(
        public string $locale,
        public string $fallbackLocale,
        public string $directory,
        public array $directories,
        public bool $autoRegister,
        public array $loaders,
        public array $dumpers,
        public array $domains,
    ) {}
}
