<?php

declare(strict_types=1);

namespace App\Modules\Tags\Public\Dto;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * Набор публичных меток, ключ — идентификатор метки: потребитель берёт готовую метку по
 * идентификатору (->get($tagId)) без отдельного запроса на каждую. Несуществующие метки в набор не
 * попадают — их идентификаторов в коллекции нет. Порядок элементов частью контракта не является.
 *
 * @extends TypedCollection<string, TagDto>
 */
final class TagDtoCollection extends TypedCollection {}
