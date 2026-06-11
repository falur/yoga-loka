<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\System\Console;

use App\Shared\Infrastructure\Framework\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Tests\TestCase;

final class OpenApiPublishAssetsCommandTest extends TestCase
{
    private string $workspace = '';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = \sprintf('%s/openapi-assets-%s', \sys_get_temp_dir(), \uniqid(prefix: '', more_entropy: true));
        \mkdir(directory: $this->workspace, permissions: 0o775, recursive: true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->removeRecursively($this->workspace);

        parent::tearDown();
    }

    public function testPublishesAssetsToTargetDirectory(): void
    {
        $root = $this->prepareRoot('success');
        $distDirectory = \sprintf('%s/vendor/swagger-api/swagger-ui/dist', $root);
        \mkdir(directory: \sprintf('%s/assets', $distDirectory), permissions: 0o775, recursive: true);
        \file_put_contents(\sprintf('%s/index.html', $distDirectory), '<html></html>');
        \file_put_contents(\sprintf('%s/assets/app.css', $distDirectory), 'body{}');
        // Целевой каталог уже существует — покрывает ранний возврат ensureDirectoryExists.
        \mkdir(directory: \sprintf('%s/public/swagger-ui', $root), permissions: 0o775, recursive: true);

        $this->useRoot($root);
        $output = $this->runCommand(command: 'openapi:publish-assets');

        self::assertStringContainsString('Swagger UI assets опубликованы', $output);
        self::assertFileExists(\sprintf('%s/public/swagger-ui/index.html', $root));
        self::assertFileExists(\sprintf('%s/public/swagger-ui/assets/app.css', $root));
    }

    public function testFailsWhenSourceAssetsMissing(): void
    {
        $root = $this->prepareRoot('missing-source');

        $this->useRoot($root);
        $output = $this->runCommand(command: 'openapi:publish-assets');

        self::assertStringContainsString('Swagger UI assets не найдены', $output);
    }

    public function testFailsWhenTargetDirectoryCannotBeCreated(): void
    {
        $root = $this->prepareRoot('mkdir-fail');
        $distDirectory = \sprintf('%s/vendor/swagger-api/swagger-ui/dist', $root);
        \mkdir(directory: $distDirectory, permissions: 0o775, recursive: true);
        \file_put_contents(\sprintf('%s/index.html', $distDirectory), '<html></html>');
        // Родитель целевого каталога — обычный файл, поэтому mkdir target не создаст каталог.
        \file_put_contents(\sprintf('%s/public', $root), 'не каталог');

        $this->useRoot($root);
        $output = $this->runCommandSwallowingWarnings('openapi:publish-assets');

        self::assertStringContainsString('Не удалось создать каталог Swagger UI assets', $output);
    }

    public function testFailsWhenAssetCannotBeCopied(): void
    {
        $root = $this->prepareRoot('copy-fail');
        $distDirectory = \sprintf('%s/vendor/swagger-api/swagger-ui/dist', $root);
        \mkdir(directory: $distDirectory, permissions: 0o775, recursive: true);
        \file_put_contents(\sprintf('%s/index.html', $distDirectory), '<html></html>');
        // На месте целевого файла заранее создаём каталог — \copy в файл вернёт false.
        \mkdir(directory: \sprintf('%s/public/swagger-ui/index.html', $root), permissions: 0o775, recursive: true);

        $this->useRoot($root);
        $output = $this->runCommandSwallowingWarnings('openapi:publish-assets');

        self::assertStringContainsString('Не удалось скопировать Swagger UI asset', $output);
    }

    private function prepareRoot(string $name): string
    {
        $root = \sprintf('%s/%s', $this->workspace, $name);
        \mkdir(directory: $root, permissions: 0o775, recursive: true);

        return $root;
    }

    private function useRoot(string $root): void
    {
        $directories = $this->getContainer()->get(DirectoriesInterface::class);

        $this->getContainer()->removeBinding(DirectoriesInterface::class);
        $this->getContainer()->bindSingleton(
            DirectoriesInterface::class,
            new class ($directories, $root) implements DirectoriesInterface {
                public function __construct(
                    private readonly DirectoriesInterface $directories,
                    private readonly string $root,
                ) {}

                #[\Override]
                public function has(string $name): bool
                {
                    return $this->directories->has($name);
                }

                #[\Override]
                public function set(string $name, string $path): DirectoriesInterface
                {
                    return $this->directories->set($name, $path);
                }

                #[\Override]
                public function get(string $name): string
                {
                    return $name === DirectoryAlias::Root->value ? $this->root : $this->directories->get($name);
                }

                #[\Override]
                public function getAll(): array
                {
                    return $this->directories->getAll();
                }
            },
        );
    }

    private function runCommandSwallowingWarnings(string $command): string
    {
        // \mkdir / \copy по «пути-файлу» эмитят PHP-warning; PHPUnit конвертирует его в ошибку,
        // поэтому на время прогона команды глушим обработчик — команда сама обрабатывает сбой
        // через OpenApiAssetsPublicationException и возвращает FAILURE.
        \set_error_handler(static fn(): bool => true);

        try {
            return $this->runCommand(command: $command);
        } finally {
            \restore_error_handler();
        }
    }

    private function removeRecursively(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }

        if (\is_file($path)) {
            \unlink($path);

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            iterator: new \RecursiveDirectoryIterator(directory: $path, flags: \FilesystemIterator::SKIP_DOTS),
            mode: \RecursiveIteratorIterator::CHILD_FIRST,
        );

        // RecursiveDirectoryIterator с дефолтными флагами всегда отдаёт SplFileInfo;
        // PHPStan видит значение итератора как mixed, поэтому сужаем тип аннотацией
        // по образцу продакшн-команды (без недостижимого instanceof-guard'а).
        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            $fileInfo->isDir() ? \rmdir($fileInfo->getPathname()) : \unlink($fileInfo->getPathname());
        }

        \rmdir($path);
    }
}
