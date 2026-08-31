---
title: Метод when() в репозиториях через WhenSelect и AbstractRepository
date: 2026-06-16 14:22
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: —
---

# План реализации

## Задача
Добавить в построение запросов репозиториев метод `when(условие, замыкание)`, чтобы
условные `where` писались внутри fluent-цепочки, без разрыва на `if`. Создать класс
`WhenSelect`, наследующий `Cycle\ORM\Select`, и общий `AbstractRepository`, чей
`select()` возвращает `WhenSelect`. Перевести все 8 репозиториев на этот базовый класс
и переписать единственное реальное условное место — `NotificationRepository::findPageForRecipient`.

Готово, когда: `make test` и `make phpstan` зелёные, 100% покрытие сохранено,
поведение `findPageForRecipient` не изменилось.

## Контекст
- `Cycle\ORM\Select\Repository::select()` возвращает `clone $this->select` — сам `Select`
  инжектится в репозиторий, а создаёт его `RepositoryProvider` (помечен `@internal`,
  жёстко `new Select($orm, $entity)`). Подменить класс Select через штатный механизм
  Cycle нельзя.
- `Cycle\ORM\Factory::repository()` создаёт репозиторий через контейнер (`$this->factory->make()`)
  и передаёт по имени три аргумента: `select`, `orm`, `role`. Значит наш базовый репозиторий
  может принять `ORM` и `role` в конструкторе, построить `WhenSelect` и отдать его в
  `parent::__construct()`. Тогда `$this->select` становится `WhenSelect`, а базовый
  `select()` (через `clone $this->select`) сам возвращает `WhenSelect` — отдельно
  переопределять `select()` не нужно.
- Spiral `resolveArguments` (`vendor/spiral/framework/.../Internal/Resolver.php`) идёт по
  параметрам конструктора, а `validateArguments` бросает `UnknownParameterException` на
  лишний именованный аргумент. Поэтому конструктор обязан объявить параметр `select`
  (фабрика его передаёт), но использовать его не обязан.
- Scope источника не применяется автоматически — `RepositoryProvider` явно делает
  `$select->scope($sourceProvider->getSource($entity)->getScope())`. Это повторяем один раз
  в конструкторе при построении `WhenSelect`.
- `Select::where()` — `where(mixed ...$args): static`, мутирует билдер и возвращает `$this`.
  `forUpdate()`, `orderBy()`, `limit()` и др. возвращают `static` — наследник `WhenSelect`
  сохранит правильный тип в цепочке (важно для `OutboxEventRepository`, где есть `->forUpdate()`).
- Все 8 репозиториев — `final`, без собственных конструкторов, `extends Cycle\ORM\Select\Repository`
  с PHPDoc `@extends Repository<Entity>`. Миграция единообразна.
- Единственный `if + where` сейчас — `NotificationRepository::findPageForRecipient`
  (ровно пример из задачи).
- PHPStan level max + строгие правила: булев параметр у `when` → на вызове обязательны
  именованные аргументы. `ORMInterface::getService()` возвращает нетипизированный `object`
  (PHPStan-расширения для него в проекте нет), поэтому scope берём через типизированный
  `Cycle\ORM\ORM::getSource(): SourceInterface`.
- Контейнер биндит `ORMInterface::class => ORM::class` (`CycleOrmBootloader`), поэтому в
  конструктор репозитория по ключу `orm` приходит конкретный `Cycle\ORM\ORM`.
- Репозитории резолвятся через `Spiral\Cycle\Injector\RepositoryInjector` →
  `ORM::getRepository($role)`; тесты берут репозиторий из контейнера
  (`$this->getContainer()->get(...Repository::class)`).
- Scoped-сущностей в проекте сейчас нет (нет soft delete, `deleted_at`, `ScopeInterface`,
  `SchemaInterface::SCOPE`). Поэтому `getSource($role)->getScope()` вернёт `null`, и
  `$select->scope(null)` — no-op. Строка с `scope()` нужна не «иначе сломается», а для
  точного воспроизведения логики `RepositoryProvider` и совместимости на будущее.
- `Cycle\ORM\Select::scope()` возвращает `self` (не `static`), как и `limit()`/`offset()`/
  `load()`/`with()`/`fetchOne()`. Поэтому `scope()` нельзя ставить в fluent-цепочку с
  `when()`: в конструкторе вызвать отдельным оператором. По той же причине в цепочке
  доменного метода `when()` ставится до `limit()`/`offset()` (после `limit()` тип сужается
  до `Select`, для финального `fetchAll()` это неважно).
