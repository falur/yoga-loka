<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Mapping;

final class InvalidConfigValueException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $path,
        public readonly string $expected,
        public readonly string $actual,
    ) {
        parent::__construct(\sprintf(
            'путь `%s`, ожидалось `%s`, получено `%s`',
            $this->path,
            $this->expected,
            $this->actual,
        ));
    }
}
