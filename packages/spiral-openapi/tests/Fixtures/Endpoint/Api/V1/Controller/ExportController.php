<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Controller;

use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\FileContentResponse;
use GianTiaga\SpiralOpenApi\Response\FileResponse;
final class ExportController
{
    /**
     * Export OpenAPI YAML.
     */
    #[Route(route: '/api/v1/export/openapi.yml', name: 'api.v1.export.openapi', methods: ['GET'], group: 'api')]
    public function openApi(): FileContentResponse
    {
        return new FileContentResponse(content: 'openapi: 3.1.0', contentType: ContentType::Yaml);
    }
    /**
     * Download report PDF.
     */
    #[Route(route: '/api/v1/export/report.pdf', name: 'api.v1.export.report', methods: ['GET'], group: 'api')]
    public function report(): FileResponse
    {
        return new FileResponse(path: __FILE__, contentType: ContentType::Pdf, filename: 'report.pdf');
    }
}
