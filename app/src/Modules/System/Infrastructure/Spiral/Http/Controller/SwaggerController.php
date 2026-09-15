<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Http\Controller;

use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Infrastructure\Spiral\Http\View\SwaggerView;
use App\Shared\Infrastructure\Spiral\Configuration\OpenApi\OpenApiConfig;
use App\Shared\Infrastructure\Spiral\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\FileContentResponse;
use GianTiaga\SpiralOpenApi\Response\HtmlResponse;

final readonly class SwaggerController
{
    public function __construct(
        private OpenApiConfig $openApiConfig,
        private DirectoriesInterface $directories,
        private SwaggerView $swaggerView,
    ) {}

    #[Route(route: '/api/docs', name: 'api.docs', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function index(): HtmlResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new NotFoundException('app.system.swagger_ui_disabled');
        }

        return new HtmlResponse(html: $this->swaggerView->render());
    }

    #[Route(route: '/api/docs/openapi.yml', name: 'api.docs.openapi', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function spec(): FileContentResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new NotFoundException('app.system.swagger_ui_disabled');
        }

        $openApiFile = $this->openApiConfig->outputFile(
            projectRoot: $this->directories->get(name: DirectoryAlias::Root->value),
        );

        if (!\is_file($openApiFile)) {
            throw new NotFoundException('app.system.openapi_yaml_not_generated');
        }

        return new FileContentResponse(
            content: (string) \file_get_contents($openApiFile),
            contentType: ContentType::Yaml,
        );
    }
}
