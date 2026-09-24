<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration\Queue;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Interceptors\InterceptorInterface;

final readonly class QueueInterceptorsConfig
{
    /**
     * Интерсептор объявляется именем класса, готовым объектом или `Autowire` — последним его
     * дописывает bootloader пакета `gian-tiaga/spiral-outbox`. Форма `Autowire` в объединении
     * ровно одна: две одинаковые по структуре ветви (`Autowire<InterceptorInterface>` и
     * `Autowire<CoreInterceptorInterface>`) неразличимы для сопоставителя, и он отказывался
     * выбирать между ними. Устаревший `CoreInterceptorInterface` остаётся допустимым именем
     * класса и готовым объектом.
     *
     * @param list<class-string<InterceptorInterface>|class-string<CoreInterceptorInterface>|InterceptorInterface|CoreInterceptorInterface|Autowire<InterceptorInterface>> $push
     * @param list<class-string<InterceptorInterface>|class-string<CoreInterceptorInterface>|InterceptorInterface|CoreInterceptorInterface|Autowire<InterceptorInterface>> $consume
     */
    public function __construct(
        public array $push = [],
        public array $consume = [],
    ) {}
}
