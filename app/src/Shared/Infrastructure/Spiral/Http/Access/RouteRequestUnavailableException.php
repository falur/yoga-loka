<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Http\Access;

/**
 * Требование доступа маршрута проверяется без HTTP-запроса в контексте вызова Spiral.
 *
 * Это ошибка сборки приложения, а не пользователя: метод с `#[Route]` вызван в обход HTTP-границы,
 * которая кладёт запрос в контекст. Наружу такая ошибка уходит общим ответом 500 без подробностей.
 */
final class RouteRequestUnavailableException extends \LogicException {}
