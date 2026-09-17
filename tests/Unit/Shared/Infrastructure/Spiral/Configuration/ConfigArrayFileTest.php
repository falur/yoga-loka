<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;
use App\Shared\Infrastructure\Spiral\Configuration\Exception\ConfigArrayFileException;
use PHPUnit\Framework\TestCase;

final class ConfigArrayFileTest extends TestCase
{
    /**
     * ConfigArrayFile — не объект, а набор статических функций: конструктор закрыт, чтобы его
     * нельзя было создать по недосмотру (тот же приём, что EntityColumnsCatalogTest).
     */
    public function testCannotBeInstantiatedFromOutsideButConstructorIsSafe(): void
    {
        $reflection = new \ReflectionClass(ConfigArrayFile::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertTrue($reflection->isFinal());

        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke($instance);

        self::assertInstanceOf(ConfigArrayFile::class, $instance);
    }

    public function testReadReturnsArrayFromValidFile(): void
    {
        $data = ConfigArrayFile::read(path: __DIR__ . '/Fixture/valid-config-array.php');

        self::assertSame(['key' => 'value'], $data);
    }

    public function testReadThrowsWhenFileDoesNotReturnArray(): void
    {
        $path = __DIR__ . '/Fixture/invalid-config-array.php';

        $this->expectException(ConfigArrayFileException::class);
        $this->expectExceptionMessage(\sprintf('Файл конфигурации `%s` должен возвращать массив.', $path));

        ConfigArrayFile::read(path: $path);
    }

    public function testIntReturnsIntValueAsIs(): void
    {
        self::assertSame(86_400, ConfigArrayFile::int(value: 86_400, default: 1));
    }

    public function testIntParsesNumericString(): void
    {
        self::assertSame(3600, ConfigArrayFile::int(value: '3600', default: 1));
    }

    public function testIntFallsBackToDefaultForNonNumericValue(): void
    {
        self::assertSame(1, ConfigArrayFile::int(value: 'not-a-number', default: 1));
        self::assertSame(1, ConfigArrayFile::int(value: null, default: 1));
    }

    public function testStringReturnsStringValueAsIs(): void
    {
        self::assertSame('imagick', ConfigArrayFile::string(value: 'imagick', default: 'gd'));
    }

    public function testStringFallsBackToDefaultForNonStringValue(): void
    {
        self::assertSame('gd', ConfigArrayFile::string(value: null, default: 'gd'));
        self::assertSame('gd', ConfigArrayFile::string(value: 123, default: 'gd'));
    }
}
