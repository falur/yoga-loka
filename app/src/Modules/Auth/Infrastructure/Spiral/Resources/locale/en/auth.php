<?php

declare(strict_types=1);

return [
    'app.auth.code_email_subject' => 'Your YogaLoka login code',
    'app.auth.code_email_body' => 'Your login code: {code}. It is valid for 10 minutes. If you did not request it, just ignore this email.',
    'app.auth.invalid_code' => 'The code is invalid or has expired.',
    'app.auth.sign_in_not_allowed' => 'Sign-in is not available for this account.',
    'app.auth.invalid_ticket' => 'The registration ticket is invalid or has expired.',
    'app.auth.invalid_refresh' => 'The refresh token is invalid or has expired.',
    'app.auth.unauthenticated' => 'Authentication required.',
    'app.auth.session_not_found' => 'Session not found.',
];
