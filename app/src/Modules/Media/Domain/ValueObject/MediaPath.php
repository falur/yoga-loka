<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use Ramsey\Uuid\Uuid;

final readonly class MediaPath implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 1024;

    private function __construct(
        private string $value,
    ) {}

    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
    {
        return self::fromString(
            \sprintf('uploads/%s/%s/source.%s', $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
        );
    }

    public static function imageConversion(
        MediaStorageKey $storageKey,
        MediaImageConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'images/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function videoConversion(
        MediaStorageKey $storageKey,
        MediaVideoConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'videos/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function audioConversion(
        MediaStorageKey $storageKey,
        MediaAudioConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'audios/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
    {
        $prefix = match ($type) {
            MediaType::Image => 'images',
            MediaType::Video => 'videos',
            MediaType::Audio => 'audios',
            MediaType::Document => 'documents',
        };

        return self::fromString(
            \sprintf('%s/%s/%s/source.%s', $prefix, $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
        );
    }

    public static function fromString(string $value): self
    {
        $value = \trim($value);
        self::assertValid($value);

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Расширение файла из последнего сегмента пути (без точки). Используется для построения
     * путей конверсий и готового оригинала с тем же форматом, что у загруженного файла.
     */
    public function extension(): string
    {
        $segments = \explode(separator: '/', string: $this->value);
        $fileName = (string) \end($segments);
        $dotPosition = \strrpos(haystack: $fileName, needle: '.');

        return $dotPosition === false ? '' : \substr(string: $fileName, offset: $dotPosition + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private static function sanitizeExtension(string $extension): string
    {
        $safeExtension = \strtolower(\trim($extension));

        if ($safeExtension === '' || \preg_match(pattern: '/^[a-z0-9]+$/', subject: $safeExtension) !== 1) {
            throw new InvalidDomainValueException('Расширение файла имеет неверный формат.');
        }

        return $safeExtension;
    }

    private static function assertValid(string $value): void
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Путь файла слишком длинный.');
        }

        $matches = [];
        if (\preg_match(
            pattern: '/^(uploads|images|videos|audios|documents)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
            subject: $value,
            matches: $matches,
        ) !== 1) {
            throw new InvalidDomainValueException('Путь файла имеет неверный формат.');
        }

        if (!Uuid::isValid($matches[3]) || $matches[2] !== \substr(string: $matches[3], offset: 0, length: 2)) {
            throw new InvalidDomainValueException('Путь файла имеет неверный раздел.');
        }
    }
}
