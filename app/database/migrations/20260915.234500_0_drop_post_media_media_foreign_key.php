<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

/**
 * Снимает межмодульный внешний ключ post_media.media_id -> media.id.
 *
 * Таблица media принадлежит модулю Media, таблица post_media — модулю Posts, а межмодульные внешние
 * ключи не используются как основа согласованности (docs/arch.md, «Владение данными»). Согласованность
 * ссылки обеспечивает проверка пригодности медиа через публичный контракт Media в сценарии создания
 * записи. Миграция, создавшая ключ, уже применена и не редактируется.
 *
 * Данные не меняются: снимается только ограничение. Колонка media_id, первичный ключ, внешний ключ на
 * posts и оба индекса post_media остаются как есть. Откат возвращает ключ с прежними правилами и
 * завершится ошибкой, если к этому моменту в post_media появятся ссылки на несуществующие медиа —
 * это ожидаемое следствие отказа от внешнего ключа.
 */
class DropPostMediaMediaForeignKey extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('post_media')
            ->dropForeignKey(columns: ['media_id'])
            ->update();
    }

    public function down(): void
    {
        // indexCreate: false — отдельный индекс по media_id ключом не заводился и при откате не нужен:
        // выборки вложений идут по post_id, а уникальный индекс (post_id, media_id) остаётся на месте.
        $this->table('post_media')
            ->addForeignKey(
                columns: ['media_id'],
                foreignTable: 'media',
                foreignKeys: ['id'],
                options: ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();
    }
}
