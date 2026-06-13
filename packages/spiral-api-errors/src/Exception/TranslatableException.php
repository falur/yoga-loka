<?php

declare(strict_types=1);

namespace GianTiaga\SpiralApiErrors\Exception;

interface TranslatableException
{
    public function translationKey(): string;

    public function translationDomain(): string;

    /**
     * @return array<string, string>
     */
    public function translationParameters(): array;
}
