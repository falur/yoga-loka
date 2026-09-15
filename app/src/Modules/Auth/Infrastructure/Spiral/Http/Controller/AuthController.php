<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Controller;

use App\Modules\Auth\Application\Command\CompleteRegistration\CompleteRegistrationCommand;
use App\Modules\Auth\Application\Command\CompleteRegistration\CompleteRegistrationHandler;
use App\Modules\Auth\Application\Command\Logout\LogoutCommand;
use App\Modules\Auth\Application\Command\Logout\LogoutHandler;
use App\Modules\Auth\Application\Command\RefreshTokens\RefreshTokensCommand;
use App\Modules\Auth\Application\Command\RefreshTokens\RefreshTokensHandler;
use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeCommand;
use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeHandler;
use App\Modules\Auth\Application\Command\RevokeUserSession\RevokeUserSessionCommand;
use App\Modules\Auth\Application\Command\RevokeUserSession\RevokeUserSessionHandler;
use App\Modules\Auth\Application\Command\VerifyLoginCode\VerifyLoginCodeCommand;
use App\Modules\Auth\Application\Command\VerifyLoginCode\VerifyLoginCodeHandler;
use App\Modules\Auth\Application\Query\GetUserSessions\GetUserSessionsHandler;
use App\Modules\Auth\Application\Query\GetUserSessions\GetUserSessionsQuery;
use App\Modules\Auth\Application\View\SessionView;
use App\Modules\Auth\Infrastructure\Spiral\Http\Filter\ListSessionsFilter;
use App\Modules\Auth\Infrastructure\Spiral\Http\Filter\LogoutFilter;
use App\Modules\Auth\Infrastructure\Spiral\Http\Filter\RefreshFilter;
use App\Modules\Auth\Infrastructure\Spiral\Http\Filter\RegisterFilter;
use App\Modules\Auth\Infrastructure\Spiral\Http\Filter\RequestCodeFilter;
use App\Modules\Auth\Infrastructure\Spiral\Http\Filter\RevokeSessionFilter;
use App\Modules\Auth\Infrastructure\Spiral\Http\Filter\VerifyCodeFilter;
use App\Modules\Auth\Infrastructure\Spiral\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Infrastructure\Spiral\Http\Middleware\RequireAuthenticatedMiddleware;
use App\Modules\Auth\Infrastructure\Spiral\Http\Resource\SessionResource;
use App\Modules\Auth\Infrastructure\Spiral\Http\Resource\TokenPairResource;
use App\Modules\Auth\Infrastructure\Spiral\Http\Resource\VerifyResultResource;
use App\Shared\Infrastructure\Spiral\Http\Middleware\RateLimitMiddleware;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\CollectionResponse;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
use Spiral\Core\Container\Autowire;
use Spiral\Router\Annotation\Route;
use Spiral\Translator\TranslatorInterface;

