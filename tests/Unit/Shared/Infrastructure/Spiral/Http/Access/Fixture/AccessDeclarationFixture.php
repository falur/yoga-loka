<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Http\Access\Fixture;

/** Нейтральное объявление доступа для проверки интерсептора без участия бизнес-модулей. */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class AccessDeclarationFixture
{
    public function __construct(
        public string $name,
    ) {}
}
