<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Translator;

final readonly class TranslatorDomainConfig
{
    /**
     * @param list<string> $patterns
     */
    public function __construct(
        public array $patterns,
    ) {}
}
