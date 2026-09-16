<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Bootloader;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Application\Contract\TranslatorContract;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use App\Modules\Auth\Domain\Repository\RegistrationTicketRepository;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleAuthTokenRepository;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleLoginCodeRepository;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleRegistrationTicketRepository;
use App\Modules\Auth\Public\Attribute\AuthenticatedRoute;
use App\Modules\Auth\Public\Attribute\PublicRoute;
use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use App\Modules\Auth\Infrastructure\Spiral\Http\Access\AuthenticatedRouteRule;
use App\Modules\Auth\Infrastructure\Spiral\Http\Access\PublicRouteRule;
use App\Modules\Auth\Infrastructure\Spiral\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenIssuer;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Spiral\Auth\SpiralTokenStorage;
use App\Modules\Auth\Infrastructure\Spiral\Auth\UserActorProvider;
use App\Modules\Auth\Infrastructure\Spiral\Hash\HmacSecretHasher;
use App\Modules\Auth\Infrastructure\Spiral\Mail\SpiralLoginCodeMailer;
use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use App\Modules\Auth\Infrastructure\Spiral\Translation\SpiralTranslator;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Shared\Infrastructure\Spiral\Bootloader\RoutesBootloader;
use App\Shared\Infrastructure\Spiral\Http\Access\AccessRuleRegistry;
use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
use Spiral\Auth\Transport\HeaderTransport;
use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader;
use Spiral\Bootloader\Auth\HttpAuthBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Container\Autowire;
use Spiral\Router\GroupRegistry;
use Spiral\Views\Bootloader\ViewsBootloader;

/**
 * Каркас аутентификации модуля Auth. Доменные интерфейсы хранения трёх независимых корней
 * агрегатов связаны со своими Cycle-реализациями, контракты Application — с инфраструктурными
 * реализациями. Транспорт (Authorization: Bearer), хранилище токенов (cycle) и actor-provider
 * регистрируются кодом без app/config/auth.php. View-шаблоны модуля (например письмо с кодом
 * входа) лежат в Infrastructure/Spiral/Resources/views и регистрируются под namespace `auth`. Пара
 * LoginCodeRequestedEvent → SendLoginCodeJob регистрируется в outbox-реестре. Установление личности
 * подключается к группе маршрутов `api` целиком, а правила публичных атрибутов доступа — в общий
 * реестр правил HTTP-границы.
 */
final class AuthBootloader extends Bootloader
{
    /**
     * Namespace представлений модуля Auth (ссылка на шаблон: `auth:<имя>`).
     */
    public const string VIEW_NAMESPACE = 'auth';

    protected const BINDINGS = [
        AuthTokenRepository::class => CycleAuthTokenRepository::class,
        LoginCodeRepository::class => CycleLoginCodeRepository::class,
        RegistrationTicketRepository::class => CycleRegistrationTicketRepository::class,
        SecretHasherContract::class => HmacSecretHasher::class,
        TokenGeneratorContract::class => RandomTokenGenerator::class,
        AuthTokenStorageContract::class => AuthTokenIssuer::class,
        LoginCodeMailerContract::class => SpiralLoginCodeMailer::class,
        TranslatorContract::class => SpiralTranslator::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function defineDependencies(): array
    {
        // RoutesBootloader объявлен зависимостью намеренно: он должен загрузиться раньше, чтобы
        // middleware установления личности встало в группе `api` после собственного конвейера группы.
        return [
            HttpAuthBootloader::class,
            SpiralAuthBootloader::class,
            ViewsBootloader::class,
            RoutesBootloader::class,
        ];
    }

    public function init(HttpAuthBootloader $httpAuth, SpiralAuthBootloader $auth, ViewsBootloader $views): void
    {
        $views->addDirectory(
            namespace: self::VIEW_NAMESPACE,
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/views', \dirname(path: __DIR__, levels: 3)),
        );
        $httpAuth->addTransport(
            name: 'header',
            transport: new HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s'),
        );
        $httpAuth->addTokenStorage(name: 'cycle', storage: SpiralTokenStorage::class);
        $auth->addActorProvider(UserActorProvider::class);
    }

    public function boot(
        IntegrationEventRoutingContract $integrationEventRouting,
        GroupRegistry $routeGroups,
        AccessRuleRegistry $accessRuleRegistry,
    ): void {
        $integrationEventRouting->register(
            integrationEventClass: LoginCodeRequestedEvent::class,
            jobClass: SendLoginCodeJob::class,
        );

        // Установление личности не является требованием доступа: оно кладёт личность в запрос и
        // никому не отказывает, поэтому объявляется один раз на всю группу `api`.
        $routeGroups->getGroup(RoutesBootloader::GROUP_API)
            ->addMiddleware(new Autowire(
                alias: AuthTransportWithStorageMiddleware::class,
                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
            ))
            ->addMiddleware(AuthContextAttributeMiddleware::class);

        $accessRuleRegistry->register(declarationClass: PublicRoute::class, rule: new PublicRouteRule());
        $accessRuleRegistry->register(
            declarationClass: AuthenticatedRoute::class,
            rule: new AuthenticatedRouteRule(),
        );
    }
}
