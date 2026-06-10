<?php

declare(strict_types=1);

namespace Tests;

use Cycle\Database\DatabaseInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Set;
use Spiral\Core\Container;
use Spiral\Testing\TestableKernelInterface;
use Spiral\Testing\TestCase as BaseTestCase;
use Spiral\Translator\TranslatorInterface;
use Tests\App\TestKernel;

class TestCase extends BaseTestCase
{
    public function createAppInstance(Container $container = new Container()): TestableKernelInterface
    {
        return TestKernel::create(
            directories: $this->defineDirectories(
                $this->rootDirectory(),
            ),
            container: $container,
        );
    }

    public function rootDirectory(): string
    {
        return __DIR__ . '/..';
    }

    public function defineDirectories(string $root): array
    {
        return TestRuntime::directories($root);
    }

    protected function setUp(): void
    {
        $this->beforeBooting(static function (ConfiguratorInterface $config): void {
            if (!$config->exists('session')) {
                return;
            }

            $config->modify('session', new Set('handler', null));
        });

        parent::setUp();

        $container = $this->getContainer();

        if ($container->has(TranslatorInterface::class)) {
            $container->get(TranslatorInterface::class)->setLocale('en');
        }
    }

    protected function tearDown(): void
    {
        try {
            \restore_error_handler();
            \restore_exception_handler();
            $this->disconnectDatabase();
        } finally {
            parent::tearDown();
        }

        // Раскомментируйте строку ниже, если нужно очищать runtime-директорию после тестов.
        // $this->cleanUpRuntimeDirectory();
    }

    private function disconnectDatabase(): void
    {
        $container = $this->getContainer();

        if (!$container->has(DatabaseInterface::class)) {
            return;
        }

        $database = $container->get(DatabaseInterface::class);
        $database->getDriver(DatabaseInterface::WRITE)->disconnect();
        $database->getDriver(DatabaseInterface::READ)->disconnect();
    }
}
