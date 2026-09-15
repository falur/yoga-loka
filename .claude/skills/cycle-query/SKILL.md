---
name: cycle-query
description: >-
  Справочник по Select, Repository и Query Builder в Cycle ORM.
  Используй при написании запросов к БД, создании кастомных репозиториев,
  фильтрации, сортировке, пагинации и работе с Select API.
user-invocable: false
---

# Cycle ORM: Repository, Select и Query Builder

## Custom Repository

```php
namespace App\Repository;

use App\Entity\User;
use App\Entity\ValueObject\Email;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function findByEmail(Email $email): ?User
    {
        return $this->findOne(['email' => (string) $email]);
    }

    public function findById(string $id): ?User
    {
        return $this->findByPK($id);
    }

    public function findActiveUsers(): \Cycle\ORM\Select
    {
        return $this->select()->where('status', 'active');
    }
}
```

Привязка к Entity:
```php
#[Entity(repository: UserRepository::class)]
class User {}
```

## Базовые методы Repository

| Метод | Описание |
|-------|---------|
| `findByPK($id)` | Найти по PK, null если нет |
| `findOne($criteria)` | Найти один по полям |
| `findAll($criteria)` | Найти все по критерию |
| `select()` | Получить Select для построения запроса |

> **ВАЖНО:** `findByPK()`, `findOne()`, `select()` используются ТОЛЬКО ВНУТРИ Repository.
> Бизнес-код вызывает ДОМЕННЫЕ методы: `findByEmail()`, `findActiveUsers()`.

## Select API

### Получение Select

```php
// Внутри Repository
$select = $this->select();

// Цепочки методов
$users = $this->select()
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(20)
    ->offset(0)
    ->fetchAll();
```

### WHERE условия

```php
// Простое равенство
->where('status', 'active')

// С оператором
->where('balance', '>', 100)

// BETWEEN
->where('id', 'between', 10, 20)

// IN
->where('id', 'in', new Parameter([1, 2, 3]))

// LIKE
->where('name', 'like', '%John%')

// IS NULL
->where('deleted_at', null)

// IS NOT NULL
->where('email', '!=', null)
```

### Логические операторы

```php
// AND (по умолчанию)
->where('status', 'active')
->where('role', 'admin')

// OR
->where('status', 'active')
->orWhere('role', 'admin')

// Группировка
->where(function (\Cycle\ORM\Select\QueryBuilder $q) {
    $q->where('status', 'active')
      ->orWhere('role', 'admin');
})
```

### Краткая нотация

```php
->where([
    'status' => 'active',
    'role' => 'admin',
])

->where([
    'id' => ['in' => new Parameter([1, 2, 3])],
])
```

### Сортировка

```php
->orderBy('created_at', 'DESC')
->orderBy('name', 'ASC')
```

### Пагинация

```php
->limit(20)
->offset(40)  // страница 3 по 20 записей
```

### Подсчёт

```php
$count = $this->select()
    ->where('status', 'active')
    ->count();
```

## Загрузка связей

### load() — отдельный запрос

```php
$users = $this->select()
    ->load('posts')
    ->load('posts.comments')
    ->fetchAll();
```

### load() с фильтрацией

```php
$users = $this->select()
    ->load('posts', [
        'where' => ['published' => true],
        'orderBy' => ['created_at' => 'DESC'],
    ])
    ->fetchAll();
```

### with() — JOIN для фильтрации

```php
// Фильтрация по связи (JOIN)
$users = $this->select()
    ->distinct()  // ОБЯЗАТЕЛЬНО для HasMany!
    ->with('posts')->where('posts.published', true)
    ->fetchAll();
```

### load + with — загрузка с фильтрацией

```php
$users = $this->select()
    ->distinct()
    ->with('posts', ['as' => 'published_posts'])
        ->where('posts.published', true)
    ->load('posts', ['using' => 'published_posts'])
    ->fetchAll();
```

### Автоматический JOIN

```php
// Cycle автоматически создаёт JOIN по точечной нотации
$posts = $this->select()
    ->where('author.status', 'active')
    ->where('author.email', 'like', '%@example.com')
    ->fetchAll();
```

## Методы извлечения данных

```php
// Все записи
$users = $select->fetchAll();

// Один результат
$user = $select->fetchOne();

// Итератор (для больших наборов)
foreach ($select as $user) { ... }
```

## Query Builder (Database Level)

Прямые запросы к БД без ORM:

```php
use Cycle\Database\DatabaseInterface;

public function __construct(
    private readonly DatabaseInterface $db,
) {}

// SELECT
$rows = $this->db->select()
    ->from('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->fetchAll();

// INSERT
$this->db->insert('users')
    ->values(['name' => 'John', 'email' => 'john@example.com'])
    ->run();

// UPDATE
$this->db->update('users')
    ->where('id', 1)
    ->values(['name' => 'Jane'])
    ->run();

// DELETE
$this->db->delete('users')
    ->where('id', 1)
    ->run();
```

## Цепочки методов в Repository

```php
class PostRepository extends Repository
{
    // Возвращает Select — можно дальше цепочить
    public function findPublished(): \Cycle\ORM\Select
    {
        return $this->select()
            ->where('published', true)
            ->where('deleted_at', null);
    }

    public function findByAuthor(string $authorId): \Cycle\ORM\Select
    {
        return $this->findPublished()
            ->where('author_id', $authorId)
            ->orderBy('created_at', 'DESC');
    }

    // Возвращает entity — конечный результат
    public function findLatestByAuthor(string $authorId): ?Post
    {
        return $this->findByAuthor($authorId)
            ->limit(1)
            ->fetchOne();
    }
}
```

> **ВАЖНО:** Не мутируй `Repository::$select`. Всегда используй `Repository::select()` — он возвращает КЛОН.

## Ключевые правила

1. Repository — read-only: НЕ содержит `save()`/`persist()`/`update()`/`delete()` и не инжектит `EntityManagerInterface`. Сохранение — через `$this->entityManager->persist()` + `run()` в Handler-е
2. Каждый Repository — конкретные доменные методы
3. `findByPK()`, `findOne()` — ТОЛЬКО внутри Repository
4. Из Handler вызывай `findByEmail()`, `findById()`, не generic-методы
5. Для условных фильтров — `Conditionable` + `SelectPipe` (проектный паттерн)
6. `distinct()` обязателен при фильтрации по HasMany/ManyToMany
7. `$orm->getRepository()` **ЗАПРЕЩЁН** — Repository через DI-инъекцию
8. Имя переменной: `$entityManager`, НЕ `$manager`
