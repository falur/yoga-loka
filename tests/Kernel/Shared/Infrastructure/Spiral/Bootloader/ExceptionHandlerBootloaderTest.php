<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Bootloader;

use App\Shared\Infrastructure\Spiral\Bootloader\ExceptionHandlerBootloader;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\Environment\AppEnvironment;
use Spiral\Boot\FinalizerInterface;
use Spiral\Exceptions\ExceptionHandler;
use Spiral\Exceptions\Renderer\JsonRenderer;
use Spiral\Exceptions\Reporter\FileReporter;
use Spiral\Exceptions\Reporter\LoggerReporter;
use Tests\TestCase;

final class ExceptionHandlerBootloaderTest extends TestCase
{
    public function testInitRegistersJsonRendererInsideRunningCallback(): void
    {
        $handler = new ExceptionHandler();
        $bootloader = new ExceptionHandlerBootloader($handler);

        $kernel = $this->createStub(AbstractKernel::class);
        // AbstractKernel::__destruct() обращается к $finalizer; у созданного без конструктора
        // stub-а свойство не инициализировано, поэтому подставляем безопасную заглушку.
        // Привязка к приватному API фреймворка (AbstractKernel::$finalizer) — чинить при апгрейде Spiral.
        (new \ReflectionProperty(AbstractKernel::class, 'finalizer'))
            ->setValue($kernel, $this->createStub(FinalizerInterface::class));
        $kernel->method('running')->willReturnCallback(static function (\Closure ...$callbacks): void {
            foreach ($callbacks as $callback) {
                $callback();
            }
        });

        $bootloader->init($kernel);

        self::assertInstanceOf(JsonRenderer::class, $handler->getRenderer('application/json'));
    }

    public function testBootRegistersFileReporterOnlyInLocalEnvironment(): void
    {
        $logger = $this->getContainer()->get(LoggerReporter::class);
        $files = $this->getContainer()->get(FileReporter::class);

        $localHandler = new ExceptionHandler();
        (new ExceptionHandlerBootloader($localHandler))->boot(logger: $logger, files: $files, appEnv: AppEnvironment::Local);

        $productionHandler = new ExceptionHandler();
        (new ExceptionHandlerBootloader($productionHandler))->boot(
            logger: $logger,
            files: $files,
            appEnv: AppEnvironment::Production,
        );

        self::assertCount(2, $this->reporters($localHandler));
        self::assertCount(1, $this->reporters($productionHandler));
    }

    /**
     * @return array<int, mixed>
     */
    private function reporters(ExceptionHandler $handler): array
    {
        // Привязка к приватному API фреймворка (ExceptionHandler::$reporters) — чинить при апгрейде Spiral.
        /** @var array<int, mixed> $reporters */
        $reporters = (new \ReflectionProperty(ExceptionHandler::class, 'reporters'))->getValue($handler);

        return $reporters;
    }
}
