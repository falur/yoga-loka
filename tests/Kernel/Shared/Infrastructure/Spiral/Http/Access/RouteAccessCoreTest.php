<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Http\Access;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;
use Tests\Kernel\Shared\Infrastructure\Spiral\Http\Access\Fixture\UndeclaredAccessControllerFixture;
use Tests\TestCase;

/**
 * Проверяет собранную цепочку доменного ядра: маршрут без объявления доступа до контроллера
 * не доходит, а наружу уходит общий ответ 500 без подробностей.
 */
final class RouteAccessCoreTest extends TestCase
{
    public function testRouteWithoutAccessDeclarationReturnsGenericServerError(): void
    {
        $response = $this->getContainer()->get(HandlerInterface::class)->handle(new CallContext(
            target: Target::fromReflectionMethod(
                reflection: new \ReflectionMethod(
                    objectOrMethod: UndeclaredAccessControllerFixture::class,
                    method: 'undeclared',
                ),
                classOrObject: UndeclaredAccessControllerFixture::class,
            ),
            arguments: [],
            attributes: [
                ServerRequestInterface::class => new ServerRequest(method: 'GET', uri: '/kernel-fixture/undeclared'),
            ],
        ));

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(500, $response->getStatusCode());
        // Ни имени маршрута, ни класса исключения клиенту не видно: подробности остаются в журнале.
        self::assertSame('{"message":"Internal server error","code":500}', (string) $response->getBody());
    }
}
