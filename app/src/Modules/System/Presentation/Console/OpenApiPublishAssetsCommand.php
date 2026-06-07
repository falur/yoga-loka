<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Console;

use App\Shared\Infrastructure\Framework\DirectoryAlias;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

#[AsCommand(
    name: 'openapi:publish-assets',
    description: 'Опубликовать локальные assets Swagger UI',
)]
final class OpenApiPublishAssetsCommand extends Command
{
    private const int DIRECTORY_PERMISSIONS = 0o775;

    public function perform(DirectoriesInterface $directories): int
    {
        $root = \rtrim(string: $directories->get(name: DirectoryAlias::Root->value), characters: '/');
        $sourceDirectory = \sprintf('%s/vendor/swagger-api/swagger-ui/dist', $root);
        $targetDirectory = \sprintf('%s/public/swagger-ui', $root);

        if (!\is_dir($sourceDirectory)) {
            $this->error(\sprintf('Swagger UI assets не найдены: %s', $sourceDirectory));

            return SymfonyCommand::FAILURE;
        }

        try {
            $this->copyDirectory(sourceDirectory: $sourceDirectory, targetDirectory: $targetDirectory);
        } catch (OpenApiAssetsPublicationException $exception) {
            $this->error($exception->getMessage());

            return SymfonyCommand::FAILURE;
        }

        $this->info(\sprintf('Swagger UI assets опубликованы: %s', $targetDirectory));

        return SymfonyCommand::SUCCESS;
    }

    private function copyDirectory(string $sourceDirectory, string $targetDirectory): void
    {
        $this->ensureDirectoryExists(directory: $targetDirectory);

        $iterator = new \RecursiveIteratorIterator(
            iterator: new \RecursiveDirectoryIterator(directory: $sourceDirectory, flags: \FilesystemIterator::SKIP_DOTS),
            mode: \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            $relativePath = \substr(string: $fileInfo->getPathname(), offset: \strlen(string: $sourceDirectory) + 1);
            $targetPath = \sprintf('%s/%s', $targetDirectory, $relativePath);

            if ($fileInfo->isDir()) {
                $this->ensureDirectoryExists(directory: $targetPath);

                continue;
            }

            if (!\copy(from: $fileInfo->getPathname(), to: $targetPath)) {
                throw new OpenApiAssetsPublicationException(\sprintf('Не удалось скопировать Swagger UI asset: %s.', $fileInfo->getPathname()));
            }
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (\is_dir($directory)) {
            return;
        }

        if (\mkdir(directory: $directory, permissions: self::DIRECTORY_PERMISSIONS, recursive: true) || \is_dir($directory)) {
            return;
        }

        throw new OpenApiAssetsPublicationException(\sprintf('Не удалось создать каталог Swagger UI assets: %s.', $directory));
    }
}
