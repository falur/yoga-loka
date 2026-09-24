<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Spiral\Job;

use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\LoggerInterface;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job обработки медиа. Грузит MediaUploadedEvent по идентификатору доставки и
 * запускает ProcessMediaCommand. Ошибки обработки ловятся здесь (Job — граница системы, try-catch
 * разрешён): фиксируем безопасную ошибку на Media и классифицируем — временную просим
 * повторить (RetryableOutboxException, её читает интерсептор доставки пакета), постоянную
 * пробрасываем терминально (доставка -> failed). Статусы доставки Job сам не трогает.
 */
final class ProcessMediaJob extends JobHandler
{
    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
    private const string PROCESSING_RETRY_MESSAGE = 'Повторная обработка медиа запланирована.';
    private const string PROCESSING_FAILURE_MESSAGE = 'Не удалось обработать медиа.';

    public function invoke(
        string $outboxDeliveryId,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        ProcessMediaHandler $processMediaHandler,
        RecordMediaProcessingFailureHandler $recordMediaProcessingFailureHandler,
        LoggerInterface $logger,
    ): void {
        $mediaUploaded = $outboxMessageLoader->load(
            outboxDeliveryId: $outboxDeliveryId,
            expectedMessageClass: MediaUploadedEvent::class,
        );

        try {
            $commandBus->dispatch(
                command: new ProcessMediaCommand(
                    mediaId: $mediaUploaded->mediaId,
                    plan: $mediaUploaded->plan,
                ),
                handler: $processMediaHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            // Временность приходит контрактным сигналом isTransient(): классификацию делает
            // Infrastructure (AWS — файловый сервис, ffmpeg — процессоры), Job не знает про
            // конкретные реализации хранилища и обработки.
            $isTransient = ($exception instanceof MediaFileServiceFailedException && $exception->isTransient())
                || ($exception instanceof MediaProcessorFailedException && $exception->isTransient());

            // Запись ошибки на Media — отдельный сбойный путь: медиа могли конкурентно удалить
            // (MediaNotFoundException) или короткий сбой БД. Защищаем только этот вызов локальным guard,
            // чтобы вторичный сбой записи не подменил исходную причину и решение повтор/терминальный исход:
            // логируем его как вторичный сбой (ERROR — реальная инфра/инвариант-проблема, rules.md:84)
            // и продолжаем классифицировать по исходному $exception.
            try {
                $commandBus->dispatch(
                    command: new RecordMediaProcessingFailureCommand(
                        mediaId: $mediaUploaded->mediaId,
                        error: $this->safeMessage($exception),
                        isTransient: $isTransient,
                    ),
                    handler: $recordMediaProcessingFailureHandler->handle(...),
                );
            } catch (\Throwable $recordFailure) {
                $logger->error(message: 'Не удалось записать ошибку обработки на медиа.', context: [
                    'mediaId' => $mediaUploaded->mediaId,
                    'outboxDeliveryId' => $outboxDeliveryId,
                    'recordErrorClass' => $recordFailure::class,
                    'originalErrorClass' => $exception::class,
                ]);
            }

            $logContext = [
                'mediaId' => $mediaUploaded->mediaId,
                'outboxDeliveryId' => $outboxDeliveryId,
                'isTransient' => $isTransient,
                'errorClass' => $exception::class,
            ];

            if ($isTransient) {
                // Временный повторяемый сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
                $logger->warning(
                    message: 'Временная ошибка обработки медиа, запланирован повтор.',
                    context: $logContext,
                );

                // Причина повтора по типу сбоя: ошибка процессора != ошибка хранилища.
                $retryReason = $exception instanceof MediaProcessorFailedException
                    ? self::PROCESSING_RETRY_MESSAGE
                    : self::STORAGE_FAILURE_MESSAGE;

                throw new RetryableOutboxException(message: $retryReason);
            }

            // Постоянный сбой (битый файл/нарушение инварианта) -> ERROR, повтора не будет.
            $logger->error(message: 'Ошибка обработки медиа.', context: $logContext);

            throw $exception;
        }
    }

    private function safeMessage(\Throwable $exception): string
    {
        return $exception instanceof MediaFileServiceFailedException
            ? self::STORAGE_FAILURE_MESSAGE
            : self::PROCESSING_FAILURE_MESSAGE;
    }
}
