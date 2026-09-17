<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentCommand;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Domain\Exception\MediaAccessDeniedException;
use App\Modules\Media\Domain\Exception\MediaCannotBeMadePermanentException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;

final class MakeMediaPermanentHandlerTest extends MediaApplicationTestCase
{
    public function testMakesUploadedMediaPermanent(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $media->markUploaded();
        $this->persist($media);

        $result = $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: $userId->value(),
            mediaIds: [$media->id->value()],
        ));

        // Media — чистая доменная сущность без Cycle-разметки: handler мутировал свою отдельно
        // загруженную через Mapper копию, а не переменную $media теста, поэтому итоговое
        // состояние проверяется перечитыванием через репозиторий.
        self::assertTrue($this->mediaRepository()->findById($media->id)?->expiration->isPermanent());
        self::assertSame([$media->id->value()], $result->mediaIds);
    }

    public function testMakesWholeSetPermanent(): void
    {
        $userId = UserId::generate();
        $first = $this->createMedia(userId: $userId);
        $first->markUploaded();
        $second = $this->createMedia(userId: $userId);
        $second->markUploaded();
        $this->persist($first, $second);

        $result = $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: $userId->value(),
            mediaIds: [$first->id->value(), $second->id->value()],
        ));

        self::assertTrue($this->mediaRepository()->findById($first->id)?->expiration->isPermanent());
        self::assertTrue($this->mediaRepository()->findById($second->id)?->expiration->isPermanent());
        self::assertSame([$first->id->value(), $second->id->value()], $result->mediaIds);
    }

    public function testAcceptsEmptySet(): void
    {
        $result = $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: UserId::generate()->value(),
            mediaIds: [],
        ));

        self::assertSame([], $result->mediaIds);
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(MediaNotFoundException::class);

        $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: UserId::generate()->value(),
            mediaIds: [UserId::generate()->value()],
        ));
    }

    public function testRejectsForeignOwner(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $media->markUploaded();
        $this->persist($media);

        $this->expectException(MediaAccessDeniedException::class);

        $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: UserId::generate()->value(),
            mediaIds: [$media->id->value()],
        ));
    }

    public function testRejectsMediaInInvalidStatus(): void
    {
        $userId = UserId::generate();
        $media = $this->createMedia(userId: $userId);
        $this->persist($media);

        $this->expectException(MediaCannotBeMadePermanentException::class);

        $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: $userId->value(),
            mediaIds: [$media->id->value()],
        ));
    }

    public function testRejectsUnsuitableMediaInTheMiddleOfSet(): void
    {
        // Набор обходится в порядке передачи: первое непригодное медиа отказывает всей операции, а
        // изменения предыдущих не фиксируются — run() выполняется только после полного обхода.
        $userId = UserId::generate();
        $first = $this->createMedia(userId: $userId);
        $first->markUploaded();
        $staging = $this->createMedia(userId: $userId);
        $last = $this->createMedia(userId: $userId);
        $last->markUploaded();
        $this->persist($first, $staging, $last);

        $this->expectException(MediaCannotBeMadePermanentException::class);
        $this->expectExceptionMessage('app.media.cannot_make_permanent');

        $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: $userId->value(),
            mediaIds: [$first->id->value(), $staging->id->value(), $last->id->value()],
        ));
    }

    private function handler(): MakeMediaPermanentHandler
    {
        return new MakeMediaPermanentHandler(
            mediaRepository: $this->mediaRepository(),
            logger: new NullLogger(),
        );
    }
}
