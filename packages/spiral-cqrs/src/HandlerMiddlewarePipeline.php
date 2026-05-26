<?php

declare(strict_types=1);

namespace GianTiaga\SpiralCqrs;

use Psr\Container\ContainerInterface;
use GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute;

/**
 * @internal
 */
final readonly class HandlerMiddlewarePipeline
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /**
     * @template TInput of object
     * @template TResult
     * @param TInput $input
     * @param callable(TInput): TResult $handler
     * @return TResult
     */
    public function execute(object $input, callable $handler, HandlerContext $context)
    {
        return $this->dispatchThrough(
            input: $input,
            handler: $handler,
            attributes: $this->attributes(handlerReflection: $context->handlerReflection),
            attributeIndex: 0,
            context: $context,
        );
    }

    /**
     * @template TInput of object
     * @template TResult
     * @param TInput $input
     * @param callable(TInput): TResult $handler
     * @param list<HandlerMiddlewareAttribute> $attributes
     * @return TResult
     */
    private function dispatchThrough(
        object $input,
        callable $handler,
        array $attributes,
        int $attributeIndex,
        HandlerContext $context,
    ) {
        if (!isset($attributes[$attributeIndex])) {
            return $handler($input);
        }

        $attribute = $attributes[$attributeIndex];
        $middleware = $this->middleware(attribute: $attribute);

        return $middleware->handle(
            input: $input,
            attribute: $attribute,
            next: fn(object $nextInput) => $this->dispatchThrough(
                input: $nextInput,
                handler: $handler,
                attributes: $attributes,
                attributeIndex: $attributeIndex + 1,
                context: $context,
            ),
            context: $context,
        );
    }

    /**
     * @return list<HandlerMiddlewareAttribute>
     */
    private function attributes(\ReflectionFunction $handlerReflection): array
    {
        $attributes = [];

        foreach ($handlerReflection->getAttributes(
            name: HandlerMiddlewareAttribute::class,
            flags: \ReflectionAttribute::IS_INSTANCEOF,
        ) as $reflectionAttribute) {
            $attributes[] = $reflectionAttribute->newInstance();
        }

        return $attributes;
    }

    private function middleware(HandlerMiddlewareAttribute $attribute): HandlerMiddlewareInterface
    {
        $middleware = $this->container->get(id: $attribute->middleware());

        if (!$middleware instanceof HandlerMiddlewareInterface) {
            throw new \LogicException(message: 'Промежуточный обработчик должен реализовывать HandlerMiddlewareInterface.');
        }

        return $middleware;
    }
}
