<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Tests\Support;

use Spiral\Translator\CatalogueManagerInterface;
use Spiral\Translator\TranslatorInterface;

final readonly class FakeTranslator implements TranslatorInterface
{
    /**
     * @param array<string, string> $messages
     */
    public function __construct(private string $locale, private array $messages) {}
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
     * @param array<int|string, bool|float|int|string|\Stringable|null> $parameters
     */
    #[\Override]
    public function trans(string $id, array $parameters = [], string|null $domain = null, string|null $locale = null): string
    {
        $replacePairs = [];
        foreach ($parameters as $key => $value) {
            $replacePairs[\sprintf('{%s}', $key)] = (string) $value;
        }
        return \strtr(string: $this->messages[$id] ?? $id, from: $replacePairs);
    }
}
