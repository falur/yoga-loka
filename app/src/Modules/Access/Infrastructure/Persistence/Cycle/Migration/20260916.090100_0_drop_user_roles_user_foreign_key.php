<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Migration;

use Cycle\Migrations\Migration;

/**
 * Снимает межмодульный внешний ключ user_roles.user_id -> users.id.
 *
 * Таблица users принадлежит модулю User, таблица user_roles — модулю Access, а межмодульные внешние
 * ключи не используются как основа согласованности (docs/arch.md, «Владение данными»). В кодовой базе
 * нет ни одного сценария, который жёстко удаляет строку users (`UserRepository` не имеет метода
 * `delete`), поэтому правило CASCADE этого ключа на практике никогда не срабатывало.
 *
 * Данные не меняются: снимается только ограничение. Колонка user_id, внешний ключ на roles и
 * уникальный индекс (user_id, role_id) остаются как есть. Откат возвращает ключ с прежними правилами.
 */
class DropUserRolesUserForeignKey extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('user_roles')
            ->dropForeignKey(columns: ['user_id'])
            ->update();
    }

    public function down(): void
    {
        // indexCreate: false — как и в исходной миграции, отдельный индекс по user_id ключом не
        // заводился: выборки идут через уникальный индекс (user_id, role_id).
        $this->table('user_roles')
            ->addForeignKey(
                columns: ['user_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();
    }
}
