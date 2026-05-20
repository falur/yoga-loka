<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Support;

use Spiral\Translator\CatalogueManagerInterface;
use Spiral\Translator\TranslatorInterface;

final readonly class FakeTranslator implements TranslatorInterface
{
    /**
     * @param array<string, string> $messages
     */
    public function __construct(
        private string $locale,
        private array $messages,
    ) {}

    #[\Override]
    public function getLocale(): string
    {
        return $this->locale;
    }

    #[\Override]
    public function getDomain(string $bundle): string
    {
        return 'messages';
    }

    #[\Override]
    public function getCatalogueManager(): CatalogueManagerInterface
    {
        throw new \BadMethodCallException(message: 'Тестовый переводчик не хранит каталог переводов.');
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[\Override]
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->messages[$id] ?? $id;
    }
}
