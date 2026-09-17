<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;

/**
 * Параметры push-доставки через FCM (Firebase Cloud Messaging, HTTP v1).
 * Service-account JSON хранится вне репозитория, путь задаётся переменной окружения.
 */
return [
    // Идентификатор проекта Firebase.
    'projectId' => ConfigArrayFile::string(
        value: \env(key: 'FCM_PROJECT_ID', default: ''),
        default: '',
    ),

    // Путь к JSON service-account для аутентификации в FCM.
    'credentialsFile' => ConfigArrayFile::string(
        value: \env(key: 'FCM_CREDENTIALS_FILE', default: ''),
        default: '',
    ),
];
