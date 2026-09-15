<?php

declare(strict_types=1);

namespace App\Modules\Media\Public\Contract;

use App\Modules\Media\Public\Dto\MediaDtoCollection;

/**
 * Публичный контракт модуля Media: единственная синхронная дверь соседей к медиа.
 *
 * Все операции пакетные по построению — набор идентификаторов на один ответ или на одну запись, а не
 * вызов на каждое медиа.
 *
 * Чтение ссылок мягкое: недоступное медиа (не найдено, не финализировано) просто отсутствует в
 * результате, поэтому потребитель трактует его отсутствие как «медиа недоступно» и подставляет своё
 * значение по умолчанию без try-catch.
 *
 * Вложение строгое: непригодное медиа — это отказ всей операции типизированным исключением
 * (`app.media.not_found` 404, `app.media.access_denied` 403, `app.media.not_ready` 422,
 * `app.media.cannot_make_permanent` 422). Набор обходится в порядке передачи, поэтому ошибку даёт
 * первое непригодное медиа.
 *
 * Срок presigned-ссылки контракт не принимает: соседям он не нужен, значение по умолчанию держит
 * конфигурация Media.
 */
interface MediaContract
{
    /**
     * @param list<string> $mediaIds
     */
    public function urlsByIds(array $mediaIds): MediaDtoCollection;

    /**
     * Убеждается, что весь набор медиа можно вложить: каждое существует, принадлежит владельцу и
     * готово. Возвращать нечего: результат операции — отсутствие исключения, а сами медиа потребитель
     * уже знает по переданным идентификаторам.
     *
     * @param list<string> $mediaIds
     */
    public function ensureAttachable(array $mediaIds, string $ownerUserId): void;

    /**
     * Переводит весь набор медиа в постоянное состояние. Вызывается после `ensureAttachable()` в той
     * же транзакции потребителя.
     *
     * @param list<string> $mediaIds
     */
    public function makePermanent(array $mediaIds, string $ownerUserId): void;
}
