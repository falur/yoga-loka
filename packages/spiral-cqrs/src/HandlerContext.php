<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs;

readonly class HandlerContext
{
    public function __construct(
        public \ReflectionFunction $handlerReflection,
    ) {}

    public function operationName(string|null $name): string
    {
        if ($name !== null) {
            return $name;
        }

        return $this->handlerReflection->getClosureScopeClass()?->getShortName() ?? 'Unknown';
    }
}
