<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use GianTiaga\SpiralCqrs\CommandBusInterface;

/**
 * Тестовая шина: на ProcessMediaCommand бросает заданное исключение (имитация сбоя
 * обработки), остальные команды (RecordMediaProcessingFailure) выполняет настоящим
 * обработчиком, чтобы проверить классификацию ошибок в ProcessMediaJob.
 */
final readonly class ThrowingProcessMediaCommandBus implements CommandBusInterface
{
    public function __construct(
        private \Throwable $processingException,
    ) {}

    public function dispatch(object $command, callable $handler)
    {
        if ($command instanceof ProcessMediaCommand) {
            throw $this->processingException;
        }

        return $handler($command);
    }
}
