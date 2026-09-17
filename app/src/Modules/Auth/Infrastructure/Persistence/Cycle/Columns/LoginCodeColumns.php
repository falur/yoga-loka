<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns;

final class LoginCodeColumns
{
    public const string TABLE = 'auth_login_codes';

    public const string ID = 'id';
    public const string EMAIL = 'email';
    public const string CODE_HASH = 'code_hash';
    public const string EXPIRES_AT = 'expires_at';
    public const string ATTEMPTS = 'attempts';
    public const string CONSUMED_AT = 'consumed_at';

    private function __construct() {}
}
