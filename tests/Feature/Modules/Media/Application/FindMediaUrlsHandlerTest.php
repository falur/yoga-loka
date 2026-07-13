<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsHandler;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsQuery;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;

/**
 * Пакетное разрешение URL нескольких медиа: результат ключуется по id медиа, недоступные (не
 * финализированы, не найдены) в набор не попадают.
 */
final class FindMediaUrlsHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsEmptyCollectionForEmptyInput(): void
    {
        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlsQuery(mediaIds: []),
        );

        self::assertCount(0, $result);
    }

    public function testResolvesAvailableMediaKeyedByIdAndSkipsUnavailable(): void
    {
        $first = $this->readyMedia(MediaVisibility::Public);
        $second = $this->readyMedia(MediaVisibility::Public);
        $notReady = $this->createMedia(userId: UserId::generate(), visibility: MediaVisibility::Public);
        $this->persist($first, $second, $notReady);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('https://cdn.example/x.jpg');

        $result = $this->handler($fileService)->handle(new FindMediaUrlsQuery(
            mediaIds: [
                $first->id->value(),
                $second->id->value(),
                $notReady->id->value(),
                UserId::generate()->value(),
            ],
        ));

        // Доступные медиа лежат под своим id; нефинализированное и несуществующее в набор не попадают.
        self::assertCount(2, $result);
        $firstUrls = $result->get($first->id->value());
        self::assertNotNull($firstUrls);
        self::assertNotNull($firstUrls->original);
        self::assertSame('https://cdn.example/x.jpg', $firstUrls->original->url);
        self::assertNotNull($result->get($second->id->value()));
        self::assertNull($result->get($notReady->id->value()));
    }

    private function handler(MediaFileServiceContract $fileService): FindMediaUrlsHandler
    {
        return new FindMediaUrlsHandler(
            mediaRepository: $this->mediaRepository(),
            mediaUrlService: new MediaUrlService(
                mediaFileService: $fileService,
                mediaConfig: $this->getContainer()->get(MediaConfig::class),
            ),
        );
    }
}
