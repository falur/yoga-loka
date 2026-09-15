<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Media;

use App\Modules\Media\Application\Contract\MediaUploadPlannerContract;
use App\Modules\Media\Application\Contract\MediaUrlServiceContract;
use App\Modules\Media\Infrastructure\Storage\MediaUploadPlanner;
use App\Modules\Media\Infrastructure\Storage\MediaUrlService;
use Tests\TestCase;

final class MediaBootloaderTest extends TestCase
{
    public function testMediaUploadPlannerContractResolvesToInfrastructureImplementation(): void
    {
        // Биндинг const BINDINGS отдаёт реализацию планировщика из Infrastructure\Storage; она сама
        // читает MediaConfig через конструктор (как MediaUrlService), без фабрики и settings-объекта.
        $mediaUploadPlanner = $this->getContainer()->get(MediaUploadPlannerContract::class);

        self::assertInstanceOf(MediaUploadPlanner::class, $mediaUploadPlanner);
    }

    public function testMediaUrlServiceContractResolvesToInfrastructureImplementation(): void
    {
        // Биндинг const BINDINGS отдаёт реализацию из Infrastructure\Storage; срок presigned по
        // умолчанию реализация читает из MediaConfig сама (без фабрики и обёртки MediaPresignedTtl).
        $mediaUrlService = $this->getContainer()->get(MediaUrlServiceContract::class);

        self::assertInstanceOf(MediaUrlService::class, $mediaUrlService);
    }
}
