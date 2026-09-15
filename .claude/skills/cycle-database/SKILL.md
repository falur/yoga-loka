---
name: cycle-database
description: >-
  Справочник по Database layer в Cycle ORM: конфигурация БД, миграции,
  транзакции, типы колонок, индексы, foreign keys.
  Используй при создании миграций, настройке подключения к БД, работе с транзакциями.
user-invocable: false
---

# Cycle ORM: Database и Migrations

## Миграции

### Структура миграции

```php
<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateUsersTableMigration extends Migration
{
    protected const DATABASE = null;  // null = default database

    public function up(): void
    {
        $this->table('users')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('email', 'string', ['length' => 255])
            ->addColumn('username', 'string', ['length' => 64])
            ->addColumn('password_hash', 'string', ['length' => 255])
            ->addColumn('name', 'string', ['length' => 100])
            ->addColumn('bio', 'text', ['nullable' => true])
            ->addColumn('avatar', 'string', ['nullable' => true])
            ->addColumn('is_verified', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['username'], ['unique' => true])
            ->addIndex(['created_at'])
            ->setPrimaryKeys(['id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('users')->drop();
    }
}
```

### Именование файлов

Формат: `YYYYMMDD_NNNNNN_description.php`

Пример: `20260304_000001_create_users_table.php`

## Типы колонок

### Числовые

```php
->addColumn('id', 'primary')                    // Auto-increment 32-bit
->addColumn('id', 'bigPrimary')                  // Auto-increment 64-bit
->addColumn('age', 'integer')                    // 32-bit int
->addColumn('views', 'bigInteger')               // 64-bit int
->addColumn('flags', 'tinyInteger')              // 8-bit int
->addColumn('rating', 'float')                   // float
->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
->addColumn('is_active', 'boolean', ['default' => true])
```

### Строковые

```php
->addColumn('name', 'string', ['length' => 255])  // VARCHAR
->addColumn('bio', 'text')                         // TEXT
->addColumn('content', 'longText')                  // LONGTEXT
```

### Дата/Время

```php
->addColumn('created_at', 'datetime')
->addColumn('birth_date', 'date')
->addColumn('start_time', 'time')
->addColumn('updated_at', 'timestamp')
```

### Специальные

```php
->addColumn('id', 'uuid')
->addColumn('metadata', 'json')
->addColumn('avatar', 'binary')
->addColumn('status', 'enum', ['values' => ['active', 'banned', 'deleted']])
```

### Опции колонок

| Опция | Тип | Описание |
|-------|-----|---------|
| `nullable` | bool | Разрешить NULL |
| `default` | mixed | Значение по умолчанию |
| `length` / `size` | int | Максимальная длина |
| `precision` | int | Общее кол-во цифр (decimal) |
| `scale` | int | Кол-во знаков после запятой |
| `values` | array | Значения enum |

## Операции с таблицами

### Создание

```php
$this->table('posts')
    ->addColumn('id', 'uuid', ['nullable' => false])
    ->addColumn('user_id', 'uuid')
    ->addColumn('text', 'text')
    ->addColumn('created_at', 'datetime')
    ->setPrimaryKeys(['id'])
    ->create();
```

### Изменение

```php
$this->table('users')
    ->addColumn('bio', 'text', ['nullable' => true])
    ->addColumn('avatar_url', 'string', ['nullable' => true])
    ->addIndex(['email'], ['unique' => true])
    ->update();
```

### Удаление

```php
$this->table('posts')->drop();
```

### Переименование

```php
$this->table('old_name')->rename('new_name');
```

## Операции с колонками

```php
// Добавление
->addColumn('status', 'string', ['length' => 50, 'default' => 'active'])

// Изменение типа/опций
->alterColumn('status', 'string', ['length' => 100])

// Переименование
->renameColumn('old_name', 'new_name')

// Удаление
->dropColumn('legacy_field')
```

## Индексы

```php
// Обычный индекс
->addIndex(['created_at'])

// Уникальный индекс
->addIndex(['email'], ['unique' => true])

// Составной индекс
->addIndex(['user_id', 'created_at'])

// Именованный индекс
->addIndex(['status', 'created_at'], ['name' => 'idx_status_created'])

// Изменение
->alterIndex(['email'], ['unique' => false])

// Удаление
->dropIndex(['email'])
```

## Foreign Keys

```php
// Добавление
->addForeignKey(
    ['user_id'],        // колонки в текущей таблице
    'users',            // целевая таблица
    ['id'],             // колонки в целевой таблице
    [
        'delete' => 'CASCADE',
        'update' => 'CASCADE',
        'indexCreate' => true,
    ],
)

// Составной FK
->addForeignKey(
    ['user_id', 'post_id'],
    'user_posts',
    ['user_id', 'post_id'],
    ['delete' => 'CASCADE'],
)

// Изменение
->alterForeignKey(['user_id'], 'users', ['id'], ['delete' => 'SET_NULL'])

// Удаление
->dropForeignKey(['user_id'])
```

### Действия FK

| Действие | Описание |
|----------|---------|
| `CASCADE` | Каскадное удаление/обновление |
| `SET_NULL` | Установить NULL (колонка должна быть nullable) |
| `RESTRICT` | Запретить операцию |
| `NO_ACTION` | Без действия (проверка отложена) |

## Primary Keys

```php
// Простой PK
->setPrimaryKeys(['id'])

// Составной PK
->setPrimaryKeys(['user_id', 'post_id'])
```

## Прямой доступ к БД в миграции

```php
public function up(): void
{
    // Вставка данных
    $this->database()->insert('roles')->values([
        ['name' => 'admin', 'slug' => 'admin'],
        ['name' => 'user', 'slug' => 'user'],
    ])->run();
}
```

## Транзакции

```php
use Cycle\Database\DatabaseInterface;

public function handle(DatabaseInterface $db): void
{
    $db->begin();
    try {
        // операции...
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
```

## CLI-команды миграций (Spiral)

```bash
# Создать миграцию
php app.php migrate:init

# Запустить все миграции
php app.php migrate

# Откатить последнюю
php app.php migrate:rollback

# Статус миграций
php app.php migrate:status

# Сгенерировать из Entity-схемы
php app.php cycle:migrate
```

## Финализация таблицы

| Метод | Описание |
|-------|---------|
| `create()` | Создать новую таблицу |
| `update()` | Применить изменения к существующей |
| `drop()` | Удалить таблицу |
| `rename(string)` | Переименовать |

## Best Practices

1. Всегда реализуй и `up()`, и `down()`
2. Одно логическое изменение на миграцию
3. Удаляй FK перед удалением колонок
4. Никогда не модифицируй опубликованные миграции — создавай новые
5. Используй UUID v7 для всех PK, не auto-increment
6. snake_case для имён таблиц и колонок (PostgreSQL convention)
