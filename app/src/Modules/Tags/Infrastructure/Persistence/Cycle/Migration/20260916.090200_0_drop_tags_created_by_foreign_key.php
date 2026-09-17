<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Persistence\Cycle\Migration;

use Cycle\Migrations\Migration;

/**
 * Снимает межмодульный внешний ключ tags.created_by_id -> users.id.
 *
 * Таблица users принадлежит модулю User, таблица tags — модулю Tags, а межмодульные внешние ключи не
 * используются как основа согласованности (docs/arch.md, «Владение данными»). В кодовой базе нет ни
 * одного сценария, который жёстко удаляет строку users (`UserRepository` не имеет метода `delete`), а
 * в модуле Tags нет ни одной команды удаления — поэтому правило RESTRICT этого ключа на практике
 * никогда не срабатывало.
 *
 * Данные не меняются: снимается только ограничение. Колонка created_by_id, первичный ключ и уникальный
 * индекс по text остаются как есть. Откат возвращает ключ с прежними правилами.
 */
class DropTagsCreatedByForeignKey extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('tags')
            ->dropForeignKey(columns: ['created_by_id'])
            ->update();
    }

    public function down(): void
    {
        // indexCreate: false — как и в исходной миграции, отдельный индекс по created_by_id ключом
        // не заводился.
        $this->table('tags')
            ->addForeignKey(
                columns: ['created_by_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();
    }
}
