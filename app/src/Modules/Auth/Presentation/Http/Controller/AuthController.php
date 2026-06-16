<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Controller;

use App\Modules\Auth\Application\Command\CompleteRegistration\CompleteRegistrationCommand;
use App\Modules\Auth\Application\Command\CompleteRegistration\CompleteRegistrationHandler;
use App\Modules\Auth\Application\Command\Logout\LogoutCommand;
use App\Modules\Auth\Application\Command\Logout\LogoutHandler;
use App\Modules\Auth\Application\Command\RefreshTokens\RefreshTokensCommand;
use App\Modules\Auth\Application\Command\RefreshTokens\RefreshTokensHandler;
use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeCommand;
use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeHandler;
use App\Modules\Auth\Application\Command\VerifyLoginCode\VerifyLoginCodeCommand;
use App\Modules\Auth\Application\Command\VerifyLoginCode\VerifyLoginCodeHandler;
use App\Modules\Auth\Presentation\Http\Filter\LogoutFilter;
use App\Modules\Auth\Presentation\Http\Filter\RefreshFilter;
use App\Modules\Auth\Presentation\Http\Filter\RegisterFilter;
use App\Modules\Auth\Presentation\Http\Filter\RequestCodeFilter;
use App\Modules\Auth\Presentation\Http\Filter\VerifyCodeFilter;
use App\Modules\Auth\Presentation\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Presentation\Http\Middleware\RequireAuthenticatedMiddleware;
use App\Modules\Auth\Presentation\Http\Resource\TokenPairResource;
use App\Modules\Auth\Presentation\Http\Resource\VerifyResultResource;
use App\Shared\Infrastructure\Framework\Middleware\RateLimitMiddleware;
use GianTiaga\SpiralCqrs\CommandBusInterface;
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
            command: new VerifyLoginCodeCommand(email: $verifyCodeFilter->email, code: $verifyCodeFilter->code),
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
            command: new RefreshTokensCommand(refreshToken: $refreshFilter->refreshToken),
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
}
