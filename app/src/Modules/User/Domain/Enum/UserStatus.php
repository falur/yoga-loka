<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Enum;

enum UserStatus: string
{
    case WaitingEmailConfirmation = 'waiting_email_confirmation';
    case Active = 'active';
    case Banned = 'banned';
    case Deleted = 'deleted';
}
