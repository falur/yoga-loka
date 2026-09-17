<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;

return [
    'maxAttempts' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'OUTBOX_MAX_ATTEMPTS', default: 100),
        default: 100,
    )),
    'maxConsecutiveRelayFailures' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'OUTBOX_MAX_CONSECUTIVE_RELAY_FAILURES', default: 10),
        default: 10,
    )),
    'baseRelayRetryDelaySeconds' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'OUTBOX_BASE_RELAY_RETRY_DELAY_SECONDS', default: 1),
        default: 1,
    )),
    'maxRelayRetryDelaySeconds' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'OUTBOX_MAX_RELAY_RETRY_DELAY_SECONDS', default: 30),
        default: 30,
    )),
    'claimTimeoutSeconds' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'OUTBOX_CLAIM_TIMEOUT_SECONDS', default: 60),
        default: 60,
    )),
    'publishRetryDelaySeconds' => \max(1, ConfigArrayFile::int(
        value: \env(key: 'OUTBOX_PUBLISH_RETRY_DELAY_SECONDS', default: 60),
        default: 60,
    )),
];
