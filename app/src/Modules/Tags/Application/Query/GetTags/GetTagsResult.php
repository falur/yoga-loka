<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Query\GetTags;

use App\Shared\Domain\Collection\TypedCollection;

/**
 * Карта «идентификатор тега -> его текст» для обогащения ресурсов записи в других модулях.
 * Ключ — данные времени выполнения (tagId), значения однородны (текст тега), поэтому это
 * допустимая типизированная карта, обёрнутая в именованную коллекцию для передачи между модулями.
 *
 * @extends TypedCollection<string, string>
 */
final class GetTagsResult extends TypedCollection {}
