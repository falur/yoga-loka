---
name: cycle-relations
description: >-
  Справочник по Relations (связям) в Cycle ORM: BelongsTo, HasOne, HasMany,
  ManyToMany, RefersTo, Embedded. Используй при определении связей между Entity,
  загрузке связанных данных, фильтрации по связям.
user-invocable: false
---

# Cycle ORM: Relations

## BelongsTo

Ребёнок хранит FK на родителя. Ребёнок сохраняется ПОСЛЕ родителя.

```php
use Cycle\Annotated\Annotation\Relation\BelongsTo;

#[Entity]
class Post
{
    #[BelongsTo(target: User::class)]
    private User $author;
    // Создаёт колонку: author_id → users.id
}
```

### Параметры BelongsTo

| Параметр | Тип | Default | Описание |
|----------|-----|---------|---------|
| `target` | string | — | **Обязателен.** Целевая entity |
| `load` | string | 'lazy' | 'lazy' или 'eager' |
| `cascade` | bool | true | Автосохранение родителя |
| `nullable` | bool | false | Можно ли без родителя |
| `innerKey` | string/array | null | FK колонка (default: `{relation}_{parentPK}`) |
| `outerKey` | string/array | null | PK родителя |
| `fkCreate` | bool | true | Создавать FK constraint |
| `fkAction` | string | 'CASCADE' | Действие FK |
| `fkOnDelete` | string | null | Отдельное действие DELETE |
| `indexCreate` | bool | true | Создавать индекс на FK |

### Nullable BelongsTo

```php
#[BelongsTo(target: User::class, nullable: true)]
private ?User $author = null;
```

### Кастомный FK

```php
#[BelongsTo(target: User::class, innerKey: 'user_id')]
private User $author;
```

## HasOne

Один-к-одному. Ребёнок хранит FK.

```php
use Cycle\Annotated\Annotation\Relation\HasOne;

#[Entity]
class User
{
    #[HasOne(target: Profile::class)]
    private ?Profile $profile = null;
}
```

Параметры аналогичны BelongsTo.

## HasMany

Один-ко-многим. Родитель сохраняется первым.

```php
use Cycle\Annotated\Annotation\Relation\HasMany;

#[Entity]
class User
{
    #[HasMany(target: Post::class)]
    private array $posts = [];

    public function addPost(Post $post): void
    {
        $this->posts[] = $post;
    }

    public function removePost(Post $post): void
    {
        $this->posts = array_filter($this->posts, fn(Post $p) => $p !== $post);
    }
}
```

### Параметры HasMany

| Параметр | Тип | Default | Описание |
|----------|-----|---------|---------|
| `target` | string | — | **Обязателен** |
| `load` | string | 'lazy' | 'lazy' или 'eager' |
| `cascade` | bool | true | Автосохранение детей |
| `nullable` | bool | false | Дети без родителя (SET NULL при удалении) |
| `where` | array | [] | WHERE по умолчанию |
| `orderBy` | array | [] | Сортировка по умолчанию |
| `fkAction` | string | 'CASCADE' | Действие FK |
| `collection` | string | null | Класс коллекции |

### WHERE и ORDER BY по умолчанию

```php
#[HasMany(
    target: Post::class,
    where: ['published' => true, 'deleted_at' => null],
    orderBy: ['published_at' => 'DESC', 'title' => 'ASC'],
)]
private array $publishedPosts = [];
```

### Несколько HasMany на разные подмножества

```php
#[HasMany(target: Post::class, where: ['published' => true])]
private array $publishedPosts = [];

#[HasMany(target: Post::class, where: ['published' => false])]
private array $draftPosts = [];
```

## ManyToMany

Через pivot entity.

```php
use Cycle\Annotated\Annotation\Relation\ManyToMany;

#[Entity]
class Post
{
    #[ManyToMany(target: Hashtag::class, through: PostHashtag::class)]
    private array $hashtags = [];
}

#[Entity]
class PostHashtag
{
    #[Column(type: 'uuid')]
    public private(set) string $id;
}
```

> **ПРАВИЛО ПРОЕКТА:** `primary`/`bigPrimary` (auto-increment) запрещены — используй UUID v7.

### Параметры ManyToMany

