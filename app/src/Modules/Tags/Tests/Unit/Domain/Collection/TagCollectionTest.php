<?php

declare(strict_types=1);

namespace App\Modules\Tags\Tests\Unit\Domain\Collection;

use App\Modules\Tags\Domain\Collection\TagCollection;
use PHPUnit\Framework\TestCase;

final class TagCollectionTest extends TestCase
{
    public function testEmptyCollectionHasNoItems(): void
    {
        self::assertCount(0, new TagCollection());
    }
}
