<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs\Middleware;

use Cycle\Database\DatabaseInterface;
use GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use GianTiaga\SpiralCqrs\CommandHandlerContext;
use GianTiaga\SpiralCqrs\HandlerContext;
use GianTiaga\SpiralCqrs\HandlerMiddlewareInterface;

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
            throw new \LogicException(message: 'Некорректный атрибут для транзакционного промежуточного обработчика.');
        }

        if (!$context instanceof CommandHandlerContext) {
            throw new \LogicException(message: 'Атрибут #[Transactional] поддерживается только для обработчика команды.');
        }

        return $this->database->transaction(
            static fn(DatabaseInterface $database) => $next($input),
        );
    }
}
