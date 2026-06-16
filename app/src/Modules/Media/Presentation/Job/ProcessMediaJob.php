<?php

declare(strict_types=1);

namespace App\Modules\Media\Presentation\Job;

use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job обработки медиа. Грузит MediaUploaded из outbox и запускает
 * ProcessMediaCommand. Ошибки обработки ловятся здесь (Job — граница системы, try-catch
 * разрешён): фиксируем безопасную ошибку на Media и классифицируем — транзиентную просим
 * повторить (RetryException, его читает OutboxQueueStatusInterceptor), постоянную пробрасываем
 * терминально (outbox -> failed). Статусы outbox Job сам не трогает.
 */
final class ProcessMediaJob extends JobHandler
{
    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
    private const string PROCESSING_FAILURE_MESSAGE = 'Не удалось обработать медиа.';

    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        ProcessMediaHandler $processMediaHandler,
        RecordMediaProcessingFailureHandler $recordMediaProcessingFailureHandler,
        LoggerInterface $logger,
    ): void {
        $mediaUploaded = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: MediaUploaded::class,
        );

        try {
            $commandBus->dispatch(
                command: new ProcessMediaCommand(
                    mediaId: $mediaUploaded->mediaId,
                    conversions: $mediaUploaded->conversions,
                ),
                handler: $processMediaHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            // Транзиентность приходит контрактным сигналом MediaFileServiceFailedException::isTransient():
            // классификацию AWS делает Infrastructure, Presentation не знает про реализацию хранилища.
            $isTransient = $exception instanceof MediaFileServiceFailedException && $exception->isTransient();

            // Запись ошибки на Media — отдельный сбойный путь: медиа могли конкурентно удалить
            // (NotFoundException) или короткий сбой БД. Защищаем только этот вызов локальным guard,
            // чтобы вторичный сбой записи не подменил исходную причину и решение retry/terminal:
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
                    'jobId' => $id,
                    'recordErrorClass' => $recordFailure::class,
                    'originalErrorClass' => $exception::class,
                    'errorMessage' => $recordFailure->getMessage(),
                ]);
            }

            $logContext = [
                'mediaId' => $mediaUploaded->mediaId,
                'jobId' => $id,
                'isTransient' => $isTransient,
                'errorClass' => $exception::class,
            ];

            if ($isTransient) {
                // Транзиентный ретраябельный сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
                $logger->warning(
                    message: 'Транзиентная ошибка обработки медиа, запланирован повтор.',
                    context: $logContext,
                );

                throw new RetryException(reason: self::STORAGE_FAILURE_MESSAGE);
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
