<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Bootloader;

use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Environment\AppEnvironment;
use Spiral\Exceptions\ExceptionHandler;
use Spiral\Exceptions\Renderer\ConsoleRenderer;
use Spiral\Exceptions\Renderer\JsonRenderer;
use Spiral\Exceptions\Reporter\FileReporter;
use Spiral\Exceptions\Reporter\LoggerReporter;
use Spiral\Http\Middleware\ErrorHandlerMiddleware\EnvSuppressErrors;
use Spiral\Http\Middleware\ErrorHandlerMiddleware\SuppressErrorsInterface;

/**
 * Регистрирует рендереры и репортёры исключений.
 *
 * @link https://spiral.dev/docs/basics-errors
 */
final class ExceptionHandlerBootloader extends Bootloader
{
    protected const array BINDINGS = [
        SuppressErrorsInterface::class => EnvSuppressErrors::class,
    ];

    public function __construct(
        private readonly ExceptionHandler $handler,
    ) {}

    public function init(AbstractKernel $kernel): void
    {
        // Регистрируем рендерер для консольного режима.
        $this->handler->addRenderer(new ConsoleRenderer());

        $kernel->running(function (): void {
            // Регистрируем JSON-рендерер для HTTP-запросов, ожидающих JSON.
            $this->handler->addRenderer(new JsonRenderer());
        });
    }

    public function boot(LoggerReporter $logger, FileReporter $files, AppEnvironment $appEnv): void
    {
        // Регистрируем репортёр, который пишет исключения в лог.
        $this->handler->addReporter($logger);

        // В локальном окружении сохраняем подробный snapshot исключения в файл.
        if ($appEnv->isLocal()) {
            $this->handler->addReporter($files);
        }
    }
}
