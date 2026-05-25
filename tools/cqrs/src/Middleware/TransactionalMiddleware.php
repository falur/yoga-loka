<?php

declare(strict_types=1);

namespace Tools\Cqrs\Middleware;

use Cycle\Database\DatabaseInterface;
use Tools\Cqrs\Attribute\HandlerMiddlewareAttribute;
use Tools\Cqrs\Attribute\Transactional;
use Tools\Cqrs\CommandHandlerContext;
use Tools\Cqrs\HandlerContext;
use Tools\Cqrs\HandlerMiddlewareInterface;

final readonly class TransactionalMiddleware implements HandlerMiddlewareInterface
{
    public function __construct(
        private DatabaseInterface $database,
    ) {}

    /**
     * @template TInput of object
     * @template TResult
     * @param TInput $input
     * @param callable(TInput): TResult $next
     * @return TResult
     */
    #[\Override]
    public function handle(
        object $input,
        HandlerMiddlewareAttribute $attribute,
        callable $next,
        HandlerContext $context,
    ) {
        if (!$attribute instanceof Transactional) {
            throw new \LogicException(message: 'Некорректный атрибут для транзакционного middleware.');
        }

        if (!$context instanceof CommandHandlerContext) {
            throw new \LogicException(message: 'Атрибут #[Transactional] поддерживается только для Command Handler.');
        }

        return $this->database->transaction(
            static fn(DatabaseInterface $database) => $next($input),
        );
    }
}
