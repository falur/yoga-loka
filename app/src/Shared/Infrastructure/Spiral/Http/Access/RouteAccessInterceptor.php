<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Http\Access;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Router\Annotation\Route;

/**
 * Применяет объявления доступа маршрута до контроллера и до сборки Filter.
 *
 * Интерсептор читает атрибуты целевого метода и для каждого зарегистрированного объявления
 * вызывает его правило. Отказ правило выражает доменным исключением, которое превращает в ответ
 * общий обработчик ошибок API, поэтому интерсептор стоит в цепочке внутри него.
 *
 * Маршрут без единого объявления доступа до контроллера не доходит: забытая декларация — ошибка
 * разработчика, а не признак публичного маршрута.
 *
 * Тип возвращаемого значения `mixed` задан интерфейсом Spiral и сужению здесь не подлежит.
 */
final readonly class RouteAccessInterceptor implements InterceptorInterface
{
    public function __construct(
        private AccessRuleRegistry $accessRuleRegistry,
    ) {}

    #[\Override]
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $reflection = $context->getTarget()->getReflection();

        if ($reflection instanceof \ReflectionMethod) {
            $this->applyDeclarations(reflection: $reflection, context: $context);
        }

        return $handler->handle($context);
    }

    /**
     * Проверяет объявления доступа метода маршрута. Метод без `#[Route]` интерсептор не касается:
     * доменное ядро обслуживает не только HTTP-границу.
     */
    private function applyDeclarations(\ReflectionMethod $reflection, CallContextInterface $context): void
    {
        if ($reflection->getAttributes(Route::class) === []) {
            return;
        }

        $request = null;
        $declarations = 0;

        foreach ($reflection->getAttributes() as $attribute) {
            $rule = $this->accessRuleRegistry->ruleFor(declarationClass: $attribute->getName());

            if ($rule === null) {
                continue;
            }

            ++$declarations;
            $request ??= $this->request(context: $context);
            $rule->check(declaration: $attribute->newInstance(), request: $request);
        }

        if ($declarations === 0) {
            throw new RouteAccessNotDeclaredException(\sprintf(
                'Маршрут %s::%s() не объявил требование доступа.',
                $reflection->getDeclaringClass()->getName(),
                $reflection->getName(),
            ));
        }
    }

    private function request(CallContextInterface $context): ServerRequestInterface
    {
        $request = $context->getAttribute(ServerRequestInterface::class);

        if (!$request instanceof ServerRequestInterface) {
            throw new RouteRequestUnavailableException(
                'Требование доступа маршрута проверяется без HTTP-запроса в контексте вызова.',
            );
        }

        return $request;
    }
}
