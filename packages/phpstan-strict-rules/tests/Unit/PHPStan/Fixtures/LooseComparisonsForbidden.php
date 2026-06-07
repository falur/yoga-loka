<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan\Fixtures;

final class LooseComparisonsForbidden
{
    public function run(int $first, string $second, string|null $third): bool
    {
        $hasLooseEqual = $first == 1;
        $hasLooseNotEqual = $second != '';
        $hasAlternativeLooseNotEqual = $third <> null;

        return $hasLooseEqual || $hasLooseNotEqual || $hasAlternativeLooseNotEqual;
    }
}
