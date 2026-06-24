# Кросс-CLI ревью плана (codex) — 2026-06-22

Цель плана: ввести `App\Shared\Domain\Collection\TypedCollection extends Illuminate\Support\Collection` с методом `mapToList(callable): list<TNew>`; перевести 34 коллекции на базу; заменить ручные `\array_values(...->all())` на `mapToList()`; устранить round-trip в `GetTagsHandler` и generic-break в `PostContentComposer`. Поведение и форма JSON не меняются.

Вердикт: план реализуем. `TypedCollection extends Illuminate\Support\Collection` не ломает Cycle (фабрика выбирается через `is_subclass_of()` и создаёт `new $class($data)`). Замечания — про критерии готовности, покрытие тестами и полноту исключений, не про невозможность.

## Замечания (все приняты и внесены в план)

- [тесты] Критерий «JSON не меняется ни на байт» не доказан существующими HTTP-тестами (часто проверяют только count/отдельные поля) — усилить проверку `data` для ленты, комментариев, ответов, сессий, уведомлений, настроек либо смягчить критерий до «форма и значения не меняются».
- [пропуск] «Все коллекции проекта» = 34 Laravel-коллекции в `app/src/Modules`; `LazyGhostPendingRelationReferenceCollection` — не Illuminate Collection, не трогать.
- [проверка] Финальный grep слабый: `extends Collection` не ловит забытый `@extends Collection` и лишний `use` — проверять три шаблона в `*Collection.php`.
- [риск] `mapToList()` доступен и на коллекциях-картах (`TagTextCollection<string,string>`), где ключ важен; метод сбрасывает ключи — в правиле/скилле запретить применять на картах.
- [PHPStan] `@param callable(TValue): TNew` разумен, но внутри callback идёт в `Collection::map()` (ждёт `callable(TValue, TKey)`) — после фазы 1 гнать `make phpstan`; при падении обернуть во внутреннюю двухаргументную лямбду.
- [правила] В PHPDoc нового класса англицизмы «дженерик»/«callback» — заменить на «обобщённый тип»/«функция преобразования» (идентификаторы не трогать).
- [тесты] Гидрация Cycle покрыта не для всех коллекционных связей — явно покрыть `Post::$media`, `Post::$tags`, `Media::$imageConversions/$videoConversions/$audioConversions`.
- [тесты] `MediaMultipartPartCollection` — критерий точнее: сортировка, запрет дублей, `jsonSerialize()` после смены родителя.
- [пропуск] Вне темы коллекций остаются валидные `array_map`/`array_values`: `PostResource`, `NotificationSettingController::updates`, репозитории с `Parameter`, `NotificationChannelDefaults`, `OpenApiConfig` — внести в «сознательно оставить».
- [пропуск] `GetTagsHandler` зависит от сохранения строковых ключей при `new TagTextCollection($baseCollection)` — тест `assertSame([$id => $text], $tags->all())`, а не только `get($id)`.
- [риск] `authorViews()` (`unique()`) и `originalViewsByPost()` (карта) — корректно оставлены; правило должно явно разрешать `map()->unique()->all()` как границу `list<T>` и фиксировать карту как исключение.
- [тесты] `PostContentComposer::notifyPostMentions()` — указать тест обязательным (убирается `@var list<string>`).
- [смежный код] `SendPushNotificationHandler::tokenValues()` — сохранить порядок токенов и удаление невалидных; точное сравнение списка вместо `assertContains`.
- [документация] Добавить проверку `docs/code-examples.md` (старого шаблона там нет — не забыть при будущих правках).
- [готовность] Явный финальный grep по `app/src` на `array_values`/`array_map`/`->all()` с ручной классификацией — критерий готовности фазы 3.

Реакция на замечания — в плане, раздел «Реакция на ревью (strict, кросс-CLI codex)». Спорных и отклонённых пунктов нет.