- `select()` базового `Repository` объявлен как `: Select` с `@return Select<TEntity>`. Без
  уточнения типа PHPStan не увидит `when()` (его нет у `Select`). Тип уточняется аннотацией
  `@method WhenSelect<TEntity> select()` на `AbstractRepository` — без переопределения метода
  (тела нет). Если PHPStan на max не уважает `@method` для унаследованного метода — фолбэк:
  однострочный `select(): WhenSelect { /** @var WhenSelect<TEntity> */ return parent::select(); }`
  (только приведение типа, без логики запроса).
- `Factory::repository()` объявляет `?Select $select`, а `RepositoryProvider` кладёт `null`
  только для бестабличных ролей. Базовый `Cycle\ORM\Select\Repository::__construct` тоже
  принимает non-nullable `Select`, т.е. репозитории Cycle создаются лишь для табличных
  ролей. Все 8 наших сущностей табличные — инвариант соблюдён, конструктор принимает
  non-nullable `Select`.
- При подходе через конструктор `$this->select` — это `WhenSelect`, поэтому repo-уровневый
  `Repository::forUpdate()` (мутирует `$this->select`) и `__clone()` (клонирует `$this->select`)
  работают на `WhenSelect`, а не на плоском `Select`. Расхождения нет. Select-уровневый
  `forUpdate()` в `OutboxEventRepository` (`select()->...->forUpdate()`) тоже идёт через
  `WhenSelect` и сохраняет тип (`forUpdate(): static`).

## Принятые решения
- **Подход — наследник Select + общий базовый репозиторий.** Источник подтверждения:
  явный запрос пользователя; технически это единственный чистый способ дать fluent
  `->when()` на объекте из `$this->select()` (Cycle Select не поддерживает macro/mixin).
