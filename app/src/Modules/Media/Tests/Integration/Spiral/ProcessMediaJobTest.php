<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Infrastructure\Spiral\Job\ProcessMediaJob;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Psr\Log\LogLevel;

final class ProcessMediaJobTest extends MediaApplicationTestCase
{
    /** Идентификатор доставки: сам Job его не проверяет, статусами доставки владеет интерсептор. */
    private const string DELIVERY_ID = '0192f2a0-0000-7000-8000-0000000000de';

    #[DataProvider('failureProvider')]
    public function testClassifiesProcessingFailureAndRecordsErrorOnMedia(
        \Throwable $exception,
        string $expectedThrownClass,
    ): void {
        $media = $this->createMedia(userId: UserId::generate());
        $media->markUploaded();
        $this->persist($media);

        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new MediaUploadedEvent(mediaId: $media->id->value(), plan: $this->emptyPlan()));

        $job = $this->getContainer()->get(ProcessMediaJob::class);

        try {
            $job->invoke(
                outboxDeliveryId: self::DELIVERY_ID,
                outboxMessageLoader: $loader,
                commandBus: new ThrowingProcessMediaCommandBus($exception),
                processMediaHandler: $this->getContainer()->get(ProcessMediaHandler::class),
                recordMediaProcessingFailureHandler: $this->getContainer()->get(RecordMediaProcessingFailureHandler::class),
                logger: new NullLogger(),
            );
            self::fail('Ожидалось исключение от ProcessMediaJob.');
        } catch (\Throwable $thrown) {
            self::assertInstanceOf($expectedThrownClass, $thrown);
        }

