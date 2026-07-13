<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * Набор URL нескольких медиа, ключ — id медиа. Результат пакетного FindMediaUrls: потребитель берёт
 * готовый набор по id (->get($mediaId)) без отдельного запроса на каждое медиа. Недоступные медиа
 * (не финализированы, не найдены) в набор не попадают — их id в коллекции нет.
 *
 * @extends TypedCollection<string, MediaUrlsResult>
 */
final class MediaUrlsResultCollection extends TypedCollection {}
