<?php

declare(strict_types=1);

namespace Tools\OpenApi\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Translator\TranslatorInterface;
use Tools\OpenApi\OpenApiGenerator;

final class OpenApiToolsBootloader extends Bootloader
{
    protected const array DEPENDENCIES = [
        I18nBootloader::class,
    ];

    public function init(I18nBootloader $i18n): void
    {
        $i18n->addDirectory(__DIR__ . '/../../locale');
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            OpenApiGenerator::class => [self::class, 'openApiGenerator'],
        ];
    }

    public function openApiGenerator(TranslatorInterface $translator): OpenApiGenerator
    {
        return new OpenApiGenerator(translator: $translator);
    }
}
