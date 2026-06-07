<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Repository;

/**
 * Маркер «сырая pending-строка разобралась без ошибок».
 *
 * Полный разбор каждого поля в fromDatabaseRow — это зонд порчи: любое битое поле
 * приводит к InvalidOutboxPendingRow. Если разбор прошёл, recovery такую строку
 * пропускает, поэтому удерживать разобранные поля не нужно — поведение их не читает.
 */
final readonly class ValidOutboxPendingRow extends OutboxPendingRow {}