| Параметр | Тип | Default | Описание |
|----------|-----|---------|---------|
| `target` | string | — | **Обязателен** |
| `through` | string | — | **Обязателен.** Pivot entity |
| `throughInnerKey` | string/array | null | FK в pivot на source |
| `throughOuterKey` | string/array | null | FK в pivot на target |
| `where` | array | [] | WHERE для target |
| `throughWhere` | array | [] | WHERE для pivot |
| `orderBy` | array | [] | Сортировка |
| `collection` | string | null | Класс коллекции |

### Pivot с дополнительными данными

```php
#[Entity]
class PostHashtag
{
    #[Column(type: 'uuid')]
    public private(set) string $id;

    #[Column(type: 'datetime')]
    public private(set) \DateTimeInterface $createdAt;
}
```

### Доступ к pivot данным

```php
use Cycle\ORM\Collection\Pivoted\PivotedCollection;

#[ManyToMany(target: Tag::class, through: UserTag::class, collection: 'illuminate')]
private PivotedCollection $tags;

// Чтение pivot:
$pivot = $user->tags->getPivot($tag);

// Установка pivot:
$pivot = new UserTag();
$user->tags->add($tag);
$user->tags->setPivot($tag, $pivot);
```

### Фильтрация по pivot данным (синтаксис @)

```php
// Внутри Repository:
$users = $this->select()
    ->distinct()
    ->where('tags.@.created_at', '>', new DateTime('-7 days'))
    ->fetchAll();
```

## Inverse Relations

```php
use Cycle\Annotated\Annotation\Relation\Inverse;

#[HasMany(
    target: Post::class,
    inverse: new Inverse(as: 'author', type: 'belongsTo'),
)]
private array $posts = [];
```

## Загрузка связей

### Явная загрузка (load)

```php
$user = $this->select()
    ->load('posts')               // отдельный запрос
    ->load('posts.comments')      // вложенные связи
    ->wherePK(1)
    ->fetchOne();
```

### Eager loading

```php
#[HasMany(target: Post::class, load: 'eager')]
private array $posts = [];
```

### Фильтрованная загрузка

```php
$users = $this->select()
    ->load('posts', ['where' => ['published' => true]])
    ->fetchAll();
```

### Сортировка при загрузке

```php
$users = $this->select()
    ->load('posts', [
        'where' => ['published' => true],
        'orderBy' => ['created_at' => 'DESC'],
    ])
    ->fetchAll();
```

## Фильтрация по связям (with)

```php
// Фильтр по связи
$posts = $this->select()
    ->with('author')->where('author.status', 'active')
    ->fetchAll();

// Автоматический JOIN
$posts = $this->select()
    ->where('author.status', 'active')
    ->fetchAll();

// HasMany — ОБЯЗАТЕЛЬНО distinct()
$users = $this->select()
    ->distinct()
    ->with('posts')->where('posts.published', true)
    ->fetchAll();
```

> **ВАЖНО:** Всегда используй `distinct()` при фильтрации по HasMany/ManyToMany!

## Коллекции

```php
// Array (по умолчанию)
#[HasMany(target: Post::class)]
private array $posts = [];

// Illuminate Collection (рекомендуемая)
#[HasMany(target: Post::class, collection: 'illuminate')]
private \Illuminate\Support\Collection $posts;

// Doctrine Collection (НЕ используется в проекте — только Illuminate)
// #[HasMany(target: Post::class, collection: 'doctrine')]
// private \Doctrine\Common\Collections\Collection $posts;
```

## Foreign Key Actions

| Действие | Описание |
|----------|---------|
| `CASCADE` | Удалить/обновить детей при изменении родителя |
| `SET NULL` | Установить FK в NULL (требует `nullable: true`) |
| `NO ACTION` | Запретить удаление/обновление при наличии детей |

```php
#[HasMany(target: Post::class, fkAction: 'CASCADE')]
#[HasMany(target: Post::class, fkAction: 'SET NULL', nullable: true)]
#[HasMany(target: Post::class, fkAction: 'CASCADE', fkOnDelete: 'SET NULL', nullable: true)]
```

### Отключение FK

```php
#[HasMany(target: Post::class, fkCreate: false, indexCreate: false)]
```

## Правила проекта

> **ПРАВИЛА:**
> - `$orm->getRepository()` запрещён — Repository через DI
> - `collection: 'doctrine'` запрещён — используй `collection: 'illuminate'`
> - `primary`/`bigPrimary` запрещены — используй UUID v7
> - Свойства: `public private(set)` с property hooks (PHP 8.4+)
> - Конструктор Entity пустой — создание через `create()`