final readonly class AuthController
{
    /**
     * Запрос кода входа на email. Ответ всегда 204 (наличие пользователя не раскрываем).
     */
    #[Route(
        route: '/api/v1/auth/code/request',
        name: 'api.v1.auth.code.request',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 5, 'perSeconds' => 60]),
        ],
    )]
    public function requestCode(
        RequestCodeFilter $requestCodeFilter,
        TranslatorInterface $translator,
        CommandBusInterface $commandBus,
        RequestLoginCodeHandler $requestLoginCodeHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new RequestLoginCodeCommand(
                email: $requestCodeFilter->email,
                requestLocale: $translator->getLocale(),
            ),
            handler: $requestLoginCodeHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    /**
     * Проверка кода: пара токенов существующему пользователю или талон регистрации новому email.
     *
     * @return DataResponse<VerifyResultResource>
     */
    #[Route(
        route: '/api/v1/auth/code/verify',
        name: 'api.v1.auth.code.verify',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 10, 'perSeconds' => 60]),
        ],
    )]
    public function verifyCode(
        VerifyCodeFilter $verifyCodeFilter,
        CommandBusInterface $commandBus,
        VerifyLoginCodeHandler $verifyLoginCodeHandler,
    ): DataResponse {
        $verifyLoginCodeResult = $commandBus->dispatch(
            command: new VerifyLoginCodeCommand(
                email: $verifyCodeFilter->email,
                code: $verifyCodeFilter->code,
                ip: $verifyCodeFilter->clientIp,
                userAgent: $verifyCodeFilter->userAgent,
            ),
            handler: $verifyLoginCodeHandler->handle(...),
        );

        return new DataResponse(VerifyResultResource::fromResult($verifyLoginCodeResult));
    }

    /**
     * Завершение регистрации по талону: создаёт пользователя и выдаёт пару токенов.
     *
     * @return DataResponse<TokenPairResource>
     */
    #[Route(
        route: '/api/v1/auth/register',
        name: 'api.v1.auth.register',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 10, 'perSeconds' => 60]),
        ],
    )]
    public function register(
        RegisterFilter $registerFilter,
        TranslatorInterface $translator,
        CommandBusInterface $commandBus,
        CompleteRegistrationHandler $completeRegistrationHandler,
    ): DataResponse {
        $tokens = $commandBus->dispatch(
            command: new CompleteRegistrationCommand(
                ticket: $registerFilter->ticket,
                name: $registerFilter->name,
                nickname: $registerFilter->nickname,
                requestLocale: $translator->getLocale(),
                ip: $registerFilter->clientIp,
                userAgent: $registerFilter->userAgent,
            ),
            handler: $completeRegistrationHandler->handle(...),
        );

        return new DataResponse(TokenPairResource::fromPair($tokens));
    }

    /**
     * Обновление пары токенов по refresh-токену (ротация).
     *
     * @return DataResponse<TokenPairResource>
     */
    #[Route(
        route: '/api/v1/auth/refresh',
        name: 'api.v1.auth.refresh',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 20, 'perSeconds' => 60]),
        ],
    )]
    public function refresh(
        RefreshFilter $refreshFilter,
        CommandBusInterface $commandBus,
        RefreshTokensHandler $refreshTokensHandler,
    ): DataResponse {
        $tokens = $commandBus->dispatch(
            command: new RefreshTokensCommand(
                refreshToken: $refreshFilter->refreshToken,
                ip: $refreshFilter->clientIp,
                userAgent: $refreshFilter->userAgent,
            ),
            handler: $refreshTokensHandler->handle(...),
        );

        return new DataResponse(TokenPairResource::fromPair($tokens));
    }

    /**
     * Выход: отзыв всех токенов текущей сессии. Требует Bearer access-токен.
     */
    #[Route(
        route: '/api/v1/auth/logout',
        name: 'api.v1.auth.logout',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(
                alias: AuthTransportWithStorageMiddleware::class,
                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
            ),
            AuthContextAttributeMiddleware::class,
            RequireAuthenticatedMiddleware::class,
        ],
    )]
    public function logout(
        LogoutFilter $logoutFilter,
        CommandBusInterface $commandBus,
        LogoutHandler $logoutHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new LogoutCommand(authSessionId: $logoutFilter->authSessionId),
            handler: $logoutHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    /**
     * Список своих активных сессий с устройством, IP и пометкой текущей. Требует Bearer access-токен.
     *
     * @return CollectionResponse<SessionResource>
     */
    #[Route(
        route: '/api/v1/auth/sessions',
        name: 'api.v1.auth.sessions.index',
        methods: ['GET'],
        group: 'api',
        middleware: [
            new Autowire(
                alias: AuthTransportWithStorageMiddleware::class,
                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
            ),
            AuthContextAttributeMiddleware::class,
            RequireAuthenticatedMiddleware::class,
        ],
    )]
    public function sessions(
        ListSessionsFilter $listSessionsFilter,
        GetUserSessionsHandler $getUserSessionsHandler,
        QueryBusInterface $queryBus,
    ): CollectionResponse {
        $userSessions = $queryBus->dispatch(
            query: new GetUserSessionsQuery(
                userId: $listSessionsFilter->authUserId,
                currentSessionId: $listSessionsFilter->authSessionId,
            ),
            handler: $getUserSessionsHandler->handle(...),
        );

        $sessionResources = $userSessions->mapToList(
            static fn(SessionView $session): SessionResource => SessionResource::fromView($session),
        );

        return new CollectionResponse($sessionResources);
    }

    /**
     * Отзыв своей сессии по её id; требует Bearer access-токен; чужая/несуществующая → 404.
     */
    #[Route(
        route: '/api/v1/auth/sessions/<sessionId>',
        name: 'api.v1.auth.sessions.revoke',
        methods: ['DELETE'],
        group: 'api',
        middleware: [
            new Autowire(
                alias: AuthTransportWithStorageMiddleware::class,
                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
            ),
            AuthContextAttributeMiddleware::class,
            RequireAuthenticatedMiddleware::class,
        ],
    )]
    public function revokeSession(
        string $sessionId,
        RevokeSessionFilter $revokeSessionFilter,
        CommandBusInterface $commandBus,
        RevokeUserSessionHandler $revokeUserSessionHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new RevokeUserSessionCommand(
                userId: $revokeSessionFilter->authUserId,
                sessionId: $sessionId,
            ),
            handler: $revokeUserSessionHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }
}
