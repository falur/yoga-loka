---
name: spiral-console
description: >-
  Справочник по Console Commands в Spiral Framework.
  Используй при создании CLI-команд, определении аргументов/опций,
  работе с пользовательским вводом/выводом.
user-invocable: false
---

# Spiral Framework: Console Commands

## Создание команды

```bash
php app.php create:command My
```

## Структура с атрибутами (3.6+)

```php
namespace App\Endpoint\Console;

use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;

#[AsCommand(name: 'app:create:user', description: 'Создаёт пользователя')]
final class CreateUserCommand extends Command
{
    #[Argument]
    private string $email;

    #[Argument(description: 'Пароль пользователя')]
    private string $password;

    #[Argument(name: 'username', description: 'Имя пользователя')]
    private string $userName;

    #[Option(shortcut: 'a', name: 'admin', description: 'Сделать администратором')]
    private bool $isAdmin = false;

    public function __invoke(): int
    {
        $this->info(sprintf('Пользователь %s создан', $this->email));
        return self::SUCCESS;
    }
}
```

## Аргументы

```php
// Обязательный
#[Argument]
private string $email;

// С описанием
#[Argument(description: 'Пароль пользователя')]
private string $password;

// Кастомное имя
#[Argument(name: 'username')]
private string $userName;

// С дефолтом
#[Argument]
private string $role = 'user';

// Опциональный
#[Argument]
private ?string $note = null;
```

## Опции

```php
// Булевый флаг
#[Option(shortcut: 'a', name: 'admin')]
private bool $isAdmin = false;

// Обязательная опция
#[Option]
private string $status;

// Опциональная опция
#[Option]
private ?string $format = null;

// Enum
#[Option(description: 'Статус пользователя')]
private UserStatus $status = UserStatus::Active;

// Множественные значения (--role=foo --role=bar)
#[Option(name: 'role', description: 'Роли пользователя')]
private array $role = [];
```

## Вопросы пользователю

```php
use Spiral\Console\Attribute\Question;

#[Question(question: 'Введите email', argument: 'email')]
#[AsCommand(name: 'app:create:user')]
final class CreateUserCommand extends Command
{
    #[Argument]
    private string $email;

    public function __invoke(): int { ... }
}
```

## Вывод

```php
// Стилизованные сообщения
$this->info('Информация');
$this->comment('Комментарий');
$this->warning('Предупреждение');
$this->error('Ошибка');
$this->alert('Важно');
$this->newLine();

// Форматирование
$this->writeln('Текст с переносом');
$this->write('Без переноса');
$this->sprintf('Привет, <comment>%s</comment>', $name);
```

## Ввод пользователя

```php
$confirmed = $this->confirm('Уверены?', default: false);
$answer = $this->ask('Ваш ответ?', default: 'нет');
$password = $this->secret('Пароль');
$choice = $this->choiceQuestion(
    'Какой менеджер пакетов?',
    ['composer', 'npm', 'yarn'],
    default: 0,
);
```

## Таблицы

```php
$table = $this->table(['Колонка 1', 'Колонка 2']);
foreach ($data as $row) {
    $table->addRow([$row['name'], $row['value']]);
}
$table->render();
```

## DI в perform/invoke

```php
#[AsCommand(name: 'app:sync')]
final class SyncCommand extends Command
{
    // invoke или perform — с автоматическим DI
    public function __invoke(UserRepository $userRepository): int
    {
        $users = $userRepository->findAll();
        // ...
        return self::SUCCESS;
    }
}
```

## SIGNATURE (альтернативный синтаксис)

```php
class CheckHttpCommand extends Command
{
    protected const SIGNATURE = <<<CMD
        check:http
            {url : URL сайта}
            {--S|skip-ssl-errors : Пропустить SSL ошибки}
        CMD;

    public function perform(): int
    {
        $url = $this->argument('url');
        $skipErrors = $this->option('skip-ssl-errors');
        return self::SUCCESS;
    }
}
```

## Проверка production

```php
use Spiral\Console\Confirmation\ApplicationInProduction;

public function perform(ApplicationInProduction $confirmation): int
{
    if (!$confirmation->confirmToProceed()) {
        return self::FAILURE;
    }
    // Опасная операция...
    return self::SUCCESS;
}
```

## События

| Событие | Описание |
|---------|---------|
| `CommandStarting` | До выполнения команды |
| `CommandFinished` | После выполнения |

## Ключевые правила

1. Консольные команды — ТОНКИЕ обёртки
2. Команда собирает ввод и делегирует в CQRS Command+Handler
3. Бизнес-логика — в Handler, НЕ в команде
4. Вывод результата — в команде
