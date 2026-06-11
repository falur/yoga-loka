<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cycle;

use BackedEnum;
use Cycle\ORM\Parser\CastableInterface;
use Cycle\ORM\Parser\UncastableInterface;

final class ValueObjectCast implements CastableInterface, UncastableInterface
{
    /**
     * Правила привязаны к конкретной роли Entity, поэтому класс stateful. Cycle
     * создаёт по одному typecast-обработчику на роль через factory->make().
     * Не биндить как #[Singleton] / общий синглтон — иначе правила разных ролей смешаются.
     *
     * @var array<non-empty-string, class-string>
     */
    private array $rules = [];

    /**
     * @param array<non-empty-string, mixed> $rules
     * @return array<non-empty-string, mixed>
     */
    #[\Override]
    public function setRules(array $rules): array
    {
        foreach ($rules as $field => $rule) {
            if (!\is_string($rule) || !\class_exists($rule)) {
                continue;
            }

            if (!$this->supportsRule($rule)) {
                continue;
            }

            /** @var class-string $rule */
            $this->rules[$field] = $rule;
            unset($rules[$field]);
        }

        return $rules;
    }

    /**
     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<int|string, null|bool|int|float|string|\DateTimeInterface|object>
     */
    #[\Override]
    public function cast(array $data): array
    {
        foreach ($this->rules as $field => $rule) {
            if (!\array_key_exists(key: $field, array: $data)) {
                continue;
            }

            $data[$field] = $this->castField(rule: $rule, value: $data[$field]);
        }

        return $data;
    }

    /**
     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
     * @return array<int|string, bool|int|float|string|\DateTimeInterface|null>
     */
    #[\Override]
    public function uncast(array $data): array
    {
        foreach ($data as $field => $value) {
            $data[$field] = $this->uncastField(
                field: (string) $field,
                value: $value,
            );
        }

        return $data;
    }

    /**
     * @param class-string $rule
     */
    private function supportsRule(string $rule): bool
    {
        return (
            \is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)
            && \method_exists(object_or_class: $rule, method: 'castDatabaseValue')
            && \method_exists(object_or_class: $rule, method: 'uncastValue')
        )
            || \is_subclass_of(object_or_class: $rule, class: BackedEnum::class)
            || \method_exists(object_or_class: $rule, method: 'fromString')
            || \method_exists(object_or_class: $rule, method: 'fromInt');
    }

    /**
     * @param class-string $rule
     */
    private function castField(
        string $rule,
        bool|int|float|string|object|null $value,
    ): object|null {
        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
            return $this->invokeColumnCast(
                rule: $rule,
                value: $this->databaseValueOrFail($value),
            );
        }

        if ($value === null) {
            return null;
        }

        if (\is_subclass_of(object_or_class: $rule, class: BackedEnum::class)) {
            if (!\is_string($value) && !\is_int($value)) {
                throw new \InvalidArgumentException('Enum-значение базы должно быть строкой или числом.');
            }

            return $rule::from($value);
        }

        if (\method_exists(object_or_class: $rule, method: 'fromString')) {
            if (!\is_string($value)) {
                throw new \InvalidArgumentException('Строковый value object должен восстанавливаться из строки.');
            }

            return $this->invokeObjectFactory(rule: $rule, method: 'fromString', value: $value);
        }

        // Инвариант: supportsRule() пропускает ровно четыре вида правил
        // (ColumnValueTypecast, BackedEnum, fromString, fromInt); первые три отсечены выше,
        // поэтому единственное оставшееся правило здесь — фабрика fromInt, и финальный throw
        // означает «не-число для fromInt». Если в supportsRule() добавят новый вид правила,
        // эту хвостовую ветку нужно расширить синхронно, иначе сообщение про «числовой
        // value object» станет вводить в заблуждение.
        if (\is_string($value) && \preg_match(pattern: '/^-?\d+$/', subject: $value) === 1) {
            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: (int) $value);
        }

        if (\is_int($value)) {
            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: $value);
        }

        throw new \InvalidArgumentException('Числовой value object должен восстанавливаться из числа.');
    }

    private function uncastField(
        string $field,
        bool|int|float|string|object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null {
        $rule = $this->rules[$field] ?? null;

        if ($rule !== null) {
            return $this->uncastFieldByRule(rule: $rule, value: $value);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (\is_object($value)) {
            throw new \InvalidArgumentException('Объект не поддерживает запись в базу.');
        }

        return $value;
    }

    /**
     * @param class-string $rule
     */
    private function uncastFieldByRule(
        string $rule,
        bool|int|float|string|object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null {
        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
            if ($value !== null && !\is_object($value)) {
                return $value;
            }

            return $this->invokeColumnUncast(rule: $rule, value: $value);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (!\is_object($value)) {
            return $value;
        }

        if (!\method_exists(object_or_class: $value, method: 'value')) {
            throw new \InvalidArgumentException('Value object должен иметь метод value().');
        }

        return $this->valueObjectDatabaseValue($value);
    }

    /**
     * @param class-string $rule
     */
    private function invokeObjectFactory(string $rule, string $method, string|int $value): object
    {
        $createdValue = (new \ReflectionMethod(objectOrMethod: $rule, method: $method))->invoke(null, $value);

        if (!\is_object($createdValue)) {
            throw new \InvalidArgumentException('Фабрика value object должна вернуть объект.');
        }

        return $createdValue;
    }

    /**
     * @param class-string $rule
     */
    private function invokeColumnCast(
        string $rule,
        bool|int|float|string|\DateTimeInterface|null $value,
    ): object {
        $castValue = (new \ReflectionMethod(objectOrMethod: $rule, method: 'castDatabaseValue'))->invoke(null, $value);

        if (!\is_object($castValue)) {
            throw new \InvalidArgumentException('Typecast базы должен вернуть объект.');
        }

        return $castValue;
    }

    /**
     * @param class-string $rule
     */
    private function invokeColumnUncast(
        string $rule,
        object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null {
        $databaseValue = (new \ReflectionMethod(objectOrMethod: $rule, method: 'uncastValue'))->invoke(null, $value);

        if ($databaseValue === null) {
            return null;
        }

        if (\is_bool($databaseValue) || \is_int($databaseValue) || \is_float($databaseValue) || \is_string($databaseValue)) {
            return $databaseValue;
        }

        if ($databaseValue instanceof \DateTimeInterface) {
            return $databaseValue;
        }

        throw new \InvalidArgumentException('Typecast базы вернул неподдерживаемый тип.');
    }

    private function valueObjectDatabaseValue(object $value): bool|int|float|string|\DateTimeInterface|null
    {
        $databaseValue = (new \ReflectionMethod(objectOrMethod: $value, method: 'value'))->invoke($value);

        if ($databaseValue === null) {
            return null;
        }

        if (\is_bool($databaseValue) || \is_int($databaseValue) || \is_float($databaseValue) || \is_string($databaseValue)) {
            return $databaseValue;
        }

        if ($databaseValue instanceof \DateTimeInterface) {
            return $databaseValue;
        }

        throw new \InvalidArgumentException('Value object вернул неподдерживаемый тип.');
    }

    private function databaseValueOrFail(
        bool|int|float|string|object|null $value,
    ): bool|int|float|string|\DateTimeInterface|null {
        if ($value === null) {
            return null;
        }

        if (\is_bool($value) || \is_int($value) || \is_float($value) || \is_string($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        throw new \InvalidArgumentException('Значение базы имеет неподдерживаемый тип.');
    }
}
