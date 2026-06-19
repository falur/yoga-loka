<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\Input\Route;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Репост целевой записи: <id> оригинала из пути (валидируется как uuid, чтобы кривой id давал 422),
 * тело — как у создания (текст/медиа/теги/упоминания) с теми же лимитами.
 */
final class RepostPostFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Route(key: 'id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;

    #[Post]
    #[Assert\Length(max: 5000)]
    public string|null $text = null;

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 10)]
    #[Assert\All([new Assert\Uuid()])]
    public array $mediaIds = [];

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 50)])]
    public array $tags = [];

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 50)]
    #[Assert\All([new Assert\Uuid()])]
    public array $mentions = [];
}
