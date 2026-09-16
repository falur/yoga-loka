<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns;

final class AuthTokenColumns
{
    public const string TABLE = 'auth_tokens';

    public const string ID = 'id';
    public const string USER_ID = 'user_id';
    public const string SESSION_ID = 'session_id';
    public const string TYPE = 'type';
    public const string TOKEN_HASH = 'token_hash';
    public const string EXPIRES_AT = 'expires_at';
    public const string IP = 'ip';
    public const string USER_AGENT = 'user_agent';
    public const string CREATED_AT = 'created_at';

    private function __construct() {}
}
