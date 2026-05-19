<?php

declare(strict_types=1);

namespace Tools\PHPStan\Tests\Unit\PHPStan\Fixtures;

#[MagicScalarAttribute('/api/v1/health', 'GET')]
final class MagicScalarLiteralsAllowed
{
    private const string STATUS_OK = 'ok';
    private const int DEFAULT_LIMIT = 20;

    public function run(): string
    {
        if (\str_contains(haystack: self::STATUS_OK, needle: 'o')) {
            return self::STATUS_OK;
        }

        return MagicScalarStatus::Ok->value;
    }

    public function limit(): int
    {
        return self::DEFAULT_LIMIT;
    }

    public function exception(): void
    {
        throw new MagicScalarAllowedException('Понятное сообщение ошибки.');
    }

    public function response(): MagicScalarAllowedResponse
    {
        return new MagicScalarAllowedResponse(message: 'Понятное сообщение ответа.');
    }

    public function view(MagicScalarViews $views): string
    {
        return $views->render(path: 'swagger/index');
    }

    public function namedBooleanArgument(MagicScalarTechnicalSwitches $technicalSwitches): void
    {
        $technicalSwitches->configure(recursive: true, bubble: false);
    }

    public function parameterDefaults(
        string $dsn = 'redis://redis:6379/0',
        string $namespace = 'yoga_loka_cache',
        int $lifetime = 0,
    ): string {
        return \sprintf('%s:%s:%d', $dsn, $namespace, $lifetime);
    }

    public function stringHelpers(string $resourceName): string
    {
        return \sprintf(
            '%s:%s',
            \str('api/{resource}')
                ->replace('{resource}', $resourceName)
                ->append('/index')
                ->toString(),
            $resourceName,
        );
    }

    public function callableTuple(): string
    {
        return $this->call(operation: [self::class, 'callableTarget']);
    }

    public static function callableTarget(): string
    {
        return self::STATUS_OK;
    }

    /**
     * @param callable(): string $operation
     */
    private function call(callable $operation): string
    {
        return $operation();
    }
}

enum MagicScalarStatus: string
{
    case Ok = 'ok';
}

#[\Attribute]
final readonly class MagicScalarAttribute
{
    public function __construct(
        public string $path,
        public string $method,
    ) {}
}

final class MagicScalarAllowedException extends \Exception
{
    public static function fromValue(string $section, string $targetClass): self
    {
        return new self(\strtr(
            string: 'Не удалось преобразовать раздел `{section}` в `{targetClass}`.',
            from: [
                '{section}' => $section,
                '{targetClass}' => $targetClass,
            ],
        ));
    }
}

final readonly class MagicScalarAllowedResponse
{
    public function __construct(
        public string $message,
    ) {}
}

final readonly class MagicScalarViews
{
    public function render(string $path): string
    {
        return $path;
    }
}

final readonly class MagicScalarTechnicalSwitches
{
    public function configure(bool $recursive, bool $bubble): void {}
}
