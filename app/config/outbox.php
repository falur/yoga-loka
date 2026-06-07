<?php

declare(strict_types=1);

return [
    'maxAttempts' => \max(1, (int) \env('OUTBOX_MAX_ATTEMPTS', 100)),
    'maxConsecutiveRelayFailures' => \max(1, (int) \env('OUTBOX_MAX_CONSECUTIVE_RELAY_FAILURES', 10)),
    'baseRelayRetryDelaySeconds' => \max(1, (int) \env('OUTBOX_BASE_RELAY_RETRY_DELAY_SECONDS', 1)),
    'maxRelayRetryDelaySeconds' => \max(1, (int) \env('OUTBOX_MAX_RELAY_RETRY_DELAY_SECONDS', 30)),
    'claimTimeoutSeconds' => \max(1, (int) \env('OUTBOX_CLAIM_TIMEOUT_SECONDS', 60)),
    'publishRetryDelaySeconds' => \max(1, (int) \env('OUTBOX_PUBLISH_RETRY_DELAY_SECONDS', 60)),
];
