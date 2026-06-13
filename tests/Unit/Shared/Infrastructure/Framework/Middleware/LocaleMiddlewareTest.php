<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Framework\Middleware;

use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use Spiral\Translator\CatalogueManagerInterface;
use Spiral\Translator\Config\TranslatorConfig;
use Spiral\Translator\Translator;

final class LocaleMiddlewareTest extends TestCase
{
    #[DataProvider('acceptLanguageProvider')]
    public function testResolvesRequestLocaleFromAcceptLanguage(string $acceptLanguage, string $expectedLocale): void
    {
        $translator = $this->translatorStartingWith('en');
        $middleware = new LocaleMiddleware(
            translator: $translator,
            localeConfig: new LocaleConfig(supported: ['ru', 'en'], default: 'ru'),
            logger: new NullLogger(),
        );

        $middleware->process(
            request: $this->requestWithAcceptLanguage($acceptLanguage),
            handler: $this->passthroughHandler(),
        );

        self::assertSame($expectedLocale, $translator->getLocale());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function acceptLanguageProvider(): iterable
    {
        yield 'точное совпадение en' => ['en', 'en'];
        yield 'регион отбрасывается en-US' => ['en-US,en;q=0.9', 'en'];
        yield 'точное совпадение ru' => ['ru', 'ru'];
        yield 'неподдерживаемый язык → default' => ['de', 'ru'];
        yield 'пустой заголовок → default' => ['', 'ru'];
        yield 'wildcard → default' => ['*', 'ru'];
        yield 'порядок по quality' => ['ru;q=0.3,en;q=0.9', 'en'];
        yield 'q=0 игнорируется при наличии валидного' => ['en;q=0,ru', 'ru'];
        yield 'q=0 у единственного поддерживаемого → default' => ['en;q=0', 'ru'];
    }

    private function translatorStartingWith(string $locale): Translator
    {
        $catalogueManager = $this->createStub(CatalogueManagerInterface::class);
        $catalogueManager->method('has')->willReturn(true);

        return new Translator(
            config: new TranslatorConfig(['locale' => $locale]),
            catalogueManager: $catalogueManager,
        );
    }

    private function requestWithAcceptLanguage(string $acceptLanguage): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')->willReturn($acceptLanguage);

        return $request;
    }

    private function passthroughHandler(): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createStub(ResponseInterface::class));

        return $handler;
    }
}
