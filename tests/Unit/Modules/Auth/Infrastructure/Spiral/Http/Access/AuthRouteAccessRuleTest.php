<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Spiral\Http\Access;

use App\Modules\Auth\Infrastructure\Spiral\Http\Access\AuthenticatedRouteRule;
use App\Modules\Auth\Infrastructure\Spiral\Http\Access\PublicRouteRule;
use App\Modules\Auth\Infrastructure\Spiral\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Auth\Public\Attribute\PublicRoute;
use App\Shared\Domain\Exception\AuthenticationException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AuthRouteAccessRuleTest extends TestCase
{
    public function testPublicRouteIsAllowedWithoutIdentity(): void
    {
        self::expectNotToPerformAssertions();

        new PublicRouteRule()->check(
            declaration: new PublicRoute(),
            request: new ServerRequest(method: 'GET', uri: '/api/v1/health'),
        );
    }

    public function testAuthenticatedRouteIsAllowedWithIdentity(): void
    {
        self::expectNotToPerformAssertions();

        $request = new ServerRequest(method: 'POST', uri: '/api/v1/auth/logout')
            ->withAttribute(AuthContextAttributeMiddleware::ATTRIBUTE_USER_ID, '0199-user');

        new AuthenticatedRouteRule()->check(declaration: new AuthenticatedRoute(), request: $request);
    }

    public function testAuthenticatedRouteIsRefusedWithoutIdentity(): void
    {
        $rule = new AuthenticatedRouteRule();
        $request = new ServerRequest(method: 'POST', uri: '/api/v1/auth/logout');

        try {
            $rule->check(declaration: new AuthenticatedRoute(), request: $request);
        } catch (AuthenticationException $exception) {
            self::assertSame('app.auth.unauthenticated', $exception->translationKey());
            self::assertSame('auth', $exception->translationDomain());
            self::assertSame(401, $exception->getCode());

            return;
        }

        self::fail('Правило сессии обязано отказать запросу без идентификатора пользователя.');
    }
}
