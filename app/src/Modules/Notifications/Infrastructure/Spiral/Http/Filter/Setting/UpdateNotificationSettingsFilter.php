<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\Setting;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\NestedArray;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Фильтр обновления настроек: список вложенных пунктов «вид × канал» через NestedArray.
 */
final class UpdateNotificationSettingsFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    /**
     * Верхняя граница списка: осмысленный размер — это «виды × каналы».
     * NotificationChannel содержит 3 канала, поэтому 300 оставляет запас под ~100 видов уведомлений
     * и при этом отбивает раздутое тело клиента на границе (422), не разворачивая его в N запросов
     * под транзакцией в Handler-е (класс OWASP «Unrestricted Resource Consumption»).
     *
     * @var list<NotificationSettingUpdateInput>
     */
    #[NestedArray(class: NotificationSettingUpdateInput::class, input: new Post(key: 'settings'))]
    #[Assert\Valid]
    #[Assert\Count(max: 300)]
    public array $settings = [];
}
