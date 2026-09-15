<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentCommand;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
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

        self::assertTrue($media->expiration->isPermanent());
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

        self::assertTrue($first->expiration->isPermanent());
        self::assertTrue($second->expiration->isPermanent());
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
        $this->expectException(NotFoundException::class);

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

        $this->expectException(ForbiddenException::class);

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

        $this->expectException(ValidationException::class);

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

        $this->expectException(ValidationException::class);
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
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }
}
