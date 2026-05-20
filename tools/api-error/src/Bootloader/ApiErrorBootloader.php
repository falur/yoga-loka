<?php

declare(strict_types=1);

namespace Tools\ApiError\Bootloader;

use Psr\Log\LoggerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Filters\ErrorsRendererInterface;
use Spiral\Translator\TranslatorInterface;
use Tools\ApiError\Filter\ApiValidationErrorsRenderer;
use Tools\ApiError\Interceptor\ApiExceptionInterceptor;
use Tools\ApiError\Middleware\RouteNotFoundMiddleware;

final class ApiErrorBootloader extends Bootloader
{
    protected const array DEPENDENCIES = [
        I18nBootloader::class,
    ];

    public function init(I18nBootloader $i18n): void
    {
        $i18n->addDirectory(__DIR__ . '/../../locale');
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ...parent::defineSingletons(),
            ErrorsRendererInterface::class => [self::class, 'apiValidationErrorsRenderer'],
            ApiValidationErrorsRenderer::class => [self::class, 'apiValidationErrorsRenderer'],
            ApiExceptionInterceptor::class => [self::class, 'apiExceptionInterceptor'],
            RouteNotFoundMiddleware::class => [self::class, 'routeNotFoundMiddleware'],
        ];
    }

    public function apiValidationErrorsRenderer(
        LoggerInterface $logger,
        TranslatorInterface $translator,
    ): ApiValidationErrorsRenderer {
        return new ApiValidationErrorsRenderer(
            logger: $logger,
            translator: $translator,
        );
    }

    public function apiExceptionInterceptor(
        LoggerInterface $logger,
        TranslatorInterface $translator,
    ): ApiExceptionInterceptor {
        return new ApiExceptionInterceptor(
            logger: $logger,
            translator: $translator,
        );
    }

    public function routeNotFoundMiddleware(
        LoggerInterface $logger,
        TranslatorInterface $translator,
    ): RouteNotFoundMiddleware {
        return new RouteNotFoundMiddleware(
            logger: $logger,
            translator: $translator,
        );
    }
}
