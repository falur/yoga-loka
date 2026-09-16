<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Persistence\Cycle\Columns;

final class StoredOutboxEventColumns
{
    public const string TABLE = 'outbox_events';

    public const string ID = 'id';
    public const string TYPE = 'type';
    public const string PAYLOAD = 'payload';
    public const string STATUS = 'status';
    public const string ATTEMPTS = 'attempts';
    public const string AVAILABLE_AT = 'available_at';
    public const string QUEUED_AT = 'queued_at';
    public const string HANDLED_AT = 'handled_at';
    public const string FAILED_AT = 'failed_at';
    public const string LAST_ERROR = 'last_error';

    private function __construct() {}
}
