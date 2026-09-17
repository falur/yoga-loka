<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Http\Access;

use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Infrastructure\Spiral\Http\Access\AccessRuleRegistry;
use App\Shared\Infrastructure\Spiral\Http\Access\RouteAccessInterceptor;
use App\Shared\Infrastructure\Spiral\Http\Access\RouteAccessNotDeclaredException;
use App\Shared\Infrastructure\Spiral\Http\Access\RouteRequestUnavailableException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;
use Tests\Unit\Shared\Infrastructure\Spiral\Http\Access\Fixture\AccessDeclarationFixture;
use Tests\Unit\Shared\Infrastructure\Spiral\Http\Access\Fixture\AccessRouteControllerFixture;
use Tests\Unit\Shared\Infrastructure\Spiral\Http\Access\Fixture\RecordingAccessRuleFixture;

final class RouteAccessInterceptorTest extends TestCase
{
    private const string HANDLER_RESULT = 'результат контроллера';

    public function testDeclaredRouteIsCheckedByRuleOfItsDeclaration(): void
    {
        $rule = new RecordingAccessRuleFixture();
        $request = new ServerRequest(method: 'GET', uri: '/fixture/declared');

        $result = $this->interceptor(rule: $rule)->intercept(
            context: $this->context(method: 'declared', request: $request),
            handler: $this->passthroughHandler(),
        );

        self::assertSame(self::HANDLER_RESULT, $result);
        self::assertSame(1, $rule->calls);
        self::assertInstanceOf(AccessDeclarationFixture::class, $rule->declaration);
        self::assertSame('объявленный маршрут', $rule->declaration->name);
        self::assertSame($request, $rule->request);
    }

    /**
     * Забытая декларация — ошибка разработчика, а не публичный маршрут: запрос до контроллера
     * не доходит, а имя метода маршрута остаётся в сообщении исключения для журнала.
     */
    public function testRouteWithoutDeclarationDoesNotReachController(): void
    {
        $rule = new RecordingAccessRuleFixture();
        $handler = $this->passthroughHandler();

        try {
            $this->interceptor(rule: $rule)->intercept(
                context: $this->context(
                    method: 'undeclared',
                    request: new ServerRequest(method: 'GET', uri: '/fixture/undeclared'),
                ),
                handler: $handler,
            );

            self::fail('Маршрут без объявления доступа должен быть отклонён.');
        } catch (RouteAccessNotDeclaredException $exception) {
            self::assertSame(
                \sprintf('Маршрут %s::undeclared() не объявил требование доступа.', AccessRouteControllerFixture::class),
                $exception->getMessage(),
            );
        }

        self::assertSame(0, $rule->calls);
    }

    /**
     * Атрибут без зарегистрированного правила объявлением доступа не является: маршрут с одним
     * только таким атрибутом остаётся необъявленным.
     */
    public function testRouteWithUnregisteredAttributeOnlyIsRejected(): void
    {
        $interceptor = $this->interceptor(rule: new RecordingAccessRuleFixture());

        $this->expectException(RouteAccessNotDeclaredException::class);

        $interceptor->intercept(
            context: $this->context(
                method: 'undeclaredWithUnregisteredAttribute',
                request: new ServerRequest(method: 'GET', uri: '/fixture/undeclared-with-unregistered-attribute'),
            ),
            handler: $this->passthroughHandler(),
        );
    }

    public function testMethodWithoutRouteAttributeIsNotChecked(): void
    {
        $rule = new RecordingAccessRuleFixture();

        $result = $this->interceptor(rule: $rule)->intercept(
            context: $this->context(
                method: 'withoutRoute',
                request: new ServerRequest(method: 'GET', uri: '/fixture/without-route'),
            ),
            handler: $this->passthroughHandler(),
        );

        self::assertSame(self::HANDLER_RESULT, $result);
        self::assertSame(0, $rule->calls);
    }

    public function testTargetWithoutMethodReflectionIsNotChecked(): void
    {
        $rule = new RecordingAccessRuleFixture();

        $result = $this->interceptor(rule: $rule)->intercept(
            context: new CallContext(target: Target::fromClosure(static fn(): string => 'замыкание')),
            handler: $this->passthroughHandler(),
        );

        self::assertSame(self::HANDLER_RESULT, $result);
        self::assertSame(0, $rule->calls);
    }

    public function testRefusalOfRuleReachesCaller(): void
    {
        $interceptor = $this->interceptor(
            rule: new RecordingAccessRuleFixture(refusal: new ForbiddenException('app.test.refused')),
        );

        $this->expectException(ForbiddenException::class);

        $interceptor->intercept(
            context: $this->context(
                method: 'declared',
                request: new ServerRequest(method: 'GET', uri: '/fixture/declared'),
            ),
            handler: $this->passthroughHandler(),
        );
    }

    public function testDeclaredRouteWithoutRequestInContextIsRejected(): void
    {
        $interceptor = $this->interceptor(rule: new RecordingAccessRuleFixture());

        $this->expectException(RouteRequestUnavailableException::class);

        $interceptor->intercept(
            context: new CallContext(
                target: Target::fromReflectionMethod(
                    reflection: new \ReflectionMethod(
                        objectOrMethod: AccessRouteControllerFixture::class,
                        method: 'declared',
                    ),
                    classOrObject: AccessRouteControllerFixture::class,
                ),
            ),
            handler: $this->passthroughHandler(),
        );
    }

    private function interceptor(RecordingAccessRuleFixture $rule): RouteAccessInterceptor
    {
        $accessRuleRegistry = new AccessRuleRegistry();
        $accessRuleRegistry->register(declarationClass: AccessDeclarationFixture::class, rule: $rule);

        return new RouteAccessInterceptor(accessRuleRegistry: $accessRuleRegistry);
    }

    private function context(string $method, ServerRequestInterface $request): CallContextInterface
    {
        return new CallContext(
            target: Target::fromReflectionMethod(
                reflection: new \ReflectionMethod(objectOrMethod: AccessRouteControllerFixture::class, method: $method),
                classOrObject: AccessRouteControllerFixture::class,
            ),
            arguments: [],
            attributes: [ServerRequestInterface::class => $request],
        );
    }

    private function passthroughHandler(): HandlerInterface
    {
        $handler = $this->createStub(HandlerInterface::class);
        $handler->method('handle')->willReturn(self::HANDLER_RESULT);

        return $handler;
    }
}
