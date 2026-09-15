# Миграция

## Назначение

Миграция фиксирует схему таблиц модуля-владельца на момент применения: создаёт и меняет его таблицы, индексы и внешние ключи.

## Когда применять

Применяй при любом изменении схемы своих таблиц. Уже применённую миграцию не редактируй — добавляй новую.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Migration;

use Cycle\Migrations\Migration;

final class CreatePostsTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('posts')
            ->addColumn(name: 'id', type: 'uuid', options: ['nullable' => false])
            ->addColumn(name: 'author_id', type: 'uuid', options: ['nullable' => false])
            ->addColumn(name: 'name', type: 'string', options: ['length' => 255, 'nullable' => false])
            ->addColumn(name: 'status', type: 'string', options: ['length' => 32, 'nullable' => false])
            ->addColumn(name: 'published_at', type: 'datetime', options: ['nullable' => true])
            ->addColumn(name: 'created_at', type: 'datetime', options: ['nullable' => false])
            ->addColumn(name: 'updated_at', type: 'datetime', options: ['nullable' => false])
            ->setPrimaryKeys(columns: ['id'])
            ->addIndex(columns: ['author_id', 'status'])
            ->create();

        $this->table('post_media')
            ->addColumn(name: 'post_id', type: 'uuid', options: ['nullable' => false])
            ->addColumn(name: 'media_id', type: 'uuid', options: ['nullable' => false])
            ->addColumn(name: 'position', type: 'integer', options: ['nullable' => false])
            ->setPrimaryKeys(columns: ['post_id', 'media_id'])
            ->addForeignKey(
                columns: ['post_id'],
                foreignTable: 'posts',
                foreignKeys: ['id'],
                options: ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(columns: ['post_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('post_media')->drop();
        $this->table('posts')->drop();
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Bootloader;

use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;

final class PostsMigrationBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

    /** @param ConfiguratorInterface<object> $config */
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function init(): void
    {
        // Каталог миграций модуля дописывается в общий механизм: файлы остаются внутри модуля,
        // а удаление модуля не оставляет миграций в чужих папках.
        $this->config->modify(
            section: MigrationConfig::CONFIG,
            patch: new Append(
                position: self::MIGRATION_VENDOR_DIRECTORIES,
                key: null,
                value: \sprintf(
                    '%s/Infrastructure/Persistence/Cycle/Migration',
                    \dirname(path: __DIR__, levels: 3),
                ),
            ),
        );
    }
}
```

## Что повторять

- Файл лежит в `Infrastructure/Persistence/Cycle/Migration` модуля-владельца, путь к каталогу регистрирует bootloader этого модуля.
- Миграция меняет только таблицы своего модуля; межмодульных внешних ключей нет.
- Имена таблиц и колонок пишутся строковым литералом: миграция фиксирует схему на момент применения и не следует за переименованием констант `{Entity}Columns`.
- Имена таблиц и колонок в `snake_case`, зарезервированные слова SQL не используются.
- Есть обе стороны: `up()` создаёт, `down()` откатывает в обратном порядке зависимостей.
- Уже применённая миграция не редактируется — изменение схемы оформляется новой миграцией.
- Имена файлов сохраняют единый порядок применения в рамках приложения.
- Ручной SQL не используется; он допустим, только когда schema/query builder не выражает операцию, и причина записывается комментарием рядом.

## Допустимые варианты

Несколько связанных таблиц одного модуля создаются одной миграцией. Данные переносятся отдельной миграцией, а не вместе с изменением структуры. Уникальный индекс по идентификатору доставки — обычный способ обеспечить идемпотентность потребителя события.
