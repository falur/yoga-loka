<?php

declare(strict_types=1);

namespace Tools\Cqrs\Middleware;

use Psr\Log\LoggerInterface;
use Tools\Cqrs\Attribute\HandlerMiddlewareAttribute;
use Tools\Cqrs\Attribute\LogOperation;
use Tools\Cqrs\HandlerContext;
use Tools\Cqrs\HandlerMiddlewareInterface;

final readonly class LogOperationMiddleware implements HandlerMiddlewareInterface
{
    public function __construct(
        private LoggerInterface $logger,
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
        if (!$attribute instanceof LogOperation) {
            throw new \LogicException(message: 'Некорректный атрибут для middleware логирования операции.');
        }

        $operationName = $context->operationName(name: $attribute->name);
        $this->logger->debug(\sprintf('[Bus] %s: начало', $operationName));

        $startedAt = \microtime(true);

        try {
            return $next($input);
        } finally {
            $elapsedMilliseconds = \round(num: (\microtime(true) - $startedAt) * 1000, precision: 2);
            $this->logger->debug(\sprintf('[Bus] %s: %sms', $operationName, $elapsedMilliseconds));
        }
    }
}
