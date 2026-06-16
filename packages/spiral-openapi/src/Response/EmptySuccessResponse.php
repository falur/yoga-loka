<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;

/**
 * Пустой успешный ответ 204 No Content: без тела и без заголовка Content-Type.
 *
 * Трейт HasHttpResponseMetadata намеренно НЕ подключается: статус 204 неизменяем,
 * методы withStatus()/withHeader() здесь не нужны. Не добавлять трейт по аналогии
 * с AbstractJsonResponse.
 */
final class EmptySuccessResponse implements ConvertsToHttpResponse
{
    public function toResponse(): ResponseInterface
    {
        return new Response(status: HttpStatus::NoContent->value);
    }
}
