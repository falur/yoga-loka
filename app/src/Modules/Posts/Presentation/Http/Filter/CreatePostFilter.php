<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Создание записи. Лимиты применяются на HTTP-границе (422), чтобы превышение длины/количества и
 * кривой uuid медиа/упоминания не доходили до доменных VO (500): текст <=5000, медиа <=10 (uuid),
 * теги <=20 (длина <=50), упоминания <=50 (uuid).
 */
final class CreatePostFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Post]
    #[Assert\Length(max: 5000)]
    public string|null $text = null;

    #[Post]
    public bool $draft = false;

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
