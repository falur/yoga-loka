<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Http\Access;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Правило доступа маршрута: проверяет одно объявление доступа, объявленное атрибутом метода.
 *
 * Интерфейс нейтрален: общая часть HTTP-границы знает только его и класс объявления, а сами
 * правила приносит модуль-владелец доступа.
 */
interface AccessRule
{
    /**
     * Пропускает запрос либо бросает доменное исключение отказа.
     *
     * @param object $declaration объявление доступа — экземпляр атрибута целевого метода
     */
    public function check(object $declaration, ServerRequestInterface $request): void;
}
