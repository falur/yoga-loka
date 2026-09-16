<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Contract;

use App\Modules\Posts\Application\Data\PostPageData;

/**
 * Чтение страницы записей автора под ленту. Своя лента (myFeed) и чужая лента (userFeed) — разные
 * методы, а не один с вычислением роли зрителя: разный набор видимых статусов задаёт разный запрос,
 * не разное поведение над одним и тем же результатом.
 */
interface PostReader
{
    /**
     * Своя лента: владелец видит все свои записи, кроме заблокированных модерацией.
     */
    public function myFeed(string $ownerUserId, string|null $cursor, int $limit): PostPageData;

    /**
     * Чужая лента: посторонний видит только опубликованные записи владельца.
     */
    public function userFeed(string $ownerUserId, string|null $cursor, int $limit): PostPageData;
}
