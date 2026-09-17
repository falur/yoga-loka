<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Persistence\Cycle\Migration;

use Cycle\Migrations\Migration;

/**
 * Создаёт таблицу `tags` — модуль Tags владеет ею с самого начала.
 *
 * Историческая миграция `20260617.160942_0_create_posts_domain_tables.php` (Posts) создавала эту
 * таблицу вместе с девятью своими — межмодульное владение таблицей закрыто переносом её создания
 * сюда, к фактическому владельцу. Колонка created_by_id хранит межмодульную ссылку на users.id без
 * внешнего ключа (docs/arch.md, «Владение данными»): согласованность обеспечивает сценарий
 * приложения, а не ограничение схемы.
 */
final class CreateTagsTable extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('tags')
            ->addColumn(name: 'id', type: 'uuid', options: ['nullable' => false])
            // Ширина 64 — намеренный запас над доменным максимумом TagText (50 символов):
            // VO остаётся строже схемы, а небольшой запас оставляет место для будущего смягчения длины.
            ->addColumn(name: 'text', type: 'string', options: ['length' => 64, 'nullable' => false])
            ->addColumn(name: 'created_by_id', type: 'uuid', options: ['nullable' => false])
            ->addColumn(name: 'created_at', type: 'datetime', options: ['nullable' => false])
            ->addColumn(name: 'updated_at', type: 'datetime', options: ['nullable' => false])
            ->setPrimaryKeys(keys: ['id'])
            ->addIndex(columns: ['text'], options: ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('tags')->drop();
    }
}
