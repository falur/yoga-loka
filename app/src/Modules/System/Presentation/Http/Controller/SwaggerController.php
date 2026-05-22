<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Http\Controller;

use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Presentation\Http\View\SwaggerView;
use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
use App\Shared\Infrastructure\Framework\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Router\Annotation\Route;
use Tools\OpenApi\Attribute\OpenApi;
use Tools\OpenApi\Response\Enum\ContentType;
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
    public function index(): HtmlResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new NotFoundException(message: 'Swagger UI выключен.');
        }

        return new HtmlResponse(html: $this->swaggerView->render());
    }

    #[Route(route: '/api/docs/openapi.yml', name: 'api.docs.openapi', methods: ['GET'], group: 'api')]
    #[OpenApi(ignore: true)]
    public function spec(): FileContentResponse
    {
        if (!$this->openApiConfig->swaggerEnabled) {
            throw new NotFoundException(message: 'Swagger UI выключен.');
        }

        $openApiFile = $this->openApiConfig->outputFile(
            projectRoot: $this->directories->get(name: DirectoryAlias::Root->value)
        );

        if (!\is_file($openApiFile)) {
            throw new NotFoundException(message: 'OpenAPI YAML ещё не сгенерирован.');
        }

        return new FileContentResponse(
            content: (string) \file_get_contents($openApiFile),
            contentType: ContentType::Yaml,
        );
    }
}