- **Размещение:** `App\Shared\Infrastructure\Cycle\WhenSelect` и
  `App\Shared\Infrastructure\Cycle\AbstractRepository` (рядом с существующими Cycle-классами:
  `ValueObjectCast`, typecast'ы). Источник: `decision_mode: recommend_and_ask`,
  согласуется с arch.md (Typecast/Cycle-инфраструктура → `Shared/Infrastructure/Cycle`).
- **Объём миграции — все 8 репозиториев** на `AbstractRepository`, плюс обновление примера
  в `docs/code-examples.md`. Источник: ответ пользователя.
- **Тип `orm` в конструкторе — конкретный `Cycle\ORM\ORM`**, не `ORMInterface`:
  `getSource()` объявлен на `ORM` и типобезопасен, а `ORMInterface::getService()` отдаёт
  `object` и потребовал бы запрещённого `instanceof`/`assert`. Формально `Factory::repository()`
  типизирует аргумент как `ORMInterface`, но контейнер биндит `ORMInterface => ORM`, поэтому
  в runtime приходит именно `ORM`; PHPStan не анализирует `$container->make()` фабрики, так
  что ошибки типов не возникает. Это осознанное инфраструктурное допущение (arch.md:
  `Infrastructure -> framework/runtime libraries`).
- **Сигнатура `when(bool $condition, callable $callback): static`**, замыкание получает
  `WhenSelect` и возвращает `void`. Для nullable-курсора скалярное значение извлекается до
  цепочки (`$cursorId = $cursor?->value()`), внутри замыкания идёт в `where()` (принимает
  `mixed`) — PHPStan чист, а `condition: $cursorId !== null` не сужается внутри замыкания,
  но это и не требуется, потому что замыкание выполняется только при истинном условии.
- **`WhenSelect` объявляется с `@template-covariant TEntity of object` / `@extends Select<TEntity>`**
  (как у родителя `Select`). Ковариантность нужна и для совместимости с `Select`, и чтобы
  замыкание `callable(self<TEntity>): void` приняло голый `WhenSelect $query` без generic
  в сигнатуре. **Фолбэк:** если `make phpstan` ругается на контравариантность параметра
  callable, ослабить PhpDoc до `@param callable(WhenSelect): void $callback` (без
  template-параметра в callable) — это первый шаг проверки в фазе 1, без baseline/ignore.
- **`WhenSelect` подставляется в базовый репозиторий через конструктор, а не строится в
  переопределённом `select()`** (по предложению пользователя). Конструктор
  `(Select $select, ORM $orm, string $role)` строит `new WhenSelect($orm, $role)`, применяет
  scope и вызывает `parent::__construct($whenSelect)`; инжектированный `$select` объявлен
  ради контракта фабрики, но не используется (Cycle жёстко создаёт базовый `Select`,
  подменить его класс в `RepositoryProvider` нельзя). Выигрыш: `$this->select` —
  `WhenSelect`, поэтому `clone`/repo-`forUpdate()` консистентны, scope считается один раз,
  логика `select()` не дублируется. `select()` не переопределяется — тип уточняется
  `@method WhenSelect<TEntity> select()` (фолбэк — однострочный override, см. «Контекст»).

## Целевой алгоритм
1. Контейнер резолвит репозиторий → `RepositoryInjector` → `ORM::getRepository()` →
   `Factory::repository()` передаёт в конструктор `AbstractRepository` значения
   `select`, `orm`, `role`. Конструктор строит `new WhenSelect($orm, $role)`, применяет
   scope источника и кладёт его в базовый репозиторий (`parent::__construct($whenSelect)`).
2. Доменный метод репозитория вызывает `$this->select()`. Базовый `Repository::select()`
   возвращает `clone $this->select`, то есть `WhenSelect`.
3. Метод строит цепочку: `->where(...)->when(condition, closure)->orderBy(...)->limit(...)->fetchAll()`.
   `when` при истинном `condition` вызывает замыкание, передавая туда тот же `WhenSelect`;
   замыкание добавляет `where`; затем `when` возвращает `WhenSelect` — цепочка продолжается.
4. Результат гидрируется Cycle в сущности и оборачивается в доменную коллекцию — как раньше.

## Контракты реализации

### Данные и БД
Не затрагивается. Миграций нет, SQL-поведение `findPageForRecipient` идентично
(тот же `WHERE user_id = ? [AND id < ?] ORDER BY id DESC LIMIT ?`).

### API и внешние контракты
Не затрагивается. Публичные сигнатуры методов репозиториев и HTTP/Query-слой не меняются.

## Фазы выполнения

### 1. Инфраструктура `WhenSelect` + `AbstractRepository` и рефакторинг `NotificationRepository`
Цель: ввести рабочий `when()`, доказав обе его ветки на существующих тестах пагинации.

Что сделать:
- Создать `app/src/Shared/Infrastructure/Cycle/WhenSelect.php`: `class WhenSelect extends Select`
  с PHPDoc `@template-covariant TEntity of object` / `@extends Select<TEntity>`. Метод:
  ```php
  /**
   * @param callable(self<TEntity>): void $callback
   *
   * @return $this
   */
  public function when(bool $condition, callable $callback): static
  {
      if ($condition) {
          $callback($this);
      }

      return $this;
  }
  ```
  `declare(strict_types=1)`, комментарий метода на русском. Первым делом прогнать `make phpstan`
  на этом файле + вызове в `NotificationRepository`; при ошибке контравариантности callable —
  фолбэк `@param callable(WhenSelect): void $callback` (см. «Принятые решения»).
- Создать `app/src/Shared/Infrastructure/Cycle/AbstractRepository.php`:
  `abstract class AbstractRepository extends Repository` с `@template TEntity of object` /
  `@extends Repository<TEntity>` и аннотацией `@method WhenSelect<TEntity> select()` на классе
  (метод НЕ переопределяем — аннотация только уточняет тип возврата для PHPStan). В
  конструкторе строим `WhenSelect` из `orm`+`role`, применяем scope ОТДЕЛЬНЫМ оператором
  (`scope()` возвращает `self`, не `static`) и отдаём в базовый репозиторий:
  ```php
  /**
   * @param Select<TEntity> $select Инжектируется фабрикой Cycle и не используется:
   *        вместо плоского Select в базовый репозиторий кладётся WhenSelect (Cycle жёстко
   *        создаёт базовый Select в RepositoryProvider, подменить его класс там нельзя).
   */
  public function __construct(Select $select, ORM $orm, string $role)
  {
      $whenSelect = new WhenSelect($orm, $role);
      // Scope источника — как в RepositoryProvider (scoped-сущностей сейчас нет → no-op,
      // строка ради совместимости на будущее).
      $whenSelect->scope($orm->getSource($role)->getScope());

      parent::__construct($whenSelect);
  }
  ```
  `Select` в сигнатуре non-nullable — инвариант табличных ролей (см. «Контекст»). Поля `orm`/
  `role` хранить не нужно: они используются только в конструкторе. **Фолбэк к `@method`:** если
  `make phpstan` не уважает аннотацию для унаследованного `select()` — добавить однострочный
  `public function select(): WhenSelect { /** @var WhenSelect<TEntity> */ return parent::select(); }`.
- Перевести `NotificationRepository` на `AbstractRepository` (`use`,
  `@extends AbstractRepository<Notification>`, `extends AbstractRepository`).
- Переписать `findPageForRecipient`:
  ```php
  $cursorId = $cursor?->value();

  return new NotificationCollection(
      $this->select()
          ->where('user_id', $userId->value())
          ->when(
              condition: $cursorId !== null,
              callback: static function (WhenSelect $query) use ($cursorId): void {
                  $query->where('id', '<', $cursorId);
              },
          )
          ->orderBy(expression: 'id', direction: 'DESC')
          ->limit($limit)
          ->fetchAll(),
  );
  ```
  Комментарий про cursor-пагинацию (rules.md:32) сохранить. `when()` ставится до `orderBy()`
  и до `limit()` (после `limit()` тип сужается до `Select`).

Результат: `when()` и override `select()` покрыты существующими тестами
`NotificationRepositoryTest` (первая страница — `cursor=null`, ветка false; вторая —
`cursor!=null`, ветка true).

Трассировка 100% покрытия двух НОВЫХ файлов под gate (`make test-coverage`, порог 100 по
`app/src`):
- конструктор `AbstractRepository` (включая `new WhenSelect(...)` и строку `scope()`) —
  выполняется при резолве `NotificationRepository` из контейнера в любом тесте;
- если применён фолбэк-`select()` — покрывается любым методом `NotificationRepository`,
  идущим через `select()` (`findPageForRecipient`, `countUnreadForRecipient`);
- обе ветки `when()` — единственная точка во всём проекте:
  `NotificationRepositoryTest::testFindPageForRecipientOrdersByIdDescAndPaginates`
  (страница без курсора = ветка false, страница с курсором = ветка true). Зависимость
  зафиксирована: этот тест нельзя ослаблять, иначе gate упадёт.

Сценарии тестирования:
- `testFindPageForRecipientOrdersByIdDescAndPaginates` проходит без изменений
  (первая и вторая страница, чужой пользователь отфильтрован).
- Остальные методы `NotificationRepository` (через `select()` и `findOne`) работают.

Проверка:
- `make phpstan` зелёный (без baseline/ignore; при ошибке callable — фолбэк PhpDoc).
- `make test` зелёный, покрытие нового кода 100%. Если отчёт покрытия покажет непокрытую
  строку в `WhenSelect`/`AbstractRepository` — добавить точечный кейс в
  `NotificationRepositoryTest` ДО завершения фазы.

### 2. Миграция остальных 7 репозиториев и пример в docs
Цель: единый базовый класс во всём проекте.

Что сделать:
- Перевести на `AbstractRepository` (замена `use Cycle\ORM\Select\Repository;` →
  `use App\Shared\Infrastructure\Cycle\AbstractRepository;`, `@extends Repository<Entity>` →
  `@extends AbstractRepository<Entity>`, `extends Repository` → `extends AbstractRepository`;
  логику методов не трогать, классы остаются `final`): `MediaRepository`,
  `MediaImageConversionRepository`, `MediaMultipartUploadRepository`,
  `MediaVideoConversionRepository`, `NotificationDeviceTokenRepository`,
  `NotificationSettingRepository`, `OutboxEventRepository`.
- `OutboxEventRepository`: `->forUpdate()` в цепочке `select()->...->forUpdate()` остаётся
  Select-уровневым, идёт через `WhenSelect` и сохраняет тип `WhenSelect<StoredOutboxEvent>`
  (`forUpdate(): static`) → `->limit()->fetchAll()` корректны.
- Обновить пример «Репозиторий» в `docs/code-examples.md` (`UserRepository`, doc-эталон):
  заменить `use Cycle\ORM\Select\Repository;` → `use App\Shared\Infrastructure\Cycle\AbstractRepository;`,
  `@extends Repository<User>` → `@extends AbstractRepository<User>`,
  `final class UserRepository extends Repository` → `... extends AbstractRepository`.

Результат: все 8 репозиториев на `AbstractRepository`; `select()` с `when()` доступен везде.
В 7 из 8 репозиториев изменение — только не исполняемые строки `use`/`@extends`/`extends`
(функциональной выгоды нет, миграция санкционирована ответом пользователя ради единообразия;
покрытие этих файлов не деградирует).

Сценарии тестирования:
- Полный `make test` (не по модулям) — все существующие тесты репозиториев
  Media/Notifications/Outbox и Application-тесты проходят без изменений (доказывает, что
  конструктор `AbstractRepository` + `scope()` корректны для всех 8 ролей).

Проверка:
- `make phpstan` зелёный.
- `make test` зелёный, покрытие 100%.

## Тесты
Стратегия `after_each_phase`: после каждой фазы — полный прогон `make test` + `make phpstan`.
Отдельные новые тест-кейсы не требуются: обе ветки `when()` и конструктор `AbstractRepository`
уже исчерпывающе покрываются действующим `NotificationRepositoryTest` (пагинация с курсором
и без) и тестами остальных репозиториев. Если отчёт покрытия после фазы 1 покажет
непокрытую строку в новом коде — добавить точечный кейс в `NotificationRepositoryTest`.

## Логирование
Стратегия `debug_precise` относится к Application-обработчикам с бизнес-решениями.
Изменения затрагивают только инфраструктурный слой построения запросов (`WhenSelect`,
`AbstractRepository`) и read-метод репозитория — проект такие места не логирует
(rules.md: «Логирование: DEBUG для бизнес-логики»). Новых логов не добавляем; ветвление
`when` не несёт бизнес-семантики.

## Документация и эксплуатация
- Обновить пример репозитория в `docs/code-examples.md`.
- Env/runbook/релиз не затрагиваются. Миграций нет, откат — обычный реверт кода.

## Изменения после мета-ревью

### Уточнение подхода (по запросу пользователя)
`WhenSelect` подставляется в базовый репозиторий **через конструктор**
(`parent::__construct(new WhenSelect(...))`), а не строится в переопределённом `select()`.
Метод `select()` не переопределяется — тип уточняется аннотацией `@method WhenSelect<TEntity> select()`
(фолбэк — однострочный type-only override). Это устраняет расхождение `forUpdate()`/`__clone()`
(они теперь работают на `WhenSelect`), считает scope один раз и убирает дублирование логики
`select()`. Инжектированный `$select` объявлен ради контракта фабрики, но не используется;
поля `orm`/`role` не хранятся (нужны только в конструкторе).

### После моделей
- **+ Добавлено:** инвариант табличных ролей (конструктор принимает non-nullable `Select`);
  трассировка 100% покрытия двух новых файлов с явной фиксацией, что обе ветки `when()`
  держатся на единственном тесте `testFindPageForRecipientOrdersByIdDescAndPaginates`;
  фолбэк PhpDoc `callable(WhenSelect): void` на случай ошибки контравариантности на
  `make phpstan`; явное правило «`when()` ставить до `limit()`/`offset()`».
- **~ Изменено:** обоснование `scope()` переписано честно — scoped-сущностей нет,
  `scope(null)` это no-op, строка ради воспроизведения логики `RepositoryProvider`, а не
  «иначе сломается»; `WhenSelect` объявлен `@template-covariant`; зафиксировано, что
  `scope()` возвращает `self` и вызывается отдельным оператором, не в цепочке; допущение
  про конкретный `ORM` дополнено объяснением, почему PHPStan не падает (контейнерный
  `make()` непрозрачен); миграция 7 репозиториев помечена как изменение только
  не исполняемых строк без функциональной выгоды (санкционировано пользователем).
- **− Убрано:** ничего из решений плана.
- **Отклонено:** добавление спекулятивного `whenNot()` и заметок о версионировании API
  (рано, нет потребности); заведение отдельных дубль-тестов
  `testFindPageForRecipientConditionNull/NotNull` — существующий тест уже покрывает обе
  ветки, дубли противоречат «точечным изменениям»; перевод конструктора на `ORMInterface`
  с `instanceof`/`assert` — оба запрещены rules.md, конкретный `ORM` в Infrastructure чище.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-16_16-45_when-select-repository.md`

- [x] Фаза 1: `WhenSelect` + `AbstractRepository` + рефакторинг `NotificationRepository`, прогон `make phpstan`/`make test`
- [x] Фаза 2: миграция остальных 7 репозиториев и пример в `docs/code-examples.md`, прогон `make qa` (стиль/PHPStan/покрытие 100%)
