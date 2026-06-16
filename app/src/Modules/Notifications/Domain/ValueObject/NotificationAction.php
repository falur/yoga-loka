<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

/**
 * Deep-link уведомления как явный тип вместо ?VO (rules.md:21). Одно опциональное значение
 * (переход) -> один VO с двумя backing-VO внутри: перехода нет (обе none) либо есть (обе заданы).
 * На Entity вычисляется доменным методом action() из колонок action_type/action_id; в API/outbox
 * раскладывается в {actionType, actionId} либо null.
 */
final readonly class NotificationAction implements \JsonSerializable
{
    private function __construct(
        private NotificationActionType $actionType,
        private NotificationActionId $actionId,
    ) {}

    public static function none(): self
    {
        return new self(
            actionType: NotificationActionType::none(),
            actionId: NotificationActionId::none(),
        );
    }

    public static function linkTo(string $actionType, string $actionId): self
    {
        return new self(
            actionType: NotificationActionType::of($actionType),
            actionId: NotificationActionId::of($actionId),
        );
    }

    /**
     * Сборка из двух backing-VO колонок: оба заданы -> переход, иначе перехода нет (в т.ч. при
     * рассогласованной строке, где задана только одна колонка) — нормализуем к none.
     */
    public static function fromParts(NotificationActionType $actionType, NotificationActionId $actionId): self
    {
        if ($actionType->isPresent() && $actionId->isPresent()) {
            return new self(actionType: $actionType, actionId: $actionId);
        }

        return self::none();
    }

    public function hasLink(): bool
    {
        return $this->actionType->isPresent() && $this->actionId->isPresent();
    }

    public function actionType(): NotificationActionType
    {
        return $this->actionType;
    }

    public function actionId(): NotificationActionId
    {
        return $this->actionId;
    }

    public function equals(self $other): bool
    {
        return $this->actionType->equals($other->actionType)
            && $this->actionId->equals($other->actionId);
    }

    /**
     * @return array<string, string>|null
     */
    #[\Override]
    public function jsonSerialize(): array|null
    {
        if (!$this->hasLink()) {
            return null;
        }

        return [
            'actionType' => $this->actionType->presentValue(),
            'actionId' => $this->actionId->presentValue(),
        ];
    }
}
