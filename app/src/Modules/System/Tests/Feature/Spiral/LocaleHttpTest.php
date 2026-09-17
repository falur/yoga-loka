<?php

declare(strict_types=1);

namespace App\Modules\System\Tests\Feature\Spiral;

use App\Shared\Infrastructure\Spiral\Configuration\Locale\LocaleConfig;
use Spiral\Translator\TranslatorInterface;
use Tests\TestCase;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;

final class LocaleHttpTest extends TestCase
{
    public function testDomainErrorIsTranslatedToEnglishForEnglishAcceptLanguage(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'en')
            ->getJson('/test/api/errors/domain');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Test resource not found.","code":404}');
    }

    public function testDomainErrorIsTranslatedToRussianForRussianAcceptLanguage(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->getJson('/test/api/errors/domain');

        $response->assertNotFound();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodySame('{"message":"Тестовый ресурс не найден.","code":404}');
    }

    public function testDomainErrorFallsBackToDefaultLocaleForUnknownAcceptLanguage(): void
    {
        $defaultLocale = $this->getContainer()->get(LocaleConfig::class)->default;
        $expectedMessage = $this->getContainer()->get(TranslatorInterface::class)->trans(
            id: 'app.system.test_resource_not_found',
            parameters: [],
            domain: 'system',
            locale: $defaultLocale,
        );

        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'de')
            ->getJson('/test/api/errors/domain');

        $response->assertNotFound();
        $response->assertBodySame(\sprintf('{"message":"%s","code":404}', $expectedMessage));
    }

    public function testParametrizedErrorInterpolatesParameterInEnglish(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'en')
            ->getJson('/test/api/errors/parametrized');

        $response->assertUnprocessable();
        $response->assertBodySame('{"message":"File type \"png\" is not supported for upload.","code":422}');
    }

    public function testParametrizedErrorInterpolatesParameterInRussian(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->getJson('/test/api/errors/parametrized');

        $response->assertUnprocessable();
        $response->assertBodySame('{"message":"Тип файла «png» не поддерживается для загрузки.","code":422}');
    }
}
