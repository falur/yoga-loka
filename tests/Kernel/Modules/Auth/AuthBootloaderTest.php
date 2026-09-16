<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Auth;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Infrastructure\Spiral\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Spiral\Http\Access\AuthenticatedRouteRule;
use App\Modules\Auth\Infrastructure\Spiral\Http\Access\PublicRouteRule;
use App\Modules\Auth\Infrastructure\Spiral\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Auth\Public\Attribute\PublicRoute;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Spiral\Hash\HmacSecretHasher;
use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use App\Shared\Infrastructure\Spiral\Bootloader\RoutesBootloader;
use App\Shared\Infrastructure\Spiral\Http\Access\AccessRuleRegistry;
use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
use Spiral\Auth\TokenStorageProviderInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Router\GroupRegistry;
use Spiral\Router\RouteGroup;
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

    /**
     * Установление личности объявлено на всю группу `api` и стоит после собственного конвейера
     * группы, поэтому порядок относительно обработчика ошибок валидации остался прежним.
     */
    public function testApiRouteGroupInstallsIdentityAfterItsOwnPipeline(): void
    {
        $apiGroup = $this->getContainer()->get(GroupRegistry::class)->getGroup(RoutesBootloader::GROUP_API);
        $middleware = new \ReflectionProperty(RouteGroup::class, 'middleware')->getValue($apiGroup);

        self::assertIsArray($middleware);
        self::assertCount(3, $middleware);
        self::assertSame('middleware:' . RoutesBootloader::GROUP_API, $middleware[0]);
        self::assertInstanceOf(Autowire::class, $middleware[1]);
        self::assertSame(AuthTransportWithStorageMiddleware::class, $middleware[1]->alias);
        self::assertSame(
            ['transportName' => 'header', 'storage' => 'cycle'],
            $middleware[1]->parameters,
        );
        self::assertSame(AuthContextAttributeMiddleware::class, $middleware[2]);
    }

    public function testAccessRulesOfPublicAttributesAreRegistered(): void
    {
        $accessRuleRegistry = $this->getContainer()->get(AccessRuleRegistry::class);

        self::assertInstanceOf(
            PublicRouteRule::class,
            $accessRuleRegistry->ruleFor(declarationClass: PublicRoute::class),
        );
        self::assertInstanceOf(
            AuthenticatedRouteRule::class,
            $accessRuleRegistry->ruleFor(declarationClass: AuthenticatedRoute::class),
        );
    }
}
