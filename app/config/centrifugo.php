<?php

declare(strict_types=1);

/**
 * Параметры HTTP API Centrifugo для realtime-доставки уведомлений.
 */
return [
    // Базовый URL HTTP API Centrifugo (без хвостового /publish).
    'apiUrl' => (string) \env('CENTRIFUGO_API_URL', 'http://centrifugo:8000/api'),

    // Ключ API Centrifugo (заголовок `Authorization: apikey <key>`).
    'apiKey' => (string) \env('CENTRIFUGO_API_KEY', ''),
];
