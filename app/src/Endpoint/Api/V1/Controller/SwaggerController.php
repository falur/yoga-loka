<?php

declare(strict_types=1);

namespace App\Endpoint\Api\V1\Controller;

use App\Endpoint\Api\V1\View\SwaggerView;
use App\Infrastructure\Configuration\OpenApi\OpenApiConfig;
use App\Infrastructure\Framework\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Router\Annotation\Route;
use Tools\OpenApi\Attribute\OpenApi;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\ErrorResponse;
use Tools\OpenApi\Response\FileContentResponse;
use Tools\OpenApi\Response\HtmlResponse;

final readonly class SwaggerController
{
    public function __construct(
        private OpenApiConfig $openApiConfig,
        private DirectoriesInterface $directories,
        private SwaggerView $swaggerView,
    ) {}

    #[Route(route: '/api/docs', name: 'api.docs', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function index(): ErrorResponse|HtmlResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            return new ErrorResponse(message: 'Swagger UI выключен.')
                ->withStatus(HttpStatus::NotFound);
        }

        return new HtmlResponse(html: $this->swaggerView->render());
    }

    #[Route(route: '/api/docs/openapi.yml', name: 'api.docs.openapi', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function spec(): ErrorResponse|FileContentResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            return new ErrorResponse(message: 'Swagger UI выключен.')
                ->withStatus(HttpStatus::NotFound);
        }

        $openApiFile = $this->openApiConfig->outputFile(projectRoot: $this->directories->get(name: DirectoryAlias::Root->value));

        if (!\is_file($openApiFile)) {
            return new ErrorResponse(message: 'OpenAPI YAML ещё не сгенерирован.')
                ->withStatus(HttpStatus::NotFound);
        }

        return new FileContentResponse(
            content: (string) \file_get_contents($openApiFile),
            contentType: ContentType::Yaml,
        );
    }
}
