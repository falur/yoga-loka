<?php

declare(strict_types=1);

namespace App\Modules\Auth\Public\Attribute;

/**
 * Маршрут доступен без действующей сессии.
 *
 * Объявление обязательно: открытый доступ объявляется явно, чтобы его нельзя было спутать
 * с забытой декларацией.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class PublicRoute {}
