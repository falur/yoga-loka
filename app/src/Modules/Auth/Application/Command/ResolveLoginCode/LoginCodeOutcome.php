<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\ResolveLoginCode;

/**
 * Исход транзакционного разбора кода. Внутренний enum между ResolveLoginCode (коммитит запись)
 * и нетранзакционным оркестратором VerifyLoginCode (по исходу строит ответ или бросает 401).
 */
enum LoginCodeOutcome
{
    case NoCode;
    case Expired;
    case Exhausted;
    case Wrong;
    case NotAllowed;
    case Verified;
    case NeedsProfile;
}
