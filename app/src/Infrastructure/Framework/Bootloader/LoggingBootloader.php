<?php

declare(strict_types=1);

namespace App\Infrastructure\Framework\Bootloader;

use App\Infrastructure\Framework\DirectoryAlias;
use Monolog\Level;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\Monolog\Config\MonologConfig;

/**
 * Настраивает логгеры приложения.
 *
 * @link https://spiral.dev/docs/basics-logging
 */
final class LoggingBootloader extends Bootloader
{
    private const string HTTP_LOG_FILE = 'logs/http.log';

    private const string ERROR_LOG_FILE = 'logs/error.log';
    private const string DEBUG_LOG_FILE = 'logs/debug.log';
    private const int ERROR_LOG_MAX_FILES = 25;

    public function __construct(
        private readonly DirectoriesInterface $directories,
    ) {}

    public function init(MonologBootloader $monolog): void
    {
        // Ошибки HTTP-слоя
        $monolog->addHandler(
            channel: ErrorHandlerMiddleware::class,
            handler: $monolog->logRotate(
                \sprintf(
                    '%s/%s',
                    $this->directories->get(DirectoryAlias::Runtime->value),
                    self::HTTP_LOG_FILE,
                ),
            ),
        );

        // Ошибки приложения
        $monolog->addHandler(
            channel: MonologConfig::DEFAULT_CHANNEL,
            handler: $monolog->logRotate(
                filename: \sprintf(
                    '%s/%s',
                    $this->directories->get(DirectoryAlias::Runtime->value),
                    self::ERROR_LOG_FILE,
                ),
                level: Level::Error,
                maxFiles: self::ERROR_LOG_MAX_FILES,
                bubble: false,
            ),
        );

        // Debug- и info-сообщения через глобальный LoggerInterface
        $monolog->addHandler(
            channel: MonologConfig::DEFAULT_CHANNEL,
            handler: $monolog->logRotate(
                filename: \sprintf(
                    '%s/%s',
                    $this->directories->get(DirectoryAlias::Runtime->value),
                    self::DEBUG_LOG_FILE,
                ),
            ),
        );
    }
}
