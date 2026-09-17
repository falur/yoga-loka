<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Columns;

final class UserColumns
{
    public const string TABLE = 'users';

    public const string ID = 'id';
    public const string NAME = 'name';
    public const string SPIRITUAL_NAME = 'spiritual_name';
    public const string BIO = 'bio';
    public const string LOCATION = 'location';
    public const string EMAIL = 'email';
    public const string NICKNAME = 'nickname';
    public const string AVATAR_MEDIA_ID = 'avatar_media_id';
    public const string VERIFICATION = 'verification';
    public const string STATUS = 'status';
    public const string LOCALE = 'locale';
    public const string DELETED_AT = 'deleted_at';

    private function __construct() {}
}
