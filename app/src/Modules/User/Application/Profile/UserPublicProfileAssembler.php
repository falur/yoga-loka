<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Profile;

use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Domain\Entity\User;
use App\Modules\Media\Application\View\MediaView;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Собирает публичный профиль из доменной сущности User, разрешая аватар через модуль Media. Аватара
 * может не быть: если у пользователя не задан аватар или его медиа недоступно (удалено/не готово) —
 * avatar = null. Сервер не выдумывает ссылку-заглушку, дефолтный аватар подставляет клиент.
 *
 * Аватар отдаётся одним значением MediaView (оригинал + все готовые конверсии), чтобы клиент сам
 * выбрал профиль показа, а потребители одной ссылки (уведомления, пуш) брали avatar?->original?->url.
 * Ссылки разрешает FindMediaUrl, проекцию в MediaView делает MediaUrlsResult::toView().
 *
 * Межмодульный Query идёт через QueryBus: шина возвращает ровно тип Handler::handle()
 * (MediaUrlsResult|null), поэтому контракт «медиа недоступно -> null -> аватара нет» сохраняется без
 * try-catch, а middleware обработчика (в том числе #[LogOperation]) работает.
 */
final readonly class UserPublicProfileAssembler
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private FindMediaUrlHandler $findMediaUrlHandler,
    ) {}

    public function fromUser(User $user): UserPublicProfileView
    {
        return new UserPublicProfileView(
            userId: $user->id->value(),
            name: $user->name->value(),
            avatar: $this->avatar($user),
            locale: $user->locale,
        );
    }

    private function avatar(User $user): MediaView|null
    {
        $mediaId = $user->avatar->value();

        if ($mediaId === null) {
            return null;
        }

        $mediaUrls = $this->queryBus->dispatch(
            query: new FindMediaUrlQuery(mediaId: $mediaId),
            handler: $this->findMediaUrlHandler->handle(...),
        );

        // Аватара нет, если медиа недоступно или оригинал удалён (readyOriginalRemoved): отдаём null
        // «всё или ничего», без конверсий удалённого оригинала. Заглушку рисует клиент.
        if ($mediaUrls === null || $mediaUrls->original === null) {
            return null;
        }

        return $mediaUrls->toView(id: $mediaId, position: null);
    }
}
