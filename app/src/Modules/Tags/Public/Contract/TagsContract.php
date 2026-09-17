<?php

declare(strict_types=1);

namespace App\Modules\Tags\Public\Contract;

use App\Modules\Tags\Public\Dto\ResolvedTagsDto;
use App\Modules\Tags\Public\Dto\TagDtoCollection;

/**
 * Публичный контракт модуля Tags: единственная синхронная дверь соседей к меткам.
 *
 * Обе операции пакетные по построению — набор текстов или набор идентификаторов на одну запись или
 * на один ответ, а не вызов на каждую метку.
 *
 * Разрешение текстов создаёт недостающие метки и вызывается изнутри транзакции соседа, поэтому
 * откат соседа уносит и созданные метки. Чтение мягкое: несуществующая метка просто отсутствует в
 * результате, и потребитель сам решает, что делать с её отсутствием.
 */
interface TagsContract
{
    /**
     * Превращает набор текстов в идентификаторы меток: существующие переиспользуются, недостающие
     * создаются от имени указанного автора.
     *
     * @param list<string> $texts
     */
    public function resolve(array $texts, string $creatorUserId): ResolvedTagsDto;

    /**
     * @param list<string> $tagIds
     */
    public function textsByIds(array $tagIds): TagDtoCollection;
}
