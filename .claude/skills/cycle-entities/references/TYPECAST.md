# Typecast — подробный справочник

## Концепция

Typecast преобразует значения при чтении из БД (гидрация) и записи в БД.
Позволяет хранить ValueObject нативно в Entity.

## Встроенные typecast

### Typecast::class (стандартный)

Автоматически кастит:
- `int` → PHP int
- `float` → PHP float
- `bool` → PHP bool
- `datetime` → DateTimeImmutable

### Enum typecast

Для `BackedEnum` достаточно указать enum-класс:

```php
#[Column(type: 'string', typecast: UserStatus::class)]
public private(set) UserStatus $status;
```

ORM проверяет наличие метода `typecast()` → если есть, использует его.
Если нет — использует `::from()` для BackedEnum.

## Кастомный Typecast Handler

Для ValueObject рекомендуется создать единый typecast handler:

```php
use Cycle\ORM\Parser\CastableInterface;
use Cycle\ORM\Parser\UncastableInterface;

class ValueObjectCast implements CastableInterface, UncastableInterface
{
    private array $rules = [];

    public function setRules(array $rules): array
    {
        foreach ($rules as $key => $rule) {
            if (is_string($rule) && is_subclass_of($rule, Castable::class)) {
                $this->rules[$key] = $rule;
                unset($rules[$key]);
            }
        }
        return $rules;
    }

    public function cast(array $data): array
    {
        foreach ($this->rules as $key => $class) {
            if (isset($data[$key])) {
                $data[$key] = $class::fromDatabase($data[$key]);
            }
        }
        return $data;
    }

    public function uncast(array $data): array
    {
        foreach ($this->rules as $key => $class) {
            if (isset($data[$key]) && $data[$key] instanceof \Stringable) {
                $data[$key] = (string) $data[$key];
            }
        }
        return $data;
    }
}
```

## ValueObject с typecast

```php
// Интерфейс Castable
interface Castable
{
    public static function fromDatabase(string $value): static;
}

// ValueObject
final readonly class Email implements \Stringable, \JsonSerializable, Castable
{
    private function __construct(
        private string $value,
    ) {}

    public static function from(string $value): self
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Невалидный email');
        }
        return new self($value);
    }

    // Гидрация из БД — БЕЗ валидации (данные уже валидны)
    public static function fromDatabase(string $value): static
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
```

В Entity:
```php
#[Entity(typecast: [Typecast::class, ValueObjectCast::class])]
class User
{
    #[Column(type: 'string', typecast: Email::class)]
    public private(set) Email $email;
}
```

## Множественные typecast handlers

```php
#[Entity(typecast: [Typecast::class, ValueObjectCast::class])]
```

Порядок: каждый handler обрабатывает свои правила. `Typecast` — для примитивов,
`ValueObjectCast` — для ValueObject.

## Правила

1. В Entity — НАТИВНЫЕ VO свойства: `public private(set) Email $email`
2. НЕТ промежуточных raw-полей (`private string $rawEmail`)
3. `typecast` в `#[Column]` указывает класс VO
4. VO реализует `fromDatabase()` для гидрации БЕЗ валидации
5. `__toString()` для записи в БД
