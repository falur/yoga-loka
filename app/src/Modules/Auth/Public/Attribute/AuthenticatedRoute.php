<?php

declare(strict_types=1);

namespace App\Modules\Auth\Public\Attribute;

/** Маршрут доступен только с действующей сессией. */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class AuthenticatedRoute {}
