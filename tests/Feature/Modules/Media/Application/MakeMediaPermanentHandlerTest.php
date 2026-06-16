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
            mediaId: $media->id->value(),
        ));

        self::assertTrue($media->expiration->isPermanent());
        self::assertSame($media->id->value(), $result->mediaId);
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new MakeMediaPermanentCommand(
            userId: UserId::generate()->value(),
            mediaId: UserId::generate()->value(),
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
            mediaId: $media->id->value(),
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
            mediaId: $media->id->value(),
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
