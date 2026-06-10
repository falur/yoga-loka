<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Flow;

use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Presentation\Job\ProcessMediaJob;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Spiral\Queue\Exception\RetryException;
use Psr\Log\LogLevel;
use Tests\Feature\Modules\Media\Application\MediaApplicationTestCase;
use Tests\Feature\Modules\Media\Flow\Fixture\RecordingMediaLogger;
use Tests\Feature\Modules\Media\Flow\Fixture\ThrowingProcessMediaCommandBus;

final class ProcessMediaJobTest extends MediaApplicationTestCase
{
    #[DataProvider('failureProvider')]
    public function testClassifiesProcessingFailureAndRecordsErrorOnMedia(
        \Throwable $exception,
        string $expectedThrownClass,
    ): void {
        $media = $this->createMedia(userId: UserId::generate());
        $media->markUploaded();
        $this->persist($media);

        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), conversions: []));

        $job = $this->getContainer()->get(ProcessMediaJob::class);

        try {
            $job->invoke(
                payload: $this->envelope(),
                id: 'job-1',
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
        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), conversions: []));

        $logger = new RecordingMediaLogger();
        $job = $this->getContainer()->get(ProcessMediaJob::class);

        try {
            $job->invoke(
                payload: $this->envelope(),
                id: 'job-1',
                outboxMessageLoader: $loader,
                commandBus: new ThrowingProcessMediaCommandBus($exception),
                processMediaHandler: $this->getContainer()->get(ProcessMediaHandler::class),
                recordMediaProcessingFailureHandler: $this->getContainer()->get(RecordMediaProcessingFailureHandler::class),
                logger: $logger,
            );
        } catch (\Throwable) {
            // Job всегда пробрасывает: транзиентный -> RetryException, постоянный -> исходное.
        }

        self::assertTrue($logger->hasLevel($expectedLevel));
        self::assertFalse($logger->hasLevel($unexpectedLevel));
    }

    public function testTransientFailureStillRetriesWhenRecordingErrorThrows(): void
    {
        // Сценарий ревью: медиа конкурентно удалили к моменту записи ошибки -> запись
        // (RecordMediaProcessingFailureHandler::handle -> findById ?? throw NotFoundException)
        // падает. Вторичный сбой записи не должен подменять исходную классификацию:
        // для транзиентного исходного сбоя Job всё равно бросает RetryException.
        $missingMediaId = Uuid::uuid7()->toString();

        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $missingMediaId, conversions: []));

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
                payload: $this->envelope(),
                id: 'job-1',
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

        // Исходная причина не подменяется: транзиентный сбой -> RetryException, а не NotFoundException.
        self::assertInstanceOf(RetryException::class, $thrown);
        // Вторичный сбой записи залогирован как ERROR (rules.md:84), плюс ERROR классификации не мешает
        // WARN транзиентного ретрая.
        self::assertTrue($logger->hasLevel(LogLevel::ERROR));
        self::assertTrue($logger->hasLevel(LogLevel::WARNING));
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
                RetryException::class,
            ],
            'постоянный сбой хранилища -> терминально' => [
                MediaFileServiceFailedException::permanent(message: 'Ошибка хранилища.', previous: $storageError),
                MediaFileServiceFailedException::class,
            ],
            'ошибка Imagick -> терминально' => [
                new \RuntimeException('повреждённое изображение'),
                \RuntimeException::class,
            ],
        ];
    }

    private function envelope(): OutboxQueueEnvelope
    {
        return new OutboxQueueEnvelope(
            outboxEventId: OutboxEventId::fromString(Uuid::uuid7()->toString()),
            outboxEventType: OutboxEventType::fromString(MediaUploaded::class),
        );
    }
}
