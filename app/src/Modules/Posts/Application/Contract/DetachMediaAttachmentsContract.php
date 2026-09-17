<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Contract;

use App\Modules\Posts\Domain\ValueObject\PostMediaReference;

/**
 * Порт массовой записи: снятие вложений записей, указывающих на удалённое медиа соседнего модуля.
 * Порт назван своей операцией и остаётся отдельным от PostRepository, потому что удаление строки
 * `post_media` не проходит через доменный переход записи (Post не хранит свои вложения в памяти —
 * см. докблок PostMedia) и набор затронутых строк не ограничен сверху: одно и то же медиа
 * теоретически может быть вложено в произвольное число записей.
 */
interface DetachMediaAttachmentsContract
{
    /**
     * Массово удаляет все вложения, ссылающиеся на mediaId, одним DELETE. Возвращает число
     * затронутых строк. Естественно идемпотентен: повторный вызов с тем же mediaId находит уже
     * пустой набор строк и ничего не меняет.
     */
    public function detachByMediaId(PostMediaReference $mediaId): int;
}
