<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Auth;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Infrastructure\Spiral\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Spiral\Hash\HmacSecretHasher;
use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use Spiral\Auth\TokenStorageProviderInterface;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class AuthBootloaderTest extends TestCase
{
    public function testContractBindingsResolveToImplementations(): void
    {
        self::assertInstanceOf(HmacSecretHasher::class, $this->getContainer()->get(SecretHasherContract::class));
        self::assertInstanceOf(RandomTokenGenerator::class, $this->getContainer()->get(TokenGeneratorContract::class));
        self::assertInstanceOf(CycleTokenStorage::class, $this->getContainer()->get(AuthTokenStorageContract::class));
    }

    public function testCycleTokenStorageRegisteredUnderCycleName(): void
    {
        $storage = $this->getContainer()->get(TokenStorageProviderInterface::class)->getStorage('cycle');

        self::assertInstanceOf(CycleTokenStorage::class, $storage);
    }

    public function testQueueRegistersSendLoginCodeJobWithOutboxSerializer(): void
    {
        $queueConfig = $this->getContainer()->get(ConfiguratorInterface::class)->getConfig('queue');

        self::assertArrayHasKey(SendLoginCodeJob::class, $queueConfig['registry']['handlers']);
        self::assertSame(
            OutboxQueueSerializer::class,
            $queueConfig['registry']['serializers'][SendLoginCodeJob::class],
        );
    }
}
