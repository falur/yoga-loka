<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Dto;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * Набор медиа с преобразованиями, ключ — идентификатор медиа: потребитель берёт готовое значение по
 * идентификатору (->get($mediaId)) без отдельного запроса на каждое медиа. Недоступные медиа (не
 * найдены, не финализированы) в набор не попадают — их идентификаторов в коллекции нет.
 *
 * @extends TypedCollection<string, MediaDto>
 */
final class MediaDtoCollection extends TypedCollection {}
