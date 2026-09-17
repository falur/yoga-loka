<?php

declare(strict_types=1);

namespace App\Modules\System\Tests\Feature\Spiral;

use Spiral\Testing\Attribute\Config;
use Tests\TestCase;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;

final class OpenApiHttpTest extends TestCase
{
    public function testHealthEndpointReturnsTypedJsonResponse(): void
    {
        $response = $this->fakeHttp()->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertHasHeader(HttpHeader::ContentType->value, ContentType::Json->value);
        $response->assertBodyContains('"status":"ok"');
    }

    public function testSwaggerUiUsesLocalAssets(): void
    {
        $response = $this->fakeHttp()->get('/api/docs');

        $response->assertOk();
        $response->assertHasHeader('Content-Type', 'text/html; charset=utf-8');
        $response->assertHasHeader('Content-Disposition', 'inline');
        $response->assertBodyContains('/swagger-ui/swagger-ui-bundle.js');
        $response->assertBodyContains('/api/docs/openapi.yml');
        self::assertStringNotContainsString('https://', (string) $response);
    }

    public function testSwaggerYamlRouteReturnsGeneratedYaml(): void
    {
        $response = $this->fakeHttp()->get('/api/docs/openapi.yml');

        $response->assertOk();
        $response->assertHasHeader('Content-Type', 'application/yaml; charset=utf-8');
        $response->assertHasHeader('Content-Disposition', 'inline');
        $response->assertBodyContains('openapi: 3.1.0');
        $response->assertBodyContains('url: /api/v1');
        $response->assertBodyContains('/health:');
    }

    #[Config('openapi.outputFile', 'runtime/missing-openapi.yml')]
    public function testSwaggerYamlRouteReturnsErrorResponseWhenFileDoesNotExist(): void
    {
        $response = $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->get('/api/docs/openapi.yml');

        $response->assertNotFound();
        $response->assertHasHeader('Content-Type', 'application/json; charset=utf-8');
        $response->assertBodySame('{"message":"OpenAPI YAML ещё не сгенерирован.","code":404}');
    }

    #[Config('openapi.swaggerEnabled', false)]
    public function testSwaggerUiRouteReturnsJsonErrorWhenDisabled(): void
    {
        $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->get('/api/docs')
            ->assertNotFound()
            ->assertHasHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertBodySame('{"message":"Swagger UI выключен.","code":404}');
    }

    #[Config('openapi.swaggerEnabled', false)]
    public function testSwaggerYamlRouteReturnsJsonErrorWhenDisabled(): void
    {
        $this->fakeHttp()
            ->withHeader('Accept-Language', 'ru')
            ->get('/api/docs/openapi.yml')
            ->assertNotFound()
            ->assertHasHeader('Content-Type', 'application/json; charset=utf-8')
            ->assertBodySame('{"message":"Swagger UI выключен.","code":404}');
    }
}
