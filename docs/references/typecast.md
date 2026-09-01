# Typecast

## Назначение

Typecast преобразует техническое значение колонки при чтении и записи, когда встроенных средств Cycle недостаточно.

## Когда применять

Применяй для составного JSON, шифрования или другого формата хранения, который нельзя однозначно выразить типом колонки.

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Cycle\Typecast;

use Cycle\ORM\Parser\CastableInterface;
use Cycle\ORM\Parser\UncastableInterface;
use Spiral\Encrypter\EncrypterInterface;

final class EncryptedStringTypecast implements CastableInterface, UncastableInterface
{
    public const string RULE = 'encrypted';

    /** @var list<string> */
    private array $fields = [];

    public function __construct(
        private readonly EncrypterInterface $encrypter,
    ) {}

    /**
     * @param array<string, null|bool|int|float|string|\DateTimeInterface|object> $rules
     * @return array<string, null|bool|int|float|string|\DateTimeInterface|object>
     */
    #[\Override]
    public function setRules(array $rules): array
    {
        foreach ($rules as $field => $rule) {
            if ($rule === self::RULE) {
                $this->fields[] = $field;
                unset($rules[$field]);
            }
        }

        return $rules;
    }

    /**
     * @param array<string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<string, null|bool|int|float|string|\DateTimeInterface|object>
     */
    #[\Override]
    public function cast(array $data): array
    {
        foreach ($this->fields as $field) {
            $value = $data[$field] ?? null;

            if (!\is_string($value)) {
                throw new \UnexpectedValueException('Зашифрованное значение базы должно быть строкой.');
            }

            $data[$field] = $this->encrypter->decrypt($value);
        }

        return $data;
    }

    /**
     * @param array<string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<string, null|bool|int|float|string|\DateTimeInterface|object>
     */
    #[\Override]
    public function uncast(array $data): array
    {
        foreach ($this->fields as $field) {
            $value = $data[$field] ?? null;

            if (!\is_string($value)) {
                throw new \UnexpectedValueException('Значение для шифрования должно быть строкой.');
            }

            $data[$field] = $this->encrypter->encrypt($value);
        }

        return $data;
    }
}
```

## Что повторять

- Имя заканчивается на `Typecast` и называет преобразуемое значение.
- Typecast Cycle реализует `CastableInterface` и при обратном преобразовании `UncastableInterface`.
- Методы Cycle называются `setRules()`, `cast()` и `uncast()`.
- Оба направления преобразования находятся в одном классе.
- Неверное значение базы отклоняется явно.
- Имя правила хранится в константе, а обслуживаемые поля собираются в `setRules()`.

## Допустимые варианты

Typecast одного модуля находится рядом с его Cycle Entity. Общий технический Typecast может находиться в `Shared/Infrastructure/Persistence/Cycle/Typecast`. Domain ValueObject создаёт Mapper, а не Typecast.
