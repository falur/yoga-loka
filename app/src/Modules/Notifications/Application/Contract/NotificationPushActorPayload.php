<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

/**
 * Автор-инициатор в push-payload (FCM). FCM data — плоская строковая карта, поэтому аватар здесь одна
 * ссылка (original url) либо null, а не полное медиа (MediaDto). Ссылку разрешают из id медиа-аватара к моменту
 * отправки, поэтому она валидна (push доставляется сразу). null на месте этого DTO — «автора нет».
 */
final readonly class NotificationPushActorPayload
{
    public function __construct(
        public string $id,
        public string $name,
        public string|null $avatarUrl,
    ) {}
}
