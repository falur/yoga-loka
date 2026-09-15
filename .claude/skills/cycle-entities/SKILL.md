---
name: cycle-entities
description: >-
  Справочник по Entity (сущностям) в Cycle ORM: атрибуты #[Entity], #[Column],
  типы колонок, typecast, Entity Behaviors (UUID, timestamps, soft delete).
  Используй при создании или модификации Entity, ValueObject typecast, enum.
user-invocable: false
---

# Cycle ORM: Entities

## Атрибут #[Entity]

```php
use Cycle\Annotated\Annotation\Entity;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
class User
{
    // ...
}
```

### Параметры #[Entity]

| Параметр | Тип | Описание |
|----------|-----|---------|
| `role` | ?string | Идентификатор сущности (по умолчанию lowercase class name) |
| `mapper` | ?class-string | Кастомный mapper |
| `repository` | ?class-string | Кастомный репозиторий |
| `table` | ?string | Имя таблицы |
| `readonlySchema` | bool | Отключить синхронизацию схемы |
| `database` | ?string | Имя БД |
| `typecast` | string/array | Typecast handler(s) |
| `scope` | ?class-string | Query constraint class |

## Атрибут #[Column]

```php
#[Column(type: 'string', name: 'email_address', nullable: false)]
public private(set) string $email;
```

### Параметры #[Column]

| Параметр | Тип | Описание |
|----------|-----|---------|
| `type` | string | Тип колонки (ОБЯЗАТЕЛЕН) |
| `name` | ?string | Имя колонки в БД (если отличается от свойства) |
| `primary` | bool | Часть составного PK |
| `nullable` | bool | Разрешить NULL |
| `default` | mixed | Значение по умолчанию |
| `typecast` | callable/string | Правило typecast |

## Типы колонок

### Числовые
- `primary` — Auto-increment 32-bit PK (**ЗАПРЕЩЁН в проекте** — используй UUID v7)
- `bigPrimary` — Auto-increment 64-bit PK (**ЗАПРЕЩЁН в проекте** — используй UUID v7)
- `integer`, `bigInteger`, `tinyInteger`, `smallInteger`
- `float`, `double`
- `decimal(precision, scale)` — `decimal(10,2)`
- `boolean`

### Строковые
- `string` или `string(length)` — VARCHAR (по умолчанию 255)
- `text`, `tinyText`, `longText`

### Дата/Время
- `datetime` — дата и время
- `date` — только дата
- `time` — только время
- `timestamp` — Unix timestamp

### Бинарные и специальные
- `binary`, `tinyBinary`, `longBinary`
- `json`, `uuid`, `enum`

### Enum типы

```php
// PHP 8.1+ BackedEnum
enum UserStatus: string
{
    case Active = 'active';
    case Banned = 'banned';
}

#[Column(type: 'enum', values: UserStatus::class)]
public private(set) string $status;

// Inline enum
#[Column(type: 'enum(active,disabled,banned)', default: 'active')]
public private(set) string $status;
```

## Typecast

### Callable typecast

```php
final class Uuid
{
    private function __construct(private UuidInterface $uuid) {}

    public static function castValue(string $value, DatabaseInterface $db): static
    {
        return new static(UuidBody::fromString($value));
    }

    public function __toString(): string
    {
        return $this->uuid->toString();
    }
}

// В Entity:
#[Column(type: 'string', typecast: [Uuid::class, 'castValue'])]
private Uuid $uuid;
```

### Enum typecast (с v2.2.0)

```php
enum UserType: string
{
    case Guest = 'guest';
    case Admin = 'admin';
}

#[Column(type: 'string', typecast: UserType::class)]
private UserType $type;
```

### Магия typecast метода

Если у класса есть статический метод `typecast`, ORM использует его автоматически:
```php
enum UserType: string
{
    case Guest = 'guest';
    case Admin = 'admin';

    public static function typecast(string $value): self
    {
        return self::from($value);
    }
}
```

### ValueInterface для бинарного хранения

```php
use Cycle\Database\Injection\ValueInterface;

class Uuid implements ValueInterface
{
    public function rawValue(): string
    {
        return $this->uuid->getBytes();
    }

    public function rawType(): int
    {
        return \PDO::PARAM_LOB;
    }
}
```

## #[GeneratedValue]

```php
// БД генерирует при INSERT (auto-increment)
#[Column(type: 'primary')]
#[GeneratedValue(onInsert: true)]
private int $id;

// PHP генерирует перед INSERT
#[Column(type: 'uuid')]
#[GeneratedValue(beforeInsert: true)]
private string $id;

// PHP обновляет перед каждым UPDATE
#[Column(type: 'datetime')]
#[GeneratedValue(beforeInsert: true, beforeUpdate: true)]
private \DateTimeInterface $updatedAt;
```

## Индексы

```php
use Cycle\Annotated\Annotation\Table\Index;

#[Entity]
#[Index(columns: ['email'], unique: true)]
#[Index(columns: ['username'], unique: true)]
#[Index(columns: ['created_at'])]
#[Index(columns: ['user_id', 'created_at'], name: 'user_created_idx')]
class User {}
```

## Foreign Keys

```php
use Cycle\Annotated\Annotation\ForeignKey;

// На свойство
#[Column(type: 'integer')]
#[ForeignKey(target: User::class, action: 'CASCADE')]
private int $userId;

// На класс
#[Entity]
#[ForeignKey(target: User::class, innerKey: 'user_id', outerKey: 'id', action: 'CASCADE')]
class Post {}
```

### Действия FK: `CASCADE`, `NO ACTION`, `SET NULL`

## CRUD Operations

### Entity Manager

```php
use Cycle\ORM\EntityManagerInterface;

// EntityManager инжектируется через DI, НЕ создаётся вручную
public function __construct(
    private readonly EntityManagerInterface $entityManager,
) {}

// Создание — через статический create(), НЕ через конструктор + сеттеры
$user = User::create(
    email: Email::from($command->email),
    passwordHash: PasswordHash::fromPlain($command->password),
    name: $command->name,
    username: Username::from($command->username),
);
$this->entityManager->persist($user);
$this->entityManager->run();

// Обновление — через именованные методы Entity, НЕ сеттеры
$user->updateName($command->name);
$this->entityManager->persist($user);
$this->entityManager->run();

// Удаление
$this->entityManager->delete($user);
$this->entityManager->run();
```

> **ПРАВИЛА ПРОЕКТА:**
> - Конструктор Entity пустой (для Cycle ORM гидрации). Создание — через `create()`.
> - Мутация — через именованные методы (`updateName()`, `updateBio()`), НЕ сеттеры.
> - `$orm->getRepository()` запрещён — Repository через DI.
> - Имя переменной: `$entityManager`, НЕ `$manager`.

### Каскадное сохранение

По умолчанию `cascade: true` — связанные entity сохраняются автоматически:
```php
$this->entityManager->persist($user, cascade: false);  // Отключить каскад
```

### Обработка ошибок

try-catch допустим ТОЛЬКО в инфраструктурном коде (interceptor, middleware).
В бизнес-логике — бросать типизированные доменные исключения:
```php
// В Handler — НЕ ловить исключения, дать всплыть до ApiExceptionInterceptor
$user = $this->userRepository->findByEmail($email)
    ?? throw new NotFoundException('Пользователь не найден');
```

Подробнее о typecast: [references/TYPECAST.md](references/TYPECAST.md)
