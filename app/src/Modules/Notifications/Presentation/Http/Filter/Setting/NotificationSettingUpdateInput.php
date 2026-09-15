<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Filter\Setting;

use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Вложенный пункт обновления настроек «вид × канал». Семантика toggle: отсутствие enabled = выключено.
 * channel и type принимаются строкой и проверяются Handler-ом: enum нельзя типизировать прямо в Filter,
 * пока генератор OpenAPI (gian-tiaga/spiral-openapi) не строит схему для enum-свойства фильтра.
 */
final class NotificationSettingUpdateInput extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $type;

    #[Post]
    #[Assert\NotBlank]
    public string $channel;

    #[Post]
    public bool $enabled = false;
}
