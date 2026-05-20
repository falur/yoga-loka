<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Configuration;

use App\Infrastructure\Configuration\Mailer\MailerConfig;
use App\Infrastructure\Configuration\Migration\MigrationConfig;
use App\Infrastructure\Configuration\Scaffolder\ScaffolderConfig;
use App\Infrastructure\Configuration\Scaffolder\ScaffolderDeclarationConfig;
use App\Infrastructure\Configuration\Scaffolder\ScaffolderDeclarationOptionsConfig;
use App\Infrastructure\Configuration\Scaffolder\ScaffolderDefaultDeclarationConfig;
use App\Infrastructure\Configuration\Scaffolder\ScaffolderDefaultsConfig;
use App\Infrastructure\Configuration\Session\SessionConfig;
use App\Infrastructure\Configuration\Translator\TranslatorConfig;
use App\Infrastructure\Configuration\Translator\TranslatorDomainConfig;
use App\Infrastructure\Configuration\TypedConfig;
use Cycle\Migrations\Config\MigrationConfig as CycleMigrationConfig;
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
        $scaffolderConfig = $container->get(ScaffolderConfig::class);

        self::assertStringEndsWith('/app/database/migrations/', $migrationConfig->directory);
        self::assertSame('migrations', $migrationConfig->table);
        self::assertSame('smtp://mailpit:1025', $mailerConfig->dsn);
        self::assertSame('local', $mailerConfig->queue);
        self::assertSame('en', $translatorConfig->locale);
        self::assertArrayHasKey('php', $translatorConfig->loaders);
        self::assertArrayHasKey('messages', $translatorConfig->domains);
        self::assertSame(86400, $sessionConfig->lifetime);
        self::assertNull($sessionConfig->sameSite);
        self::assertNull($sessionConfig->handler);
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
        self::assertSame('en', $container->get(SpiralTranslatorConfig::class)->getDefaultLocale());
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
        yield ScaffolderConfig::class => [ScaffolderConfig::class];
    }
}
