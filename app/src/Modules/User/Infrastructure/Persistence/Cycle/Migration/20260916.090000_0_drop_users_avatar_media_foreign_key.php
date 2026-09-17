<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Migration;

use Cycle\Migrations\Migration;

/**
 * Снимает межмодульный внешний ключ users.avatar_media_id -> media.id.
 *
 * Таблица media принадлежит модулю Media, таблица users — модулю User, а межмодульные внешние
 * ключи не используются как основа согласованности (docs/arch.md, «Владение данными»). Согласованность
 * ссылки уже сегодня обеспечивается без страховки на уровне этого ключа: чтение аватара
 * (`GetUserPublicProfileHandler::resolveAvatars()`) идёт через `MediaContract->urlsByIds()`, который
 * переживает отсутствующий идентификатор медиа.
 *
 * Данные не меняются: снимается только ограничение. Колонка avatar_media_id, первичный ключ и оба
 * уникальных индекса users (email, nickname) остаются как есть. Откат возвращает ключ с прежними
 * правилами.
 */
class DropUserAvatarMediaForeignKey extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('users')
            ->dropForeignKey(columns: ['avatar_media_id'])
            ->update();
    }

    public function down(): void
    {
        // indexCreate: false — как и в исходной миграции, отдельный индекс по avatar_media_id
        // ключом не заводился.
        $this->table('users')
            ->addForeignKey(
                columns: ['avatar_media_id'],
                foreignTable: 'media',
                foreignKeys: ['id'],
                options: ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();
    }
}
