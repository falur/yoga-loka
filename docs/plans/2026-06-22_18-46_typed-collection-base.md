---
title: Базовая коллекция TypedCollection и устранение round-trip «коллекция → массив → коллекция»
date: 2026-06-22 18:46
mode: strict
plan_size: normal
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
meta_reviewers: [claude-haiku, claude-sonnet, claude-opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: —
---

# План реализации

## Задача

Ввести один общий базовый класс коллекции `App\Shared\Domain\Collection\TypedCollection` (наследует `Illuminate\Support\Collection`), от которого наследуются все 34 типизированные коллекции проекта. В базовый класс вынести правильно типизированный метод `mapToList()`, который заменяет повторяющийся на местах вызова шаблон `\array_values($collection->toBase()->map($fn)->all())` / `\array_values(\array_map($fn, $collection->all()))`. После этого почистить места вызова и устранить оставшиеся перекладки «коллекция → массив → коллекция».

Готово, когда:
- существует `TypedCollection` с методом `mapToList()`, дающим PHPStan-тип `list<TNew>`;
- все 34 коллекции наследуют `TypedCollection`, а не `Illuminate\Support\Collection` напрямую;
- перечисленные ниже места вызова используют `mapToList()` вместо ручной связки `array_values + toBase/map + all` (или `array_values + array_map + all`);
- round-trip «коллекция → массив-карта → коллекция» в `GetTagsHandler` и map-по-типизированной-коллекции в `PostContentComposer` устранены;
- `docs/rules.md` (пункт про коллекции) и скилл `laravel-collections` описывают `mapToList()` как канонический способ получить список из коллекции;
- HTTP-ответы API не меняются ни на байт (форма JSON прежняя);
- `make phpstan` и `make test` зелёные.

## Контекст

Факты из кода (проверено чтением файлов):

- Проект использует `illuminate/collections` (^13) без larastan; PHPStan level max читает дженерики из PHPDoc пакета. Каждая доменная/прикладная коллекция — отдельный класс `extends Collection` с `@extends Collection<TKey, TValue>` (всего 34, см. список в фазе 2).
- Граница ответа API объявлена строго как `list<T>`: `packages/spiral-openapi/src/Response/CollectionResponse.php:13` и `PaginationResponse.php:15` — `@param list<T> $data` при runtime-типе `array`.
- `Collection::all()` PHPStan видит как `array<int, T>`, а не `list<T>`. Поэтому `->values()->all()` (тоже `array<int, T>`) в границу `list<T>` отдать нельзя — `make phpstan` упадёт. Существующий код обходит это через `\array_values(...)` (стаб этой функции даёт `list<T>`). Это уже зафиксировано в скилле `laravel-collections` (п. 5).
- Базовый класс с методом, у которого объявлен `@return list<TNew>`, решает проблему один раз: PHPStan доверяет объявленному типу метода и проверяет тело (`array_values(...)` действительно возвращает `list`). На местах вызова `array_values`/`toBase`/`all` больше не нужны.
- Версия анализатора — `phpstan/phpstan: ^2.1.54` (`composer.lock`). Method-level дженерики (`@template` на методе) ею поддерживаются и уже используются в проде: `App\Shared\Domain\Pagination\CursorSlice::fromOverfetched()` объявляет `@template TOverValue`/`@template TOverCollection` и `@param callable(TOverValue): string`. То есть `mapToList()` опирается на ровно тот же механизм, что уже зелёный под `make phpstan`.
- Cursor-пагинация уже полностью переведена на общие примитивы `WhenSelect::cursorById()` и `CursorSlice::fromOverfetched()` (проверено: все cursor-методы репозиториев зовут `cursorById`, обработчики — `CursorSlice`). В этой задаче пагинация не затрагивается.
- Литерального round-trip `new XCollection($coll->all())` в `app/src` не осталось. Остались: ручная связка `array_values + map + all` на границах, одна перекладка в map-коллекцию (`GetTagsHandler`) и один `->map()` по типизированной коллекции со сменой типа элемента (`PostContentComposer`), который ломает дженерик и поэтому идёт через `@var`.
- Коллекции инстанцирует Cycle ORM через `IlluminateCollectionFactory` (зарегистрирована в `app/config/cycle.php:35`): фабрика выбирается по `is_subclass_of(..., Illuminate\Support\Collection)` и создаёт коллекцию как `new $concreteFinalClass($data)` по FQCN из атрибута связи (`collection:`). Identity map оперирует Entity, а не коллекциями. Поэтому вставка промежуточного родителя `TypedCollection` (который сам `instanceof Illuminate\Support\Collection`) ни выбор фабрики, ни инстанцирование, ни гидрацию не меняет. Статически это не доказывается — гарантия выносится в полный прогон `make test`, покрывающий гидрацию связей.

Применимые правила (`docs/rules.md`), которым план обязан следовать:
- «Не подменять методы коллекции array-функциями», «Без round-trip коллекция → массив → коллекция», «Collection-пайплайны», «Типизированные Laravel Collections вместо массивов».
- «Без сложных массивов в PHPDoc», «Именованные типы для сложных данных», «Без явного/неявного mixed».
- «Строгая типизация» (`declare(strict_types=1)`), «Trailing commas», «Нет мёртвого кода», «Точечные изменения».
- Размещение общего доменного кода — `Shared/Domain` (arch.md). Зависимости `Domain → Shared/Domain` и `Application → Shared/Domain` разрешены.

## Принятые решения

Все ключевые решения подтверждены пользователем в текущем диалоге (`decision_mode: recommend_and_ask`):

1. **Базовый класс:** `App\Shared\Domain\Collection\TypedCollection`, `abstract`, `extends Illuminate\Support\Collection`. Подтверждено пользователем.
2. **Метод:** `mapToList(callable $callback): array` с `@return list<TNew>` и `@param callable(TValue): TNew $callback`. Реализация — `\array_values($this->toBase()->map($callback)->all())`. Имя подтверждено пользователем.
   - Callback объявляется как `callable(TValue): TNew` (один аргумент), а не `callable(TValue, TKey): TNew`: все места вызова берут только элемент (ключ не используется), и это повторяет уже работающий идиом `CursorSlice::fromOverfetched` (`callable(TOverValue): string`). Передача одноаргументной лямбды во внутренний `->map()` (который зовёт callback с `($value, $key)`) корректна — лишний аргумент просто игнорируется, PHPStan это принимает. Так снимается любой риск претензии анализатора к арности.
   - Метод `toList()` (identity → `list<TValue>`) **не вводится**: текущих потребителей нет (все места делают преобразование типа элемента), а заводить неиспользуемый метод запрещает правило «Нет мёртвого кода». Добавится позже, если появится граница, которой нужен список тех же элементов.
   - **Инвариант:** `TypedCollection` **не объявляет собственный `__construct`**. `MediaMultipartPartCollection` переопределяет конструктор и зовёт `parent::__construct(...)`; если в базе появится свой конструктор, его сигнатура/поведение подменят `parent::` у наследника и сломают нормализацию частей. База остаётся «тонкой»: только `mapToList()`.
3. **Охват:** на базу переводятся **все** 34 коллекции, включая Application-DTO (`TagTextCollection`, `UserPublicProfileCollection`, `MediaPresignedPartCollection`, `NotificationSettingViewCollection`, `NotificationTypeDefinitionCollection`) и view-коллекции (`PostViewCollection`, `CommentViewCollection`, `AuthSessionCollection`). Подтверждено пользователем.
4. **`array_values` сохраняется только вне темы коллекций** (не считается нарушением): спред id-списка в variadic-метод (`findByIds(...$ids)` — строковые ключи в variadic недопустимы), `\array_values(\array_unique($list))` над плоским `list<string>`, и связка `map(...)->unique()->all()` там, где между map и all есть переиндексирующая операция, которую `mapToList()` не выражает (`authorViews`). `decision_mode: autonomous`-обоснование: эти места не разворачивают коллекцию в массив и обратно в коллекцию и не подменяют методы коллекции — это настоящие границы.

## Целевой алгоритм

Сквозное поведение после изменений:

1. Любая типизированная коллекция (доменная, Application-DTO, view) — это `final class XCollection extends TypedCollection` с `@extends TypedCollection<TKey, TValue>`. Она наследует весь API `Illuminate\Support\Collection` плюс `mapToList()`.
2. Когда на границе нужен `list<T>` из коллекции с преобразованием элемента (сущность → ресурс/VO/строка), код вызывает `$collection->mapToList(fn(TValue): TNew => ...)` и получает `list<TNew>`. Внутри метод один раз делает `toBase()->map()->all()` и оборачивает в `array_values`, поэтому PHPStan видит `list<TNew>`, а вызывающий код чист.
3. Границы `CollectionResponse`/`PaginationResponse` получают `list<Resource>` от `mapToList()` — форма JSON-ответа не меняется (тот же порядок, те же поля, последовательные ключи 0..n).
4. Перекладки «коллекция → промежуточный массив → коллекция» не остаётся: где на входе и выходе коллекция (или из коллекции строится другая коллекция), конвейер живёт на коллекции (`keyBy`/`map`/`filter` через `toBase()` при смене типа), без промежуточного `foreach` по массиву.

## Контракты реализации

### Данные и БД
Не затрагивается. Миграций, изменений схемы и запросов нет.

### API и внешние контракты
Не затрагивается. Маршруты, методы, request/response-DTO и форма JSON остаются прежними: `mapToList()` возвращает тот же `list<Resource>`, что и текущая связка `array_values(...->all())`. Изменение чисто внутреннее (рефакторинг типов и мест вызова). OpenApi-спецификация не меняется.

## Фазы выполнения

### 1. Базовый класс `TypedCollection`
Цель: один типизированный примитив, прячущий `array_values + toBase + map + all`.

Что сделать:
- Создать `app/src/Shared/Domain/Collection/TypedCollection.php`:
  ```php
  <?php

  declare(strict_types=1);

  namespace App\Shared\Domain\Collection;

  use Illuminate\Support\Collection;

  /**
   * Общий базовый класс для всех типизированных коллекций проекта. Наследует
   * Illuminate Collection и добавляет правильно типизированное преобразование
   * коллекции в список, чтобы на местах вызова не писать
   * \array_values($coll->toBase()->map($fn)->all()).
   *
   * @template TKey of array-key
   * @template TValue
   *
   * @extends Collection<TKey, TValue>
   */
  abstract class TypedCollection extends Collection
  {
      /**
       * Преобразовать элементы в другой тип и вернуть список с последовательными
       * ключами. Идёт через toBase(), чтобы смена типа элемента не ломала
       * обобщённый тип final-коллекции; array_values даёт PHPStan-тип list<TNew>.
       *
       * @template TNew
       *
       * @param callable(TValue): TNew $callback
       *
       * @return list<TNew>
       */
      public function mapToList(callable $callback): array
      {
          return \array_values($this->toBase()->map($callback)->all());
      }
  }
  ```
- Создать папку `app/src/Shared/Domain/Collection/` — её ещё нет (в `Shared/Domain` сейчас `Enum`, `Exception`, `Pagination`, `Trait`, `ValueObject`).
- Файл начинается с `declare(strict_types=1);` (правило «Строгая типизация»).
- Класс `abstract`, чтобы его нельзя было инстанцировать напрямую (инстанцируются только конкретные `final`-наследники). Собственного `__construct` база не объявляет (см. инвариант в «Принятых решениях»).

Результат: примитив существует, PHPStan видит `mapToList()` как `list<TNew>`.

Сценарии тестирования (через тестовую конкретную коллекцию — фикстурный `final`-наследник `TypedCollection` в каталоге тестов, либо анонимный класс `extends TypedCollection`):
- `mapToList()` на коллекции `<int, T>` преобразует элементы и возвращает список с ключами 0,1,2 в том же порядке.
- `mapToList()` на коллекции со **строковыми ключами** (кейс `TagTextCollection` — `<string, string>`) возвращает переиндексированный `list` (строковые ключи сброшены в 0,1,2).
- `mapToList()` на коллекции с «дырявыми» int-ключами возвращает список с последовательными ключами.
- Пустая коллекция → пустой список `[]`.

Проверка:
- Новый unit-тест на `TypedCollection` проходит, покрывая все ветки метода (непустая, пустая, переиндексация) для жёсткого порога 100% покрытия.
- `make phpstan` зелёный (метод типизируется как `list<TNew>`; одноаргументная лямбда в тесте не вызывает претензий к арности).
- Запасной вариант, если PHPStan всё же придерётся к передаче одноаргументной лямбды во внутренний `->map()` (который объявлен `callable(TValue, TKey)`): обернуть вызов внутри метода во внутреннюю лямбду, игнорирующую ключ — `$this->toBase()->map(static fn(mixed $value, mixed $key) => $callback($value))` без аннотации возврата (тип выводится). Решение принимается по факту первого прогона `make phpstan`, а не заранее. Публичный PHPDoc-контракт метода (`@param callable(TValue): TNew`, `@return list<TNew>`) при этом не меняется.

### 2. Перевод 34 коллекций на `TypedCollection`
Цель: все коллекции наследуют общий базовый класс.

Что сделать: в каждом файле заменить `use Illuminate\Support\Collection;` на `use App\Shared\Domain\Collection\TypedCollection;`, `extends Collection` на `extends TypedCollection`, а PHPDoc `@extends Collection<...>` на `@extends TypedCollection<...>`. Собственные методы, конструкторы и `jsonSerialize()` коллекций не трогать. (Проверено: ни один из 34 файлов не использует `Illuminate\Support\Collection` как тип в сигнатуре собственного метода — только в `extends`/`@extends`, поэтому второй импорт нигде не нужен.)

Список файлов (`app/src/Modules/...`):
- Access: `Access/Domain/Collection/PermissionCollection.php`, `RoleCollection.php`, `RolePermissionCollection.php`, `UserRoleCollection.php`
- Auth: `Auth/Domain/Collection/AuthTokenCollection.php`, `Auth/Application/Query/GetUserSessions/AuthSessionCollection.php`
- Media: `Media/Domain/Collection/MediaCollection.php`, `MediaAudioConversionCollection.php`, `MediaImageConversionCollection.php`, `MediaVideoConversionCollection.php`, `MediaMimeTypeCollection.php`, `MediaMultipartPartCollection.php`; `Media/Application/Dto/MediaPresignedPartCollection.php`
- Notifications: `Notifications/Domain/Collection/NotificationCollection.php`, `NotificationDeviceTokenCollection.php`, `NotificationSettingCollection.php`; `Notifications/Application/Dto/NotificationSettingViewCollection.php`, `NotificationTypeDefinitionCollection.php`
- Outbox: `Outbox/Domain/Collection/OutboxEventCollection.php`
- Posts: `Posts/Domain/Collection/PostCollection.php`, `CommentCollection.php`, `CommentLikeCollection.php`, `CommentMentionCollection.php`, `PostBlockCollection.php`, `PostLikeCollection.php`, `PostMediaCollection.php`, `PostMentionCollection.php`, `PostTagCollection.php`; `Posts/Application/View/PostViewCollection.php`, `CommentViewCollection.php`
- Tags: `Tags/Domain/Collection/TagCollection.php`; `Tags/Application/Dto/TagTextCollection.php`
- User: `User/Domain/Collection/UserCollection.php`; `User/Application/Dto/UserPublicProfileCollection.php`

Особые случаи:
- `MediaMultipartPartCollection` — переопределяет `__construct()` (нормализация/сортировка) и `jsonSerialize()`. Меняется только родитель и импорт; тело методов остаётся. `parent::__construct(...)` теперь зовёт конструктор `TypedCollection` (= конструктор Illuminate Collection, поведение прежнее).
- `TagTextCollection` — `@extends Collection<string, string>` (map id→text). У `TypedCollection` `@template TKey of array-key`, поэтому string-ключи допустимы: `@extends TypedCollection<string, string>`.
- `MediaMimeTypeCollection` — собственный метод `containsMimeType()` использует `$this->contains(...)` (наследуется), правок тела не требует.
- **Не трогать** `App\Shared\Infrastructure\Cycle\LazyGhostPendingRelationReferenceCollection` — это инфраструктурная DTO для Cycle ORM, она не наследует `Illuminate\Support\Collection` и в список 34 не входит. «Все коллекции проекта» в задаче означают именно 34 Laravel-коллекции в `app/src/Modules`.

Результат: 34 коллекции на общей базе, `mapToList()` доступен на каждой.

Сценарии тестирования (через существующий сьют):
- Гидрация связей Cycle ORM возвращает прежние коллекции (репозитории, отдающие коллекции, работают как раньше).
- `jsonSerialize()` `MediaMultipartPartCollection` даёт прежний результат.
- Конструктор-нормализация `MediaMultipartPartCollection` по-прежнему отклоняет дубликат номера части.

Проверка:
- В файлах `app/src/Modules/**/*Collection.php` не осталось ни `extends Collection`, ни `@extends Collection<`, ни `use Illuminate\Support\Collection;` (все три проверяются grep'ом — слабого `extends Collection` мало: можно забыть PHPDoc `@extends` или лишний импорт). Каждый из 34 файлов ссылается на `TypedCollection` в `extends` и `@extends`.
- `make phpstan` зелёный (обобщённые типы коллекций не сломаны).
- `make test` зелёный — гидрация связей Cycle ORM (`IlluminateCollectionFactory`, `app/config/cycle.php:35`) возвращает прежние конкретные коллекции. Явно убедиться, что покрыта гидрация связей, отдающих коллекции: `Post::$media` (`PostMediaCollection`), `Post::$tags` (`PostTagCollection`), `Media::$imageConversions`/`$videoConversions`/`$audioConversions` — если связь не покрыта существующим feature/flow-тестом, добавить точечную проверку, что связь отдаёт нужную коллекцию с элементами.
- Для `MediaMultipartPartCollection` после смены родителя подтвердить тестами: сортировка частей по номеру, отклонение дубликата номера части (`InvalidDomainValueException`) и неизменность `jsonSerialize()`.

### 3. Чистка мест вызова и устранение оставшихся перекладок
Цель: убрать ручную связку `array_values + map/array_map + all`, устранить два оставшихся round-trip-паттерна.

Что сделать — замена на `mapToList()` (форма результата `list<...>` сохраняется):
- `app/src/Modules/Posts/Presentation/Http/Controller/PostController.php` (`userFeed`): `$result->posts->mapToList(static fn(PostView $post): PostResource => PostResource::fromView($post))`.
- `app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php` (`list` и `replies`): `$result->comments->mapToList(...)` и `$result->replies->mapToList(...)` с `CommentResource::fromView`.
- `app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php` (`sessions`): `$userSessions->mapToList(static fn(AuthSession $session): SessionResource => SessionResource::fromSession(session: $session, currentSessionId: $listSessionsFilter->authSessionId))`.
- `app/src/Modules/Notifications/Presentation/Http/Controller/NotificationController.php` (`list`): `$result->notifications->mapToList(static fn(Notification $notification): NotificationResource => NotificationResource::fromEntity($notification))`.
- `app/src/Modules/Notifications/Presentation/Http/Controller/NotificationSettingController.php` (`resources()`): `$views->mapToList(static fn(NotificationSettingView $view): NotificationSettingResource => NotificationSettingResource::fromView($view))`.
- `app/src/Modules/Notifications/Application/Command/Push/SendPushNotification/SendPushNotificationHandler.php` (`tokenValues()`): `$deviceTokens->mapToList(static fn(NotificationDeviceToken $deviceToken): string => $deviceToken->token->value())`. Удалить пояснительный комментарий про `array_map поверх ->all()` — он больше не актуален.
- `app/src/Modules/Posts/Application/View/PostViewAssembler.php`:
  - `postIds()`: `return $posts->mapToList(static fn(Post $post): PostId => $post->id);`
  - `likedPostIds()`: `$postIds = $posts->mapToList(static fn(Post $post): PostId => $post->id);` (далее `findByUserAndPostIds($viewer, ...$postIds)` — спред `list<PostId>` в variadic).
- `app/src/Modules/Posts/Application/View/CommentViewAssembler.php` (`likedCommentIds()`): `$commentIds = $comments->mapToList(static fn(Comment $comment): CommentId => $comment->id);`

Что сделать — устранить round-trip/generic-break:
- `app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php`: убрать `foreach` и промежуточный массив `$map`, собрать `TagTextCollection` конвейером:
  ```php
  return new TagTextCollection(
      $this->tagRepository->findByIds(...$tagIds)
          ->toBase()
          ->keyBy(static fn(Tag $tag): string => $tag->id->value())
          ->map(static fn(Tag $tag): string => $tag->text->value()),
  );
  ```
  (`keyBy` → `Collection<string, Tag>`, `map` → `Collection<string, string>`; конструктор `TagTextCollection` сохраняет строковые ключи — карта id→text прежняя.) Преобразование `$query->tagIds` в `TagId` через `\array_map` оставить — это граница list→VO→variadic.
- `app/src/Modules/Posts/Application/Post/PostContentComposer.php` (`notifyPostMentions()`): заменить `->map(...)->values()->all()` по типизированной `PostMentionCollection` (ломает дженерик, поэтому стоит `@var list<string>`) на `$this->postMentionRepository->findByPostId($post->id)->mapToList(static fn(PostMention $postMention): string => $postMention->userId->value())`. Удалить аннотацию `@var list<string>` — тип теперь выводится.

Уточнение по `postIds()`/`likedPostIds()`/`likedCommentIds()`: меняется **реализация** каждого метода (один источник списка → `mapToList()`), а сами спреды `...$ids` в variadic-вызовах на местах потребления остаются — спред получает `list<…>` от `mapToList()` и работает как раньше. Противоречия с пунктом «оставить спреды variadic» нет: variadic-граница касается `...$ids`, а не построения списка.

Замены в Notifications-контроллерах (`NotificationController::list`, `NotificationSettingController::resources`) и `SendPushNotificationHandler::tokenValues` — это форма `\array_values(\array_map($fn, $coll->all()))` (через `array_map`, без `toBase()->map()`); `mapToList()` заменяет обе формы одинаково.

Сознательно оставить без изменений (задокументировано, чтобы исполнитель не трогал лишнее и чтобы новое правило `rules.md` не считалось нарушенным в этих местах):
- `PostViewAssembler::authorViews()` и `CommentViewAssembler::authorViews()` — `$userIds = \array_values($posts->toBase()->map($fn)->unique()->all())`: между `map` и `all` стоит `->unique()`, которую `mapToList()` не выражает, а результат — `list<string>` для `GetUserPublicProfilesQuery`. Это граница, не round-trip.
- `PostViewAssembler::originalViewsByPost()` (строки ~426–436) — `$originals->toBase()->map($fn)->keyBy($fn)->all()`: между `map` и `all` стоит `->keyBy()`, результат — lookup-карта `array<string, PostView>` (доступ по id за O(1)), а не `list<T>`. `mapToList()` неприменим; место не трогать.
- `MediaMultipartPartCollection::jsonSerialize()` (строки ~29–33) — `$this->toBase()->map($fn)->values()->all()` возвращает `array` (сериализация частей в JSON, контракт `jsonSerialize(): array`), а не `list<Resource>`-границу; `array_values` тут уже нет, разворачивания в коллекцию обратно тоже. Оставить как есть — это внутренняя сериализация коллекции, а не место round-trip.
- `S3MediaFileService::completeMultipartUpload()` (строки ~125–132) — `$parts->toBase()->map($fn): array)->values()->all()` для передачи частей в AWS SDK. Это инфраструктурная граница с чужим API (массив SDK), а не `list<T>`-контракт нашего ответа; `array_values` уже нет. Оставить как есть.
- Спреды id-списков в variadic (`findByIds(...$ids)` и т.п.) и `\array_values(\array_unique($list))` над плоскими `list<string>` (`PostContentComposer`, `CommentComposer`, `CheckUsersExistHandler`).
- `NotificationTypeRegistry::all()` — `\array_values($this->definitions)` над плоской регистровой картой (источник не коллекция).
- Прочие `array_map`/`array_values` вне темы коллекций — не трогать, новое правило их не касается (источник не доменная коллекция, а Filter/конфиг/ORM-граница): `PostResource` (сборка `list<...>` для JSON-ресурса), `NotificationSettingController::updates()` (маппинг Filter-DTO в Command-DTO), репозитории с `new Parameter(\array_map(...))` для `where in`, `NotificationChannelDefaults`, `OpenApiConfig`.
- `GetUserSessionsHandler` — `toBase()->groupBy()->map()->values()` уже остаётся коллекцией без разворачивания в массив; не трогать.
- Lookup-карты `array<string, X>`, собираемые `foreach` для O(1)-доступа по id в ассемблерах (`authorViews`-карты, `likedPostIds/likedCommentIds`-карты, `mediaCollectionsByPost`, `tagCollectionsByPost`, `tagViews`, `mediaItems`) — выход является картой/`list<ViewDTO>`, а не доменной коллекцией; намеренно сохранены в текущих незакоммиченных правках.

Результат: на местах вызова чистые `mapToList()`-вызовы; round-trip в `GetTagsHandler` и generic-break в `PostContentComposer` устранены; форма данных и ответов не изменилась.

Сценарии тестирования (через существующий сьют):
- Эндпоинты ленты, комментариев, ответов, сессий, уведомлений и настроек уведомлений возвращают прежний JSON (порядок и поля ресурсов не изменились). Где существующий feature-тест проверяет лишь `count` или отдельные поля, а не весь `data` — усилить проверку до сравнения всего массива `data` (или зафиксировать в журнале исполнения, что эквивалентность проверена иначе), потому что критерий «форма, порядок и значения не меняются» иначе не доказан.
- `GetTags`: проверить **полную** карту — `$tags->all() === [$id => $text, ...]` для нескольких тегов (а не только `->get($id)`), чтобы подтвердить сохранение строковых ключей при `new TagTextCollection($baseCollection)`; несуществующие id отсутствуют.
- Рассылка post_mention при публикации черновика (`PostContentComposer::notifyPostMentions`) — обязательный тест: рассылка тем же получателям после удаления ручной аннотации `@var list<string>`.
- Отправка push (`SendPushNotificationHandler::tokenValues`): список токенов формируется в том же порядке и невалидные токены удаляются как раньше — сравнение точным списком токенов (а не `assertContains`), если порядок значим.

Проверка:
- `make phpstan` зелёный (все `mapToList()`-возвраты сводятся к ожидаемым `list<...>`).
- `make test` зелёный (HTTP-интеграционные тесты маршрутов подтверждают неизменность ответов).
- Финальный `grep` по `app/src` на `array_values`, `array_map`, `->all()` с ручной классификацией: каждое оставшееся вхождение либо относится к задокументированным исключениям («сознательно оставить»), либо неприменимо к `mapToList()`. Не должно остаться ни одного места, где `mapToList()` применим, но не применён.

### 4. Обновление правил и скилла
Цель: закрепить `mapToList()` как канонический способ и снять двусмысленность вокруг `array_values`.

Что сделать:
- `docs/rules.md`, пункт про коллекции (рядом с «Без round-trip ...» и «Не подменять методы коллекции array-функциями») — добавить правило:
  > **`mapToList()` вместо `\array_values(...->all())`**: чтобы получить `list<T>` из коллекции с преобразованием элемента, вызывай метод базовой коллекции `App\Shared\Domain\Collection\TypedCollection::mapToList($fn)`, а не пиши вручную `\array_values($coll->toBase()->map($fn)->all())` или `\array_values(\array_map($fn, $coll->all()))`. Метод коллекции `->all()` PHPStan видит как `array<int, T>`, а не `list<T>`, поэтому ручная связка тянет за собой `array_values`; `mapToList()` прячет это в одном месте и сразу даёт `list<TNew>`. Для переиндексации коллекции, остающейся коллекцией, — её метод `->values()`. `mapToList()` сбрасывает ключи — **не применять на коллекциях-картах** (`TagTextCollection` и подобных `<string, …>`), где ключ несёт смысл: для них конвейер `->toBase()->keyBy($fn)->map($fn)` и оборачивание в нужную коллекцию. `\array_values(...)` допустим только вне темы коллекций: спред id-списка в variadic-метод, `\array_values(\array_unique($list))` над плоским `list<string>`, и конвейер с промежуточной переиндексирующей операцией между `map` и `all` (`->unique()`) как граница `list<T>`, которую `mapToList()` не выражает.
  Место вставки — раздел `## Качество кода` в `docs/rules.md`, непосредственно рядом с буллетами «**Без round-trip «коллекция → массив → коллекция»**» и «**Не подменять методы коллекции array-функциями**» (новый буллет идёт сразу после них, единым блоком про коллекции).
- `.claude/skills/laravel-collections/SKILL.md` — обновить **три** места, чтобы не осталось рассинхрона:
  1. п. 4 «Смена типа элемента: `toBase()->map()`» — пример с сессиями (`\array_values($userSessions->toBase()->map(...)->all())`) заменить на `$userSessions->mapToList(...)`.
  2. п. 5 «Когда `->all()` оправдан» — рекомендацию `\array_values($collection->toBase()->map($fn)->all())` заменить на `$collection->mapToList($fn)` как основной способ; `\array_values(...)` оставить только для перечисленных границ (variadic-спред, `array_unique`, `map(...)->unique()->all()`).
  3. «Ключевые правила» п. 5 — переписать на `mapToList()` как канонический способ получить `list<T>` из коллекции.

Результат: правила и скилл описывают единый способ; ревью будет ловить ручной `array_values(...->all())` там, где применим `mapToList()`.

Проверка (чеклист, ручная сверка — фаза только документационная):
- В `docs/rules.md` добавлен буллет про `mapToList()` рядом с буллетами про коллекции.
- В `SKILL.md` обновлены все три места (п. 4, п. 5, «Ключевые правила» п. 5) — поиском по `array_values` в файле не остаётся примеров `\array_values(...->toBase()->map(...)->all())`, для которых есть `mapToList()`.
- Проверить `docs/code-examples.md`: устаревшего шаблона `\array_values(...->toBase()->map(...)->all())` там сейчас нет; если в нём появится пример с коллекцией → списком, он использует `mapToList()`. (AGENTS.md держит `docs/code-examples.md` эталоном — рассинхрон недопустим.)
- Текст правил и комментариев — без англицизмов (правило «Без англицизмов»): «обобщённый тип» вместо «дженерик», «функция-преобразователь»/«функция» вместо «callback».
- Текст правил согласуется с реализацией фаз 1–3.
- Изменения только в `docs/rules.md` и `SKILL.md`; кода эта фаза не трогает.

## Тесты

Стратегия: `after_each_phase`. Фаза 1 завершается новым unit-тестом на `TypedCollection::mapToList()` плюс `make phpstan`. Фазы 2 и 3 завершаются полным прогоном `make phpstan` и `make test` (интеграционные тесты всех затронутых HTTP-маршрутов уже существуют и служат сетью безопасности — рефакторинг не должен менять их результат). Фаза 4 — только документация, отдельных тестов не требует. Новых тест-сценариев на поведение не добавляется, потому что внешнее поведение не меняется; добавляется только покрытие нового примитива `mapToList()` (правило «100% покрытие»).

## Логирование

Стратегия: `debug_precise`. Новых логов не вводится: задача — чистый рефакторинг типов и мест вызова без нового поведения, а `TypedCollection::mapToList()` — чистый метод без побочных эффектов и логирования. Существующие DEBUG-логи сохраняются дословно, включая `SendPushNotificationHandler` («Push отправлен.», «Удалён невалидный push-токen.») и `ResolveTagsHandler` («Теги разрешены.») — рефакторинг `tokenValues()`/прочих мест не затрагивает их строки и контекст. Уровни (DEBUG для нормального потока) остаются прежними.

## Документация и эксплуатация

- Обновляются `docs/rules.md` (пункт про коллекции) и `.claude/skills/laravel-collections/SKILL.md` (фаза 4).
- Изменений в env, миграциях, конфигах и зависимостях нет; релиз без особых шагов. OpenAPI не перегенерируется (контракт не меняется).

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** Зафиксирован инвариант «`TypedCollection` НЕ объявляет собственный `__construct`» (флагман-ревью: иначе `parent::__construct()` в `MediaMultipartPartCollection` сломает нормализацию частей). В фазу 1 добавлены создание папки `Shared/Domain/Collection`, требование `declare(strict_types=1)`, тест-сценарий со строковыми ключами (кейс `TagTextCollection`) и явное условие 100%-покрытия всех веток метода.
- **+ Добавлено:** В раздел «сознательно оставить» внесены три места, которые ревью нашли упущенными: lookup-карта `originalViewsByPost()` (`->map()->keyBy()->all()`), `MediaMultipartPartCollection::jsonSerialize()` и `S3MediaFileService::completeMultipartUpload()` (границы JSON/AWS SDK, возвращают `array`, не `list<T>`) — чтобы новое правило `rules.md` не считало их нарушением.
- **+ Добавлено:** В фазу 2 — критерий готовности `grep` (0 коллекций на прямом `extends Collection`) и ссылка на фактическую фабрику `IlluminateCollectionFactory` (`app/config/cycle.php:35`), подтверждающую безопасность гидрации.
- **+ Добавлено:** В фазу 4 — точное место вставки правила в `docs/rules.md` (раздел «Качество кода», рядом с буллетами про коллекции) и все три места правки в `SKILL.md` (п. 4, п. 5, «Ключевые правила»).
- **~ Изменено:** Сигнатура `mapToList` — callback объявлен как `callable(TValue): TNew` (один аргумент, как у `CursorSlice::fromOverfetched`), чтобы снять риск претензии PHPStan к арности (замечание кодового ревью).
- **~ Изменено:** В контекст добавлены факты: PHPStan `^2.1.54` с уже используемыми method-level дженериками (`CursorSlice`) и точная механика `IlluminateCollectionFactory`; уточнено, что замены в Notifications идут от формы `array_values(array_map(...->all()))`, а не только `toBase()->map()`.
- **− Убрано:** Из фазы 2 убрана неприменимая оговорка «оставить отдельный импорт `Collection`» — проверка показала, что ни один из 34 файлов не использует `Collection` как тип в сигнатуре собственного метода.
- **Отклонено:** Предложение завести метод `toList()` (identity → `list<TValue>`) — отклонено: текущих потребителей нет, неиспользуемый метод нарушил бы правило «Нет мёртвого кода» (с этим согласился и флагман-ревью). Предложение включить `jsonSerialize()`/AWS SDK места в замену на `mapToList()` — отклонено в пользу документированного исключения (точечные изменения; это границы `array`, а не round-trip и не `list<T>`-контракт).

## Реакция на ревью (strict, кросс-CLI codex)

Кросс-CLI ревью (`codex`) дало список уточнений; спорных развилок и отклонённых пунктов нет — все замечания бесспорные, внесены в план:

- **+ Тесты/JSON:** критерий «JSON не меняется» смягчён до «форма, порядок и значения не меняются» с требованием усилить проверку `data` там, где feature-тест проверяет лишь `count`/отдельные поля.
- **+ Проверка фазы 2:** grep усилен до трёх шаблонов (`extends Collection`, `@extends Collection<`, `use Illuminate\Support\Collection;`) именно в `*Collection.php`; добавлен явный пункт про гидрацию связей `Post::$media/$tags`, `Media::$imageConversions/$videoConversions/$audioConversions` и про сортировку/дубли/`jsonSerialize` у `MediaMultipartPartCollection`.
- **+ Охват:** уточнено, что 34 коллекции — это Laravel-коллекции в `app/src/Modules`; инфраструктурный `LazyGhostPendingRelationReferenceCollection` явно не трогать.
- **+ Исключения:** в «сознательно оставить» добавлены не относящиеся к теме `array_map`/`array_values` (`PostResource`, `NotificationSettingController::updates`, репозитории с `Parameter`, `NotificationChannelDefaults`, `OpenApiConfig`), чтобы новое правило не провоцировало лишние правки.
- **+ Правило:** в текст правила добавлен явный запрет применять `mapToList()` на коллекциях-картах (`<string, …>`), где ключ важен; `unique()`-исключение помечено как граница `list<T>`.
- **+ Тесты GetTags/push/mentions:** добавлены точные критерии — `assertSame` полной карты id→text для `GetTags`, точное сравнение списка токенов для `tokenValues`, обязательный тест `notifyPostMentions`.
- **+ Готовность:** в фазу 3 добавлен явный финальный grep по `app/src` с ручной классификацией оставшихся мест; в фазу 4 — проверка `docs/code-examples.md`.
- **~ Англицизмы:** замечание про `дженерик`/`callback` в комментариях принято — комментарий базового класса и тексты правил приведены к русским формулировкам (правило «Без англицизмов»), идентификаторы не тронуты.
- **~ PHPStan/арность:** добавлен явный запасной вариант (обёртка во внутреннюю двухаргументную лямбду), если первый прогон `make phpstan` придерётся к арности callback.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-23_11-46_typed-collection-base.md`

- [x] Фаза 1: Базовый класс `TypedCollection` + unit-тест
- [x] Фаза 2: Перевод 34 коллекций на `TypedCollection`
- [x] Фаза 3: Чистка мест вызова и устранение оставшихся перекладок
- [x] Фаза 4: Обновление правил (`docs/rules.md`) и скилла `laravel-collections`
- [x] Финальная проверка: `make phpstan` + `make test` + `make test-coverage` (100%)
