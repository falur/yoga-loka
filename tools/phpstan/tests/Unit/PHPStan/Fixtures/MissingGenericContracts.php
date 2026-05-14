<?php

declare(strict_types=1);

namespace Tools\PHPStan\Tests\Unit\PHPStan\Fixtures;

/**
 * @property array $items
 * @method array names(array $ids)
 */
final class MissingGenericContracts
{
    /**
     * @param array $items
     * @return array
     */
    public function missingGeneric($items)
    {
        return $items;
    }

    public function varDoc(): void
    {
        /** @var array $items */
        $items = [];
    }
}
