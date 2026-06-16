<?php

declare(strict_types=1);

/**
 * Параметры push-доставки через FCM (Firebase Cloud Messaging, HTTP v1).
 * Service-account JSON хранится вне репозитория, путь задаётся переменной окружения.
 */
return [
    // Идентификатор проекта Firebase.
    'projectId' => (string) \env('FCM_PROJECT_ID', ''),

    // Путь к JSON service-account для аутентификации в FCM.
    'credentialsFile' => (string) \env('FCM_CREDENTIALS_FILE', ''),
];
