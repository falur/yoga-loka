<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns;

final class RegistrationTicketColumns
{
    public const string TABLE = 'auth_registration_tickets';

    public const string ID = 'id';
    public const string EMAIL = 'email';
    public const string TICKET_HASH = 'ticket_hash';
    public const string EXPIRES_AT = 'expires_at';
    public const string CONSUMED_AT = 'consumed_at';

    private function __construct() {}
}
