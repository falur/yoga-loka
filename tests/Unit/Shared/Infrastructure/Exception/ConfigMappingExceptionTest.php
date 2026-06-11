<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use App\Shared\Infrastructure\Exception\ConfigMappingException;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Покрывает разбор пути в actualType(). MappingError (Valinor) — final, мокать нельзя,
 * поэтому строится реальная ошибка через MapperBuilder; путь подаётся в приватный
 * хелпер напрямую, т.к. числовой сегмент (:82,83,85) недостижим через канонические
 * пути Valinor: PHP нормализует ключ-строку «0» к int 0, и ветвь array_key_exists
 * по строковому ключу (:76) перехватывает его раньше.
 */
final class ConfigMappingExceptionTest extends TestCase
{
    public function testFromMappingErrorBuildsReadableMessage(): void
    {
        $exception = ConfigMappingException::fromMappingError(
            section: 'mailer',
            targetClass: ConfigMappingExceptionProbe::class,
            error: $this->mappingError(['value' => 'not-an-int']),
        );

        self::assertStringContainsString('Не удалось преобразовать раздел конфигурации `mailer`', $exception->getMessage());
        self::assertStringContainsString(ConfigMappingExceptionProbe::class, $exception->getMessage());
    }

    public function testActualTypeReturnsSourceTypeForEmptySegments(): void
    {
        self::assertSame('array', $this->actualType(['key' => 'value'], '.', 'fallback'));
    }

    public function testActualTypeReturnsFallbackWhenSourceIsNotArray(): void
    {
        self::assertSame('fallback', $this->actualType(['a' => 'scalar'], 'a.b', 'fallback'));
    }

    public function testActualTypeFollowsNumericSegments(): void
    {
        self::assertSame('string', $this->actualType(['x' => ['deep']], 'x.00', 'fallback'));
    }

    public function testActualTypeReturnsFallbackForMissingSegment(): void
    {
        self::assertSame('fallback', $this->actualType(['a' => 1], 'missing', 'fallback'));
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private function actualType(array $source, string $path, string $fallback): string
    {
        $actualType = (new \ReflectionMethod(ConfigMappingException::class, 'actualType'))
            ->invoke(null, $this->mappingError($source), $path, $fallback);

        self::assertIsString($actualType);

        return $actualType;
    }

    /**
     * @param array<array-key, mixed> $source
     */
    private function mappingError(array $source): MappingError
    {
        try {
            new MapperBuilder()->mapper()->map(ConfigMappingExceptionProbe::class, $source);
        } catch (MappingError $error) {
            return $error;
        }

        self::fail('Ожидалась ошибка маппинга конфигурации.');
    }
}

final readonly class ConfigMappingExceptionProbe
{
    public function __construct(
        public int $value,
    ) {}
}
