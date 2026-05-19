<?php

declare(strict_types=1);

namespace Tools\ApiError\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Filters\ErrorsRendererInterface;
use Tools\ApiError\Filter\ApiValidationErrorsRenderer;

final class ApiErrorBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            ErrorsRendererInterface::class => ApiValidationErrorsRenderer::class,
        ];
    }
}
