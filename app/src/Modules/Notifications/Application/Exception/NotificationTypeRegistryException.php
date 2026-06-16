<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Exception;

use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * Ошибки реестра видов уведомлений — часть контракта NotificationTypeRegistryContract, поэтому
 * лежит в Application/Exception. Это внутренние ошибки конфигурации модулей-источников (вид не
 * зарегистрирован или зарегистрирован дважды), не клиентские 4xx.
 */
final class NotificationTypeRegistryException extends \DomainException
{
    public static function unknownType(NotificationTypeCode $code): self
    {
        return new self(\sprintf('Вид уведомления `%s` не зарегистрирован.', $code->value()));
    }

    public static function duplicateType(NotificationTypeCode $code): self
    {
        return new self(\sprintf('Вид уведомления `%s` уже зарегистрирован.', $code->value()));
    }
}
