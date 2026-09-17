<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Contract;

use App\Modules\Posts\Application\Data\CommentViewerFlagsData;

/**
 * Флаг «оценил я» по комментариям для конкретного зрителя: признак вне агрегата Comment (лайк
 * принадлежит зрителю, не комментарию), поэтому читается отдельным Reader, а не полем Comment.
 */
interface CommentViewerReader
{
    /**
     * @param list<string> $commentIds
     */
    public function likedByMe(array $commentIds, string $viewerId): CommentViewerFlagsData;
}
