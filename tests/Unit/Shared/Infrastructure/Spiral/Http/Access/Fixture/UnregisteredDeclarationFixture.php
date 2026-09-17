<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Http\Access\Fixture;

/** Атрибут метода без зарегистрированного правила: объявлением доступа он не является. */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class UnregisteredDeclarationFixture {}
