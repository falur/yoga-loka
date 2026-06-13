<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Middleware;

use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Http\Header\AcceptHeader;
use Spiral\Http\Header\AcceptHeaderItem;
use Spiral\Translator\Translator;

/**
 * Определяет локаль запроса из заголовка Accept-Language, пересечённого с белым списком
 * поддерживаемых локалей, и выставляет её в translator на время обработки запроса.
 *
 * Зависит от конкретного Spiral\Translator\Translator (не TranslatorInterface): метод
 * setLocale() объявлен в LocaleAwareInterface и реализован конкретным Translator, а не
 * в TranslatorInterface. Translator — singleton, поэтому выставленная локаль видна и
 * интерсептору ошибок, который читает тот же инстанс через TranslatorInterface.
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Translator $translator,
        private LocaleConfig $localeConfig,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $acceptLanguage = $request->getHeaderLine('Accept-Language');
        $matchedLocale = $this->matchSupportedLocale($acceptLanguage);
        $resolvedLocale = $matchedLocale ?? $this->localeConfig->default;

        $this->translator->setLocale($resolvedLocale);
        $this->logger->debug(message: 'Локаль запроса определена.', context: [
            'acceptLanguage' => $acceptLanguage,
            'resolvedLocale' => $resolvedLocale,
            'usedFallback' => $matchedLocale === null,
        ]);

        return $handler->handle($request);
    }

    private function matchSupportedLocale(string $acceptLanguage): string|null
    {
        // getAll() уже отсортирован по quality по убыванию, поэтому first() даёт самый приоритетный язык.
        return Collection::make(AcceptHeader::fromString($acceptLanguage)->getAll())
            ->filter(static fn(AcceptHeaderItem $item): bool => $item->getQuality() > 0.0)
            ->map(fn(AcceptHeaderItem $item): string => $this->primarySubtag((string) $item->getValue()))
            ->first(fn(string $primarySubtag): bool => \in_array(
                needle: $primarySubtag,
                haystack: $this->localeConfig->supported,
                strict: true,
            ));
    }

    private function primarySubtag(string $value): string
    {
        $lower = \strtolower($value);
        $separatorPosition = \strpos(haystack: $lower, needle: '-');

        return $separatorPosition === false ? $lower : \substr(string: $lower, offset: 0, length: $separatorPosition);
    }
}
