<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Public\Attribute\PublicRoute;
use App\Modules\System\Application\Exception\OpenApiSpecificationNotGeneratedException;
use App\Modules\System\Application\Exception\SwaggerUiDisabledException;
use App\Modules\System\Infrastructure\Spiral\Http\Response\SwaggerView;
use App\Modules\System\Infrastructure\Spiral\Configuration\OpenApiConfig;
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
    #[PublicRoute]
    #[OpenApi(ignore: true)]
    public function index(): HtmlResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new SwaggerUiDisabledException();
        }

        return new HtmlResponse(html: $this->swaggerView->render());
    }

    #[Route(route: '/api/docs/openapi.yml', name: 'api.docs.openapi', methods: ['GET'], group: 'api')]
    #[PublicRoute]
    #[OpenApi(ignore: true)]
    public function spec(): FileContentResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new SwaggerUiDisabledException();
        }

        $openApiFile = $this->openApiConfig->outputFile(
            projectRoot: $this->directories->get(name: DirectoryAlias::Root->value),
        );

        if (!\is_file($openApiFile)) {
            throw new OpenApiSpecificationNotGeneratedException();
        }

        return new FileContentResponse(
            content: (string) \file_get_contents($openApiFile),
            contentType: ContentType::Yaml,
        );
    }
}
