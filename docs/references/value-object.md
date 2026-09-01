# ValueObject

## Назначение

Объект-значение хранит одно доменное понятие, нормализует вход и не позволяет создать неверное значение.

## Когда применять

Применяй для имени, адреса электронной почты, идентификатора, счётчика, текста и другого значения с собственными ограничениями или поведением.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserDisplayName implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 30;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $name = \trim($value);

        if ($name === '' || \mb_strlen($name) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Имя имеет неверную длину.');
        }

        return new self(value: $name);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $name): bool
    {
        return $this->value === $name->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
```

## Что повторять

- Класс `final readonly`, конструктор закрыт.
- Фабрика нормализует и проверяет вход до создания объекта.
- Значение читается явным методом; равенство сравнивает содержимое.
- Объект не знает про Cycle, HTTP, конфиг и контейнер.

## Допустимые варианты

Для закрытого набора вариантов используй enum. Для составного значения естественное строковое представление и `Stringable` не обязательны.
