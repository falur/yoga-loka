<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class PostLessonTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string|null $value): PostLesson
    {
        if ($value === null) {
            return PostLesson::none();
        }

        return PostLesson::pointingTo($value);
    }

    public static function uncastValue(PostLesson|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
