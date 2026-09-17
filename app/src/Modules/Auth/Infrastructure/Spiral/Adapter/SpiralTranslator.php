<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Adapter;

use App\Modules\Auth\Application\Contract\TranslatorContract;
use Spiral\Translator\TranslatorInterface;

/**
 * Адаптер TranslatorContract поверх Spiral\Translator\TranslatorInterface: аргументы передаются
 * без изменений, текст переводов и локали не меняются.
 */
final readonly class SpiralTranslator implements TranslatorContract
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function trans(string $id, array $parameters, string $domain, string $locale): string
    {
        return $this->translator->trans(id: $id, parameters: $parameters, domain: $domain, locale: $locale);
    }
}
