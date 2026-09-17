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
use App\Shared\Infrastructure\Spiral\Bootloader\ConfigBootloader;
use App\Shared\Infrastructure\Spiral\Bootloader\RoutesBootloader;
use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;
use App\Shared\Infrastructure\Spiral\Http\Access\AccessRuleRegistry;
use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
use Spiral\Auth\Transport\HeaderTransport;
use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader;
use Spiral\Bootloader\Auth\HttpAuthBootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Config\Patch\Group;
use Spiral\Config\Patch\Set;
use Spiral\Core\Container\Autowire;
use Spiral\Router\GroupRegistry;
use Spiral\SendIt\Config\MailerConfig as SpiralMailerConfig;
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

    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

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

    /** @param ConfiguratorInterface<object> $config */
    public function init(
        HttpAuthBootloader $httpAuth,
        SpiralAuthBootloader $auth,
        ViewsBootloader $views,
        I18nBootloader $i18n,
        ConfiguratorInterface $config,
        ConfigBootloader $configBootloader,
    ): void {
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

        // Переводы модуля лежат внутри модуля: удаление модуля не оставляет переводов в чужих папках.
        $i18n->addDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/locale', \dirname(path: __DIR__, levels: 3)),
        );

        // Каталог миграций модуля дописывается в общий механизм: файлы остаются внутри модуля,
        // а удаление модуля не оставляет миграций в чужих папках.
        $config->modify(
            section: MigrationConfig::CONFIG,
            patch: new Append(
                position: self::MIGRATION_VENDOR_DIRECTORIES,
                key: null,
                value: \sprintf(
                    '%s/Infrastructure/Persistence/Cycle/Migration',
                    \dirname(path: __DIR__, levels: 3),
                ),
            ),
        );

        // Типизированный конфиг модуля лежит внутри модуля: удаление модуля не оставляет
        // конфигурации в чужих папках. Значения секции 'mailer' переносятся в boot() (см. ниже),
        // а не через setDefaults() здесь: секцию 'mailer' уже забирает себе
        // Spiral\SendIt\Bootloader\MailerBootloader::init() своим собственным setDefaults(), и
        // ConfigManager::setDefaults() бросает исключение при повторном вызове для той же секции.
        $configBootloader->addConfigurationDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Configuration', \dirname(path: __DIR__, levels: 3)),
        );
    }

    /** @param ConfiguratorInterface<object> $config */
    public function boot(
        IntegrationEventRoutingContract $integrationEventRouting,
        GroupRegistry $routeGroups,
        AccessRuleRegistry $accessRuleRegistry,
        ConfiguratorInterface $config,
    ): void {
        // Значения mailer.php модуля накладываются на секцию 'mailer' поверх дефолтов
        // Spiral\SendIt\Bootloader\MailerBootloader (см. пояснение в init()). modify() безопасен
        // именно в boot(): к этому моменту init() уже отработал у ВСЕХ bootloader-ов (в т.ч. у
        // MailerBootloader), поэтому секция 'mailer' гарантированно проинициализирована, а порядок
        // AuthBootloader и MailerBootloader в Kernel не имеет значения.
        $mailerConfig = ConfigArrayFile::read(path: \sprintf(
            '%s/Infrastructure/Spiral/Configuration/mailer.php',
            \dirname(path: __DIR__, levels: 3),
        ));
        $config->modify(
            section: SpiralMailerConfig::CONFIG,
            patch: new Group(
                new Set(key: 'dsn', value: $mailerConfig['dsn']),
                new Set(key: 'from', value: $mailerConfig['from']),
                new Set(key: 'queueConnection', value: $mailerConfig['queueConnection']),
                new Set(key: 'queue', value: $mailerConfig['queue']),
            ),
        );

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
