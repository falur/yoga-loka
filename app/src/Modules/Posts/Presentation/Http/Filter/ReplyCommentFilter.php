<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\Input\Route;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ответ на комментарий: <id> родительского комментария из пути (uuid), обязательный текст (<=2000)
 * и упоминания (<=50 uuid).
 */
final class ReplyCommentFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Route(key: 'id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $text;

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 50)]
    #[Assert\All([new Assert\Uuid()])]
    public array $mentions = [];
}
