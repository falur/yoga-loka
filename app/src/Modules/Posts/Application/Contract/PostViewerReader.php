<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Contract;

use App\Modules\Posts\Application\Data\PostViewerFlagsData;

/**
 * Флаг «оценил я» по записям для конкретного зрителя: признак вне агрегата Post (лайк принадлежит
 * зрителю, не записи), поэтому читается отдельным Reader, а не полем Post.
 */
interface PostViewerReader
{
    /**
     * @param list<string> $postIds
     */
    public function likedByMe(array $postIds, string $viewerId): PostViewerFlagsData;
}
