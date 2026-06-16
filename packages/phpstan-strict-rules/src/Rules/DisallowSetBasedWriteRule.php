<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Запрещает set-based запись (insert/update/delete на Cycle DatabaseInterface) во всех классах,
 * кроме реализующих интерфейс-маркер. Обычное изменение состояния идёт через Entity +
 * EntityManager. Так массовая запись остаётся узкой осознанной дверью, а не привычкой.
 *
 * FQCN маркера передаётся из конфига (extension.neon), чтобы правило не зависело от конкретного
 * приложения. Cycle-интерфейс указан строкой FQCN, а не через use, — пакет изолирован.
 *
 * @implements Rule<MethodCall>
 */
final class DisallowSetBasedWriteRule implements Rule
{
    private const string DATABASE_INTERFACE = 'Cycle\\Database\\DatabaseInterface';
    private const array WRITE_METHODS = ['insert', 'update', 'delete'];
    private const string IDENTIFIER = 'gianTiaga.phpstanStrictRules.setBasedWriteForbidden';

    public function __construct(
        private readonly string $markerInterface,
    ) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $writeMethod = $node->name->toLowerString();
        if (!\in_array(needle: $writeMethod, haystack: self::WRITE_METHODS, strict: true)) {
            return [];
        }

        // Пишет именно Cycle DatabaseInterface? EntityManager::delete($entity) сюда не попадёт —
        // у него другой тип получателя, значит санкционированное удаление сущности не ломается.
        if (!(new ObjectType(self::DATABASE_INTERFACE))->isSuperTypeOf($scope->getType($node->var))->yes()) {
            return [];
        }

        $callerClass = $scope->getClassReflection();
        if ($callerClass !== null && $callerClass->implementsInterface($this->markerInterface)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Set-based запись %s() на DatabaseInterface разрешена только в классах, реализующих '
                . 'SetBasedWrite. Обычное изменение состояния — через Entity + EntityManager.',
                $writeMethod,
            ))
                ->identifier(self::IDENTIFIER)
                ->line($node->getStartLine())
                ->build(),
        ];
    }
}