        $failedMedia = $this->mediaRepository()->findById($media->id);
        self::assertNotNull($failedMedia);
        self::assertSame(MediaStatus::ProcessingFailed, $failedMedia->status);
        self::assertFalse($failedMedia->processingError->isEmpty());
    }

    #[DataProvider('logLevelProvider')]
    public function testLogsTransientFailureAsWarningAndPermanentAsError(
        \Throwable $exception,
        string $expectedLevel,
        string $unexpectedLevel,
    ): void {
        $media = $this->createMedia(userId: UserId::generate());
        $media->markUploaded();
        $this->persist($media);

        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new MediaUploadedEvent(mediaId: $media->id->value(), plan: $this->emptyPlan()));

        $logger = new RecordingMediaLogger();
        $job = $this->getContainer()->get(ProcessMediaJob::class);

        try {
            $job->invoke(
                outboxDeliveryId: self::DELIVERY_ID,
                outboxMessageLoader: $loader,
                commandBus: new ThrowingProcessMediaCommandBus($exception),
                processMediaHandler: $this->getContainer()->get(ProcessMediaHandler::class),
                recordMediaProcessingFailureHandler: $this->getContainer()->get(RecordMediaProcessingFailureHandler::class),
                logger: $logger,
            );
        } catch (\Throwable) {
            // Job всегда пробрасывает: временный -> RetryableOutboxException, постоянный -> исходное.
        }

        self::assertTrue($logger->hasLevel($expectedLevel));
        self::assertFalse($logger->hasLevel($unexpectedLevel));
    }

    public function testTransientFailureStillRetriesWhenRecordingErrorThrows(): void
    {
        // Сценарий ревью: медиа конкурентно удалили к моменту записи ошибки -> запись
        // (RecordMediaProcessingFailureHandler::handle -> findById ?? throw MediaNotFoundException)
        // падает. Вторичный сбой записи не должен подменять исходную классификацию:
        // для временного исходного сбоя Job всё равно бросает RetryableOutboxException.
        $missingMediaId = Uuid::uuid7()->toString();

        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new MediaUploadedEvent(mediaId: $missingMediaId, plan: $this->emptyPlan()));

        $storageError = new \RuntimeException('сырой AWS-сбой');
        $transientException = MediaFileServiceFailedException::transient(
            message: 'Ошибка хранилища.',
            previous: $storageError,
        );

        $logger = new RecordingMediaLogger();
        $job = $this->getContainer()->get(ProcessMediaJob::class);

        $thrown = null;

        try {
            $job->invoke(
                outboxDeliveryId: self::DELIVERY_ID,
                outboxMessageLoader: $loader,
                commandBus: new ThrowingProcessMediaCommandBus($transientException),
                processMediaHandler: $this->getContainer()->get(ProcessMediaHandler::class),
                recordMediaProcessingFailureHandler: $this->getContainer()->get(RecordMediaProcessingFailureHandler::class),
                logger: $logger,
            );
            self::fail('Ожидалось исключение от ProcessMediaJob.');
        } catch (\Throwable $caught) {
            $thrown = $caught;
        }

        // Исходная причина не подменяется: временный сбой -> RetryableOutboxException, а не MediaNotFoundException.
        self::assertInstanceOf(RetryableOutboxException::class, $thrown);
        // Вторичный сбой записи залогирован как ERROR (rules.md:84), плюс ERROR классификации не мешает
        // WARN временного повтора.
        self::assertTrue($logger->hasLevel(LogLevel::ERROR));
        self::assertTrue($logger->hasLevel(LogLevel::WARNING));
    }

    public function testMediaNotFoundExceptionCarriesTranslationKeyWithoutTranslationInQueueContext(): void
    {
        // Очередь не выполняет перевод (per-request локали нет): мигрированное исключение несёт
        // ключ перевода, а не русский текст — getMessage() == ключ, translationKey() == ключ.
        try {
            $this->getContainer()->get(RecordMediaProcessingFailureHandler::class)->handle(
                new RecordMediaProcessingFailureCommand(
                    mediaId: Uuid::uuid7()->toString(),
                    error: 'сбой обработки',
                    isTransient: false,
                ),
            );
            self::fail('Ожидалось MediaNotFoundException.');
        } catch (MediaNotFoundException $exception) {
            self::assertSame('app.media.not_found', $exception->translationKey());
            self::assertSame('app.media.not_found', $exception->getMessage());
            self::assertSame([], $exception->translationParameters());
        }
    }

    /**
     * @return array<string, array{\Throwable, string, string}>
     */
    public static function logLevelProvider(): array
    {
        $storageError = new \RuntimeException('сырой AWS-сбой');

        return [
            'транзиентный -> WARNING' => [
                MediaFileServiceFailedException::transient(message: 'Ошибка хранилища.', previous: $storageError),
                LogLevel::WARNING,
                LogLevel::ERROR,
            ],
            'постоянный -> ERROR' => [
                MediaFileServiceFailedException::permanent(message: 'Ошибка хранилища.', previous: $storageError),
                LogLevel::ERROR,
                LogLevel::WARNING,
            ],
            'временный сбой процессора -> WARNING' => [
                MediaProcessorFailedException::transient(message: 'Не удалось обработать медиа.', previous: $storageError),
                LogLevel::WARNING,
                LogLevel::ERROR,
            ],
            'постоянный сбой процессора -> ERROR' => [
                MediaProcessorFailedException::permanent(message: 'Не удалось обработать медиа.', previous: $storageError),
                LogLevel::ERROR,
                LogLevel::WARNING,
            ],
        ];
    }

    /**
     * @return array<string, array{\Throwable, class-string<\Throwable>}>
     */
    public static function failureProvider(): array
    {
        // Job реагирует на контрактный сигнал MediaFileServiceFailedException::isTransient(),
        // а не на сырой AwsException: классификацию хранилища делает Infrastructure.
        $storageError = new \RuntimeException('сырой AWS-сбой');

        return [
            'транзиентный сбой хранилища -> повтор' => [
                MediaFileServiceFailedException::transient(message: 'Ошибка хранилища.', previous: $storageError),
                RetryableOutboxException::class,
            ],
            'постоянный сбой хранилища -> терминально' => [
                MediaFileServiceFailedException::permanent(message: 'Ошибка хранилища.', previous: $storageError),
                MediaFileServiceFailedException::class,
            ],
            'временный сбой процессора -> повтор' => [
                MediaProcessorFailedException::transient(message: 'Не удалось обработать медиа.', previous: $storageError),
                RetryableOutboxException::class,
            ],
            'постоянный сбой процессора -> терминально' => [
                MediaProcessorFailedException::permanent(message: 'Не удалось обработать медиа.', previous: $storageError),
                MediaProcessorFailedException::class,
            ],
            'ошибка Imagick -> терминально' => [
                new \RuntimeException('повреждённое изображение'),
                \RuntimeException::class,
            ],
        ];
    }
}
