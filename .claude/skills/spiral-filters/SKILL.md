---
name: spiral-filters
description: >-
  Справочник по Filter-объектам (валидация запросов) в Spiral Framework.
  Используй при создании или модификации фильтров для входящих HTTP-запросов,
  валидации данных, работе с атрибутами #[Post], #[Query], #[Attribute] и т.д.
user-invocable: false
---

# Spiral Framework: Filters (валидация запросов)

Filter — класс, представляющий набор полей запроса с фильтрацией и валидацией.
Реализует `Spiral\Filters\Model\FilterInterface`. Injectable — автоматически создаётся и маппит данные.

## Атрибуты источников данных

### Основные источники

| Атрибут | Источник | Пример |
|---------|---------|--------|
| `#[Post]` | Тело запроса (POST) | `#[Post] public string $name;` |
| `#[Query]` | Query параметры | `#[Query] public int $page = 1;` |
| `#[Input]` | POST + Query | `#[Input] public string $search;` |
| `#[Data]` | Данные запроса (application/json body) | `#[Data] public string $title;` |
| `#[File]` | Загруженные файлы | `#[File] public UploadedFileInterface $avatar;` |
| `#[Route]` | Параметры маршрута | `#[Route] public string $id;` |
| `#[Attribute]` | Request attributes (из middleware) | `#[Attribute(key: 'userId')] public string $userId;` |
| `#[Header]` | HTTP-заголовки | `#[Header] public string $contentType;` |
| `#[Cookie]` | Cookies | `#[Cookie] public string $theme;` |
| `#[BearerToken]` | Bearer токен из Authorization | `#[BearerToken] public string $token;` |

### Информационные

| Атрибут | Описание |
|---------|---------|
| `#[IsAjax]` | true если AJAX-запрос |
| `#[IsSecure]` | true если HTTPS |
| `#[IsJsonExpected]` | true если ожидается JSON |
| `#[Method]` | HTTP-метод запроса |
| `#[Path]` | Путь запроса |
| `#[Uri]` | Полный URI |
| `#[RemoteAddress]` | IP клиента |
| `#[Server]` | Server параметры |

## Параметр key

Если имя свойства совпадает с ключом в JSON — `key` НЕ НУЖЕН:
```php
// camelCase свойство = camelCase ключ в JSON → key не нужен
#[Post]
public string $firstName;  // читает "firstName" из JSON

// key нужен ТОЛЬКО когда имя отличается
#[Post(key: 'first_name')]
public string $firstName;  // читает "first_name" из JSON
```

## Dot Notation

Доступ к вложенным данным:
```php
#[Post(key: 'address.city')]
public string $city;

#[Post(key: 'contacts.0.email')]
public string $primaryEmail;
```

## Валидация

### Symfony Validator (рекомендуемый)

```php
use Symfony\Component\Validator\Constraints as Assert;

class RegisterFilter extends \Spiral\Filters\Model\Filter
{
    #[Post]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 128)]
    public string $password;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $name;

    #[Post]
    public ?string $spiritualName = null;
}
```

### Правила типизации свойств

**Обязательное свойство — без значения по умолчанию:**
```php
#[Post]
#[Assert\NotBlank]
public string $email;  // НЕ public string $email = '';
```

**Опциональное свойство — nullable или с дефолтом:**
```php
#[Query]
public int $limit = 20;

#[Post]
public ?string $bio = null;
```

**Файл:**
```php
#[File]
public UploadedFileInterface $avatar;  // НЕ nullable для обязательного
```

**Enum в Filter — автокаст:**
```php
#[Post]
public UserStatus $status;  // Spiral автоматически кастит строку в BackedEnum
```

## Санитизация

```php
use Spiral\Filters\Attribute\Setter;

#[Post]
#[Setter(filter: 'trim')]
public string $name;

#[Post]
#[Setter(filter: 'intval')]
public int $age;

// Несколько фильтров
#[Post]
#[Setter(filter: 'trim')]
#[Setter(filter: 'strtolower')]
public string $email;
```

## Вложенные Filter-ы

Для вложенных JSON-объектов — создавать отдельный Filter:
```php
use Spiral\Filters\Attribute\NestedFilter;

class CreateOrderFilter extends Filter
{
    #[Post]
    #[Assert\NotBlank]
    public string $comment;

    #[NestedFilter(class: AddressFilter::class, prefix: 'address')]
    public AddressFilter $address;
}

class AddressFilter extends Filter
{
    #[Post]
    #[Assert\NotBlank]
    public string $city;

    #[Post]
    #[Assert\NotBlank]
    public string $street;
}
```

## Request Attributes (для auth)

Middleware устанавливает attribute, Filter читает его:

```php
// Middleware:
$request->withAttribute('userId', $decodedUserId);

// Filter:
class UpdateUserProfileFilter extends Filter
{
    #[Attribute(key: 'userId')]
    #[Assert\NotBlank]
    public string $userId;

    #[Post]
    #[Assert\NotBlank]
    public string $name;
}
```

## Обработка ошибок

При невалидных данных выбрасывается `ValidationException`.
`ValidationHandlerMiddleware` перехватывает и рендерит через `ErrorsRendererInterface`.

## Кастомные атрибуты

Наследуй `AbstractInput` для создания своих:
```php
use Spiral\Filters\Attribute\Input\AbstractInput;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class CurrentLocale extends AbstractInput
{
    public function getValue(InputInterface $input, \ReflectionProperty $property): mixed
    {
        return $input->getValue('attribute', 'locale') ?? 'ru';
    }

    public function getSchema(\ReflectionProperty $property): string
    {
        return 'attribute:locale';
    }
}
```

## Ключевые правила

1. `?array` в Filter-е — ЗАПРЕЩЁН. Для вложенных объектов → NestedFilter
2. `key` указывать ТОЛЬКО когда имя в JSON отличается от имени свойства
3. Обязательные свойства — без значения по умолчанию
4. Filter-ы в `Endpoint/Api/Filter/{Domain}/`, НЕ в `App\Filter`
5. Контроллер НЕ делает `Enum::from()` вручную — Filter кастит автоматически
6. `ServerRequestInterface` в контроллерах ЗАПРЕЩЁН — используй Filter
