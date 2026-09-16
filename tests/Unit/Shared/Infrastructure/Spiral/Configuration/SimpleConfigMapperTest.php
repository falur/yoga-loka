<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Mailer\MailerConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Spiral\Configuration\Exception\ConfigMappingException;
use App\Shared\Infrastructure\Spiral\Configuration\Migration\MigrationConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Outbox\OutboxConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Scaffolder\ScaffolderConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Scaffolder\ScaffolderDeclarationOptionsConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Session\SessionConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Translator\TranslatorConfig;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container\Autowire;
use Spiral\Session\Handler\CacheHandler;
use Symfony\Component\Translation\Dumper\PhpFileDumper;
use Symfony\Component\Translation\Loader\PhpFileLoader;

final class SimpleConfigMapperTest extends TestCase
{
    public function testHydratesMigrationConfigFromArray(): void
    {
        $config = $this->mapperFor(MigrationConfig::configName(), [
            'directory' => '/app/database/migrations/',
            'vendorDirectories' => ['cycle' => '/vendor/migrations'],
            'strategy' => 'StrategyClass',
            'nameGenerator' => 'NameGeneratorClass',
            'table' => 'migrations',
            'safe' => true,
        ])->map(section: MigrationConfig::configName(), targetClass: MigrationConfig::class);

        self::assertSame('/app/database/migrations/', $config->directory);
        self::assertSame('/vendor/migrations', $config->vendorDirectories['cycle']);
        self::assertSame('StrategyClass', $config->strategy);
        self::assertSame('NameGeneratorClass', $config->nameGenerator);
        self::assertSame('migrations', $config->table);
        self::assertTrue($config->safe);
    }

    public function testHydratesMailerConfigFromArray(): void
    {
        $config = $this->mapperFor(MailerConfig::configName(), [
            'dsn' => 'smtp://mailpit:1025',
            'from' => 'YogaLoka <noreply@yogaloka.test>',
            'queueConnection' => null,
            'queue' => null,
        ])->map(section: MailerConfig::configName(), targetClass: MailerConfig::class);

        self::assertSame('smtp://mailpit:1025', $config->dsn);
        self::assertSame('YogaLoka <noreply@yogaloka.test>', $config->from);
        self::assertNull($config->queueConnection);
        self::assertNull($config->queue);
    }

    public function testHydratesTranslatorConfigFromArray(): void
    {
        $config = $this->mapperFor(TranslatorConfig::configName(), [
            'locale' => 'en',
            'fallbackLocale' => 'ru',
            'directory' => '/app/locale',
            'directories' => ['shared' => '/app/shared-locale'],
            'autoRegister' => true,
            'loaders' => ['php' => PhpFileLoader::class],
            'dumpers' => ['php' => PhpFileDumper::class],
            'domains' => ['messages' => ['*']],
        ])->map(section: TranslatorConfig::configName(), targetClass: TranslatorConfig::class);

        self::assertSame('en', $config->locale);
        self::assertSame('ru', $config->fallbackLocale);
        self::assertSame('/app/locale', $config->directory);
        self::assertSame('/app/shared-locale', $config->directories['shared']);
        self::assertTrue($config->autoRegister);
        self::assertSame(PhpFileLoader::class, $config->loaders['php']);
        self::assertSame(PhpFileDumper::class, $config->dumpers['php']);
        self::assertSame(['*'], $config->domains['messages']->patterns);
    }

    public function testHydratesSessionConfigFromArray(): void
    {
        $config = $this->mapperFor(SessionConfig::configName(), [
            'lifetime' => 86400,
            'cookie' => 'sid',
            'secure' => true,
            'sameSite' => null,
            'handler' => new Autowire(CacheHandler::class),
        ])->map(section: SessionConfig::configName(), targetClass: SessionConfig::class);

        self::assertSame(86400, $config->lifetime);
        self::assertSame('sid', $config->cookie);
        self::assertTrue($config->secure);
        self::assertNull($config->sameSite);
        self::assertInstanceOf(Autowire::class, $config->handler);
    }

    public function testHydratesOutboxConfigFromArray(): void
    {
        $config = $this->mapperFor(OutboxConfig::configName(), [
            'maxAttempts' => 7,
            'maxConsecutiveRelayFailures' => 5,
            'baseRelayRetryDelaySeconds' => 2,
            'maxRelayRetryDelaySeconds' => 20,
            'claimTimeoutSeconds' => 45,
            'publishRetryDelaySeconds' => 90,
        ])->map(section: OutboxConfig::configName(), targetClass: OutboxConfig::class);

        self::assertSame(7, $config->maxAttempts);
        self::assertSame(5, $config->maxConsecutiveRelayFailures);
        self::assertSame(2, $config->baseRelayRetryDelaySeconds);
        self::assertSame(20, $config->maxRelayRetryDelaySeconds);
        self::assertSame(45, $config->claimTimeoutSeconds);
        self::assertSame(90, $config->publishRetryDelaySeconds);
    }

