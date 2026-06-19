<?php

declare(strict_types=1);

namespace App\Modules\Tags\Application\Dto;

use Illuminate\Support\Collection;

/**
 * Карта «идентификатор тега -> его текст» для обогащения ресурсов записи в других модулях.
 * Ключ — данные времени выполнения (tagId), значения однородны (текст тега), поэтому это
 * допустимая типизированная карта, обёрнутая в именованную коллекцию для передачи между модулями.
 *
 * @extends Collection<string, string>
 */
final class TagTextCollection extends Collection {}
