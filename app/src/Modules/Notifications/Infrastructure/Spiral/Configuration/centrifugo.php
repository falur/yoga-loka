<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;

/**
 * Параметры HTTP API Centrifugo для realtime-доставки уведомлений.
 */
return [
    // Базовый URL HTTP API Centrifugo (без хвостового /publish).
    'apiUrl' => ConfigArrayFile::string(
        value: \env(key: 'CENTRIFUGO_API_URL', default: 'http://centrifugo:8000/api'),
        default: 'http://centrifugo:8000/api',
    ),

    // Ключ API Centrifugo (заголовок `Authorization: apikey <key>`).
    'apiKey' => ConfigArrayFile::string(
        value: \env(key: 'CENTRIFUGO_API_KEY', default: ''),
        default: '',
    ),
];
