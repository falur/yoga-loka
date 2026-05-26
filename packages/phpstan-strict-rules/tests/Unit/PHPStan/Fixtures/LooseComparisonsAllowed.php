<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan\Fixtures;

final class LooseComparisonsAllowed
{
    public function run(int $first, int $second, string $label): bool
    {
        $usesStrictEqual = $first === $second;
        $usesStrictNotEqual = $label !== '';
        $usesLess = $first < $second;
        $usesGreater = $first > $second;
        $usesLessOrEqual = $first <= $second;
        $usesGreaterOrEqual = $first >= $second;
        $sum = $first + $second;
        $text = $label . '-value';

        return $usesStrictEqual
            || $usesStrictNotEqual
            || $usesLess
            || $usesGreater
            || $usesLessOrEqual
            || $usesGreaterOrEqual
            || $sum > 0
            || $text !== '';
    }
}
