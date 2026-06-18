<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Cycle;

use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class PostPracticeTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): PostPractice
    {
        if ($value === null) {
            return PostPractice::none();
        }

        return PostPractice::pointingTo($value);
    }

    public static function uncastValue(PostPractice|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
