---
date: 2026-06-15 20:46
source: text (запрос пользователя: перенести view-шаблоны в модули и зафиксировать правилом)
status: done
---

# Фикс: View-шаблоны (twig) переносятся в свои модули

## Контекст

Оба twig-шаблона проекта лежали в общей папке `app/views/` (`login-code.twig` и
`swagger/index.twig`), хотя относятся к конкретным модулям. Решение (принято в обсуждении с
пользователем) — перевести проект на конвенцию «модульные view-шаблоны лежат в своём модуле» и
сделать это целиком (и Auth, и System), а также зафиксировать рамку правилом.

Учтены `docs/rules.md`, `docs/arch.md`, `docs/code-examples.md` и стиль bootloader-ов проекта
(`spiral-bootloaders`). Проверено по исходникам Spiral: `ViewsBootloader::addDirectory(namespace,
directory)` регистрирует namespace; ссылка на шаблон — `namespace:view` (или `@namespace/view`).
SendIt рендерит письмо как `views->get($message->getSubject())`, поэтому namespace-имя работает и
для письма. Конфиги (`app/config/*.php`) и миграции (`app/database/migrations`) намеренно
оставлены глобальными — по уже принятому в `arch.md` решению и из-за единой линейной истории
схемы.

Граница задачи: точечный перенос двух шаблонов + регистрация namespace модулями + правило.
Логика письма и swagger не менялась.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Modules/Auth/Presentation/views/login-code.twig` | Перенесён из `app/views/login-code.twig` (содержимое без изменений) | Шаблон письма — в своём модуле |
| 2 | `app/src/Modules/System/Presentation/views/swagger/index.twig` | Перенесён из `app/views/swagger/index.twig` через `git mv` (содержимое без изменений) | Шаблон Swagger UI — в своём модуле |
| 3 | `app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php` | Регистрация namespace `auth` через `ViewsBootloader::addDirectory` в `init()`; `ViewsBootloader` добавлен в `defineDependencies()`; константа `VIEW_NAMESPACE` | Модуль сам регистрирует свою папку шаблонов |
| 4 | `app/src/Modules/Auth/Infrastructure/Mail/SpiralLoginCodeMailer.php` | `EMAIL_VIEW`: `'login-code'` → `'auth:login-code'` + комментарий | Ссылка на шаблон через namespace модуля |
| 5 | `app/src/Modules/System/Infrastructure/Bootloader/SystemBootloader.php` | Новый bootloader: регистрирует namespace `system` для view; константа `VIEW_NAMESPACE` | У System не было Infrastructure-слоя; единообразие с другими модулями |
| 6 | `app/src/Shared/Infrastructure/Framework/Kernel.php` | `SystemBootloader::class` добавлен в `defineBootloaders()` (рядом с модульными) + импорт | Подключить новый bootloader |
| 7 | `app/src/Modules/System/Presentation/Http/View/SwaggerView.php` | `render(path: 'swagger/index')` → `render(path: 'system:swagger/index')` | Ссылка на шаблон через namespace модуля |
| 8 | `app/views/.gitkeep` | Папка `app/views` оставлена пустой через `.gitkeep` | Default namespace Spiral указывает на `app/views`; избегаем краевого случая отсутствующей директории в `views:compile`/`list()`. Модульных шаблонов там нет |
| 9 | `docs/rules.md` | Новое правило «View-шаблоны живут в модуле» (рядом с «Filter-ы живут в модуле») | Зафиксировать конвенцию и контраст с конфигами/миграциями |
| 10 | `docs/arch.md` | В структуре модуля System добавлены `Infrastructure/Bootloader` и `Presentation/views`; строка `Views ->` в «Границы и контроль качества» + абзац «что колокейтим, а что глобально» | Отразить новую конвенцию в архитектуре |
| 11 | `tests/Feature/Modules/Auth/Infrastructure/SpiralLoginCodeMailerTest.php` | Утверждение subject `'login-code'` → `'auth:login-code'`; добавлен тест `testLoginCodeViewRendersFromAuthNamespace`, рендерящий `auth:login-code` через реальный `ViewsInterface` | Зафиксировать новое имя view и доказать, что namespace регистрируется и шаблон на новом месте рендерится |

Технические детали реализации:
- Путь к папке шаблонов модуля в bootloader-е строится от `__DIR__`:
  `\sprintf('%s/Presentation/views', \dirname(path: __DIR__, levels: 2))` — не зависит от
  конфигурации директорий. Именованные аргументы `dirname` — требование правила
  `RequireNamedArgumentsRule` (`dirname` не variadic; `sprintf` исключён как variadic).
- Регистрация выполняется в `init()` (до рендеринга в рантайме конфиг `views` не заморожен);
  `ViewsBootloader` указан зависимостью, чтобы дефолты были установлены раньше.
- Swagger перенесён через `git mv` (был под контролем). `login-code.twig` ещё не закоммичен
  (модуль Auth целиком новый/untracked), поэтому перенесён обычным `mv`.

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make phpstan` | ✓ | `No errors` (после правки `dirname` на именованные аргументы) |
| `make test` | ✓ | 599 тестов, 1956 assertions, OK |
| точечный `phpunit --display-notices` по `SpiralLoginCodeMailerTest`, `OpenApiHttpTest`, `SendLoginCodeJobTest` | ✓ | 11 тестов, 44 assertions, OK, **без notices** |

## Открытые вопросы

В полном прогоне `make test` PHPUnit показал 1 Notice («OK, but there were issues»). Точечный
прогон затронутых тестов с `--display-notices` notice не выдал — значит он предсуществующий и не
связан с этой правкой (тесты при этом проходят, это не падение). Хунтить чужой notice в рамках
этой задачи не стал.
