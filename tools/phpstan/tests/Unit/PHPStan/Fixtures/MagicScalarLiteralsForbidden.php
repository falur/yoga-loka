<?php

declare(strict_types=1);

namespace Tools\PHPStan\Tests\Unit\PHPStan\Fixtures;

final class MagicScalarLiteralsForbidden
{
    public function run(): MagicScalarLiteralResource
    {
        $status = 'active';
        $limit = 20;
        $enabled = true;

        if ($status === 'pending') {
            return new MagicScalarLiteralResource(status: 'pending', limit: $limit, enabled: $enabled);
        }

        $this->stringBuilder()->replace('forbidden', 'still-forbidden');

        return new MagicScalarLiteralResource(status: 'ok', limit: 30, enabled: false);
    }

    private function stringBuilder(): MagicScalarLiteralStringBuilder
    {
        return new MagicScalarLiteralStringBuilder();
    }
}

final readonly class MagicScalarLiteralResource
{
    public function __construct(
        public string $status,
        public int $limit,
        public bool $enabled,
    ) {}
}

final class MagicScalarLiteralStringBuilder
{
    public function replace(string $search, string $replace): self
    {
        return $this;
    }
}

final class MagicScalarFakeConfig
{
    public static function configName(): string
    {
        return 'fake_config';
    }
}
