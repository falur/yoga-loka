<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Queue;

final readonly class OutboxQueueHeaders
{
    public const string OUTBOX_ID = 'outboxId';
    public const string OUTBOX_TYPE = 'outboxType';

    public function __construct(
        public string|null $outboxId,
        public string|null $outboxType,
    ) {}

    /**
     * @param array<int|string, mixed> $headers
     */
    public static function fromHeaders(array $headers): self
    {
        return self::fromLines(
            outboxId: self::headerLine(headers: $headers, name: self::OUTBOX_ID),
            outboxType: self::headerLine(headers: $headers, name: self::OUTBOX_TYPE),
        );
    }

    public static function fromLines(string|null $outboxId, string|null $outboxType): self
    {
        return new self(
            outboxId: self::normalize($outboxId),
            outboxType: self::normalize($outboxType),
        );
    }

    private static function normalize(string|null $value): string|null
    {
        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @param array<int|string, mixed> $headers
     */
    private static function headerLine(array $headers, string $name): string|null
    {
        if (!\array_key_exists(key: $name, array: $headers)) {
            return null;
        }

        $headerValue = $headers[$name];

        if (\is_string($headerValue)) {
            return $headerValue;
        }

        if (!\is_array($headerValue) || $headerValue === []) {
            return null;
        }

        $headerLines = [];

        foreach ($headerValue as $item) {
            if (\is_string($item) && $item !== '') {
                $headerLines[] = $item;
            }
        }

        return $headerLines === [] ? null : \implode(separator: ',', array: $headerLines);
    }
}
