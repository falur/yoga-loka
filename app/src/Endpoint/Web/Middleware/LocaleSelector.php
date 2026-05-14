<?php

declare(strict_types=1);

namespace App\Endpoint\Web\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Translator\Translator;

/**
 * The middleware that sets the application locale based on the "Accept-Language" header.
 * List of available locales is taken from the translator.
 */
final class LocaleSelector implements MiddlewareInterface
{
    /** @var list<string> */
    private array $availableLocales;

    public function __construct(
        private readonly Translator $translator,
    ) {
        $availableLocales = [];
        foreach ($this->translator->getCatalogueManager()->getLocales() as $locale) {
            if (\is_string($locale)) {
                $availableLocales[] = $locale;
            }
        }

        $this->availableLocales = $availableLocales;
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $defaultLocale = $this->translator->getLocale();

        try {
            foreach ($this->fetchLocales($request) as $locale) {
                if ($locale !== '' && \in_array(needle: $locale, haystack: $this->availableLocales, strict: true)) {
                    $this->translator->setLocale($locale);
                    break;
                }
            }

            return $handler->handle($request);
        } finally {
            // restore
            $this->translator->setLocale($defaultLocale);
        }
    }

    /**
     * @return \Generator<int, string, void, void>
     */
    public function fetchLocales(ServerRequestInterface $request): \Generator
    {
        $header = $request->getHeaderLine('accept-language');
        foreach (\explode(separator: ',', string: $header) as $value) {
            $pos = \strpos(haystack: $value, needle: ';');
            if ($pos !== false) {
                yield \substr(string: $value, offset: 0, length: $pos);
            }

            yield $value;
        }
    }
}
