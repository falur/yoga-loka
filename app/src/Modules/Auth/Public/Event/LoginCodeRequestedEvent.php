<?php

declare(strict_types=1);

namespace App\Modules\Auth\Public\Event;

use GianTiaga\SpiralOutbox\IntegrationEventContract;

/**
 * Интеграционное событие: запрошен код входа, нужно отправить письмо. Payload — только примитивы
 * (email, code, locale), чтобы Valinor восстановил его без кастомных конструкторов VO.
 * Код идёт открытым текстом (иначе письмо не отправить) — короткоживущая запись, принятый риск.
 */
final readonly class LoginCodeRequestedEvent implements IntegrationEventContract
{
    public function __construct(
        public string $email,
        public string $code,
        public string $locale,
    ) {}
}
