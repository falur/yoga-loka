<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Http\Access\Fixture;

use App\Shared\Infrastructure\Spiral\Http\Access\AccessRule;
use Psr\Http\Message\ServerRequestInterface;

/** Правило-дублёр: запоминает вызов и при необходимости отказывает заданным исключением. */
final class RecordingAccessRuleFixture implements AccessRule
{
    public int $calls = 0;

    public object|null $declaration = null;

    public ServerRequestInterface|null $request = null;

    public function __construct(
        private readonly \Throwable|null $refusal = null,
    ) {}

    #[\Override]
    public function check(object $declaration, ServerRequestInterface $request): void
    {
        $this->calls++;
        $this->declaration = $declaration;
        $this->request = $request;

        if ($this->refusal !== null) {
            throw $this->refusal;
        }
    }
}
