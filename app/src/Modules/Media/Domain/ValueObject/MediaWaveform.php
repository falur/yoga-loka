<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Волна аудио — массив амплитуд для отрисовки прогресса воспроизведения «как в Telegram».
 *
 * Составной VO без естественного строкового представления, поэтому не Stringable. Хранится
 * JSON-колонкой через MediaWaveformTypecast. Каждая амплитуда — целое 0..255, нормализованное
 * процессором при извлечении волны.
 */
final readonly class MediaWaveform implements \JsonSerializable
{
    private const int MIN_PEAK = 0;
    private const int MAX_PEAK = 255;

    /**
     * @param list<int> $peaks
     */
    private function __construct(
        private array $peaks,
    ) {}

    /**
     * @param list<int> $peaks
     */
    public static function fromPeaks(array $peaks): self
    {
        // Количество пиков валидируется доменным диапазоном MediaWaveformPeakCount (1..4096):
        // отсутствие/перебор пиков — невалидная волна.
        MediaWaveformPeakCount::fromInt(\count($peaks));

        foreach ($peaks as $peak) {
            if ($peak < self::MIN_PEAK || $peak > self::MAX_PEAK) {
                throw new InvalidDomainValueException(
                    \sprintf('Амплитуда волны должна быть от %d до %d.', self::MIN_PEAK, self::MAX_PEAK),
                );
            }
        }

        return new self(peaks: $peaks);
    }

    /**
     * @return list<int>
     */
    public function peaks(): array
    {
        return $this->peaks;
    }

    public function equals(self $other): bool
    {
        return $this->peaks === $other->peaks;
    }

    /**
     * @return list<int>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->peaks;
    }
}