    public function testHydratesScaffolderConfigFromArray(): void
    {
        $config = $this->mapperFor(ScaffolderConfig::configName(), [
            'header' => [],
            'directory' => '/app/src',
            'namespace' => 'App',
            'declarations' => [
                'config' => ['namespace' => 'Shared\Infrastructure\Spiral\Configuration'],
            ],
            'defaults' => [
                'declarations' => [
                    'config' => [
                        'namespace' => 'Config',
                        'postfix' => 'Config',
                        'class' => \Spiral\Scaffolder\Declaration\ConfigDeclaration::class,
                        'options' => [
                            'directory' => '/app/config',
                        ],
                    ],
                    'entity' => [
                        'namespace' => 'Entity',
                        'postfix' => '',
                        'options' => [
                            'annotated' => 'attributes',
                        ],
                    ],
                ],
            ],
        ])->map(section: ScaffolderConfig::configName(), targetClass: ScaffolderConfig::class);

        self::assertSame('/app/src', $config->directory);
        self::assertSame('App', $config->namespace);
        self::assertSame('Shared\Infrastructure\Spiral\Configuration', $config->declarations['config']->namespace);
        self::assertSame('Config', $config->defaults->declarations['config']->namespace);
        self::assertSame('Config', $config->defaults->declarations['config']->postfix);
        self::assertSame(
            \Spiral\Scaffolder\Declaration\ConfigDeclaration::class,
            $config->defaults->declarations['config']->class,
        );
        self::assertSame('/app/config', $config->defaults->declarations['config']->options->directory);
        self::assertNull($config->defaults->declarations['entity']->class);
        self::assertSame('attributes', $config->defaults->declarations['entity']->options->annotated);
    }

    /**
     * @param class-string $targetClass
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidRootConfigProvider')]
    public function testThrowsSafeExceptionForInvalidRootConfig(
        string $section,
        string $targetClass,
        array $config,
        string $path,
    ): void {
        try {
            $this->mapperFor($section, $config)->map(section: $section, targetClass: $targetClass);
        } catch (ConfigMappingException $exception) {
            self::assertStringContainsString("раздел конфигурации `{$section}`", $exception->getMessage());
            self::assertStringContainsString($targetClass, $exception->getMessage());
            self::assertStringContainsString($path, $exception->getMessage());
            self::assertStringNotContainsString('secret-password', $exception->getMessage());

            return;
        }

        self::fail('Ожидалось безопасное исключение маппинга.');
    }

    public static function invalidRootConfigProvider(): iterable
    {
        yield 'migration' => [
            MigrationConfig::configName(),
            MigrationConfig::class,
            [
                'directory' => [],
                'vendorDirectories' => [],
                'strategy' => 'StrategyClass',
                'nameGenerator' => 'NameGeneratorClass',
                'table' => 'migrations',
                'safe' => true,
            ],
            'directory',
        ];

        yield 'mailer' => [
            MailerConfig::configName(),
            MailerConfig::class,
            [
                'dsn' => [],
                'from' => 'YogaLoka <noreply@yogaloka.test>',
                'queueConnection' => null,
                'queue' => null,
            ],
            'dsn',
        ];

        yield 'translator' => [
            TranslatorConfig::configName(),
            TranslatorConfig::class,
            [
                'locale' => [],
                'fallbackLocale' => 'en',
                'directory' => '/app/locale',
                'directories' => [],
                'autoRegister' => true,
                'loaders' => ['php' => PhpFileLoader::class],
                'dumpers' => ['php' => PhpFileDumper::class],
                'domains' => ['messages' => ['*']],
            ],
            'locale',
        ];

        yield 'session' => [
            SessionConfig::configName(),
            SessionConfig::class,
            [
                'lifetime' => 'secret-password',
                'cookie' => 'sid',
                'secure' => true,
                'sameSite' => null,
                'handler' => null,
            ],
            'lifetime',
        ];

        yield 'outbox' => [
            OutboxConfig::configName(),
            OutboxConfig::class,
            [
                'maxAttempts' => [],
                'maxConsecutiveRelayFailures' => 10,
                'baseRelayRetryDelaySeconds' => 1,
                'maxRelayRetryDelaySeconds' => 30,
                'claimTimeoutSeconds' => 60,
                'publishRetryDelaySeconds' => 60,
            ],
            'maxAttempts',
        ];

        yield 'scaffolder' => [
            ScaffolderConfig::configName(),
            ScaffolderConfig::class,
            [
                'header' => [],
                'directory' => '/app/src',
                'namespace' => [],
                'declarations' => [],
                'defaults' => [
                    'declarations' => [],
                ],
            ],
            'namespace',
        ];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function mapperFor(string $section, array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[$section, $config]]);

        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()
                ->configureWith(new ConvertKeysToCamelCase())
                ->allowPermissiveTypes()
                ->allowScalarValueCasting()
                ->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }
}
