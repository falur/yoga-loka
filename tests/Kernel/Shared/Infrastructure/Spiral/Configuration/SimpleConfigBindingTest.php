<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Locale\LocaleConfig;
use App\Modules\Auth\Infrastructure\Spiral\Configuration\MailerConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Migration\MigrationConfig;
use App\Modules\Outbox\Infrastructure\Spiral\Configuration\OutboxConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Scaffolder\ScaffolderConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Scaffolder\ScaffolderDeclarationConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Scaffolder\ScaffolderDeclarationOptionsConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Scaffolder\ScaffolderDefaultDeclarationConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Scaffolder\ScaffolderDefaultsConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Session\SessionConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Translator\TranslatorConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Translator\TranslatorDomainConfig;
use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;
use App\Shared\Infrastructure\Spiral\DirectoryAlias;
use Cycle\Migrations\Config\MigrationConfig as CycleMigrationConfig;
use Spiral\Boot\DirectoriesInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Core\Container\Autowire;
use Spiral\Scaffolder\Config\ScaffolderConfig as SpiralScaffolderConfig;
use Spiral\SendIt\Config\MailerConfig as SpiralMailerConfig;
use Spiral\Session\Handler\CacheHandler;
use Spiral\Session\Config\SessionConfig as SpiralSessionConfig;
use Spiral\Translator\Config\TranslatorConfig as SpiralTranslatorConfig;
use Tests\TestCase;

final class SimpleConfigBindingTest extends TestCase
{
    /**
     * @param class-string $configClass
     */
    #[DataProvider('simpleRootConfigProvider')]
    public function testSimpleConfigIsRegisteredAsSingleton(string $configClass): void
    {
        $container = $this->getContainer();

        self::assertSame($container->get($configClass), $container->get($configClass));
    }

    public function testSimpleConfigValuesComeFromRealConfigurator(): void
    {
        $container = $this->getContainer();

        $migrationConfig = $container->get(MigrationConfig::class);
        $mailerConfig = $container->get(MailerConfig::class);
        $translatorConfig = $container->get(TranslatorConfig::class);
        $sessionConfig = $container->get(SessionConfig::class);
        $outboxConfig = $container->get(OutboxConfig::class);
        $scaffolderConfig = $container->get(ScaffolderConfig::class);

        // Каталог миграций — заглушка внутри runtime-директории (файлы переехали в модули): в
        // тестах runtime у каждого worker-а свой, поэтому путь сверяется с DirectoriesInterface.
        $runtimeDirectory = $container->get(DirectoriesInterface::class)->get(DirectoryAlias::Runtime->value);
        self::assertSame($runtimeDirectory . 'migrations/', $migrationConfig->directory);
        self::assertSame('migrations', $migrationConfig->table);
        self::assertSame('smtp://mailpit:1025', $mailerConfig->dsn);
        self::assertSame('local', $mailerConfig->queue);
        // locale приходит из env(LOCALE); вместо конкретного значения сверяем единый источник:
        // typed translator-конфиг и locale-конфиг читают ту же переменную, значит совпадают.
        self::assertSame($container->get(LocaleConfig::class)->default, $translatorConfig->locale);
        self::assertArrayHasKey('php', $translatorConfig->loaders);
        self::assertArrayHasKey('messages', $translatorConfig->domains);
        self::assertSame(86400, $sessionConfig->lifetime);
        self::assertNull($sessionConfig->sameSite);
        self::assertNull($sessionConfig->handler);
        self::assertSame(100, $outboxConfig->maxAttempts);
        self::assertSame(10, $outboxConfig->maxConsecutiveRelayFailures);
        self::assertSame(1, $outboxConfig->baseRelayRetryDelaySeconds);
        self::assertSame(30, $outboxConfig->maxRelayRetryDelaySeconds);
        self::assertSame(60, $outboxConfig->claimTimeoutSeconds);
        self::assertSame(60, $outboxConfig->publishRetryDelaySeconds);
        self::assertSame('App', $scaffolderConfig->namespace);
        self::assertArrayHasKey('config', $scaffolderConfig->declarations);
        self::assertArrayHasKey('entity', $scaffolderConfig->defaults->declarations);
    }

    public function testNestedSimpleConfigDtosAreNotRootTypedConfigs(): void
    {
        self::assertFalse(\is_subclass_of(ScaffolderDeclarationConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(ScaffolderDeclarationOptionsConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(ScaffolderDefaultDeclarationConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(ScaffolderDefaultsConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(TranslatorDomainConfig::class, TypedConfig::class));
    }

    public function testNativeFrameworkConfigsStillWork(): void
    {
        $container = $this->getContainer();

        self::assertSame('migrations', $container->get(CycleMigrationConfig::class)->getTable());
        self::assertSame('smtp://mailpit:1025', $container->get(SpiralMailerConfig::class)->getDSN());
        self::assertSame('sid', $container->get(SpiralSessionConfig::class)->getCookie());
        // Нативный translator-конфиг читает ту же env(LOCALE), что и наш typed-конфиг — сверяем их,
        // а не конкретную локаль, чтобы тест не зависел от значения LOCALE в окружении.
        self::assertSame(
            $container->get(TranslatorConfig::class)->locale,
            $container->get(SpiralTranslatorConfig::class)->getDefaultLocale(),
        );
        self::assertContains('config', $container->get(SpiralScaffolderConfig::class)->getDeclarations());
    }

    public function testSessionConfigAcceptsAutowireHandler(): void
    {
        $config = new SessionConfig(
            lifetime: 86400,
            cookie: 'sid',
            secure: true,
            sameSite: null,
            handler: new Autowire(CacheHandler::class),
        );

        self::assertInstanceOf(Autowire::class, $config->handler);
    }

    public static function simpleRootConfigProvider(): iterable
    {
        yield MigrationConfig::class => [MigrationConfig::class];
        yield MailerConfig::class => [MailerConfig::class];
        yield TranslatorConfig::class => [TranslatorConfig::class];
        yield SessionConfig::class => [SessionConfig::class];
        yield OutboxConfig::class => [OutboxConfig::class];
        yield ScaffolderConfig::class => [ScaffolderConfig::class];
    }
}
