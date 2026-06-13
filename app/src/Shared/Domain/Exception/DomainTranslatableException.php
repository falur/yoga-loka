<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use GianTiaga\SpiralApiErrors\Exception\TranslatableException;

abstract class DomainTranslatableException extends \DomainException implements TranslatableException
{
    /**
     * @param array<string, string> $translationParameters
     */
    public function __construct(private readonly string $translationKey, private readonly array $translationParameters = [])
    {
        parent::__construct(message: $translationKey, code: $this->statusCode());
    }

    abstract protected function statusCode(): int;

    #[\Override]
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Домен перевода (= файл каталога) выводится из второго сегмента ключа:
     * `app.media.not_found` -> `media`, `app.system.swagger_ui_disabled` -> `system`.
     * Ключи без модульного сегмента переводятся в домене по умолчанию `messages`.
     */
    #[\Override]
    public function translationDomain(): string
    {
        return \explode(separator: '.', string: $this->translationKey)[1] ?? 'messages';
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function translationParameters(): array
    {
        return $this->translationParameters;
    }
}
