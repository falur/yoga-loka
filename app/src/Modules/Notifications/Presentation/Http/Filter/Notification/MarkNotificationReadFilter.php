<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Filter\Notification;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Route;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Фильтр отметки уведомления прочитанным: authUserId из JWT-атрибута и id уведомления из сегмента
 * пути. id валидируется как UUID на HTTP-границе, чтобы битый формат давал 422, а не 500 из
 * доменного VO — симметрично проверке cursor в ListNotificationsFilter.
 */
final class MarkNotificationReadFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Route(key: 'id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;
}
