# Interceptors — подробный справочник

## Создание Interceptor

```php
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\CoreInterface;

class LoggingInterceptor implements CoreInterceptorInterface
{
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $start = microtime(true);
        $result = $handler->handle($context);
        $duration = microtime(true) - $start;

        // логирование...

        return $result;
    }
}
```

## Модификация аргументов

```php
class ParameterTypeCastInterceptor implements InterceptorInterface
{
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $arguments = $context->getArguments();

        foreach ($arguments as $key => $value) {
            if (is_string($value) && ctype_digit($value)) {
                $arguments[$key] = (int) $value;
            }
        }

        return $handler->handle($context->withArguments($arguments));
    }
}
```

## UUID конвертация

```php
final class UuidParameterConverterInterceptor implements InterceptorInterface
{
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $arguments = $context->getArguments();
        $reflection = $context->getTarget()->getReflection();

        if ($reflection === null) {
            return $handler->handle($context);
        }

        foreach ($reflection->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            if (
                isset($arguments[$paramName])
                && $paramType !== null
                && $paramType->getName() === UuidInterface::class
            ) {
                $arguments[$paramName] = Uuid::fromString($arguments[$paramName]);
            }
        }

        return $handler->handle($context->withArguments($arguments));
    }
}
```

## CycleInterceptor — автоматическое разрешение Entity

Загружает Entity из БД по route-параметру:

```php
// Регистрация
protected const INTERCEPTORS = [CycleInterceptor::class];

// Контроллер — Entity подставляется автоматически
#[Route(route: '/users/<user>')]
public function show(User $user): DataResponse
{
    // $user уже загружен из БД
}
```

## GuardInterceptor (RBAC)

```php
#[GuardNamespace(namespace: 'admin')]
class AdminController
{
    #[Guarded]
    public function index(): string
    {
        return 'Доступ разрешён';
    }

    #[Guarded(permission: 'admin.users', else: 'forbidden')]
    public function users(): string
    {
        return 'Список пользователей';
    }
}
```

## Pipeline — кастомные interceptor-ы на endpoint

```php
use Spiral\Domain\Annotation\Pipeline;

#[Pipeline(pipeline: [CycleInterceptor::class, GuardInterceptor::class], skipNext: true)]
public function sensitiveAction(): string
{
    // Только CycleInterceptor и GuardInterceptor, остальные пропущены
}
```

## Route-specific Interceptors

```php
$pipeline = (new PipelineBuilder())
    ->withInterceptors(new CustomInterceptor())
    ->build(new CallableHandler());

$router->setRoute('home', new Route(
    '/home/<action>',
    (new Controller(HomeController::class))->withHandler($pipeline),
));
```
