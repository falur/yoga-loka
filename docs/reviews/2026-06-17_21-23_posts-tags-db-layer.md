---
title: Ревью слоя БД модулей Posts и Tags (черновик)
date: 2026-06-17 21:23
target: >-
  app/src/Modules/Posts/, app/src/Modules/Tags/,
  app/src/Shared/Domain/ValueObject/TagId.php,
  app/database/migrations/20260617.160942_0_create_posts_domain_tables.php,
  tests/Unit/Modules/Posts/, tests/Unit/Modules/Tags/,
  tests/Feature/Modules/Posts/, tests/Feature/Modules/Tags/
plan:
  - docs/plans/2026-06-17_14-16_posts-comments-db-layer.md
  - docs/plans/2026-06-17_19-02_extract-tags-module.md
mode: strict
score: 82
status: final
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet), codex (gpt-5.5)]
---

# Ревью: слой БД модулей Posts и Tags (черновик)

## Оценка

**82/100.** Слой БД реализован в основном аккуратно и по обоим планам: теги корректно
вынесены в отдельный модуль `Tags`, `TagId` перенесён в `Shared`, а в `Posts` от тегов
осталась только связь `PostTag`. Код в целом следует правилам и архитектуре проекта и
канону из `docs/code-examples.md` (фабрики `create()`, property-свойства как VO, общий
`ValueObjectCast` по соглашению и отдельные typecast для nullable↔null-object и дат,
read-only репозитории на `AbstractRepository`, курсор по UUID v7 через `when()`/`WhenSelect`).
Тесты широкие: раунд-трипы гидрации, CASCADE/RESTRICT/SET NULL, уникальные индексы,
ненулевые счётчики, пагинация, null-object'ы и граничная валидация VO. Однако есть **одна
обязательная к исправлению проблема**: `Post::create()` не держит инвариант
взаимоисключающего вложения, который план 1 явно отнёс к домену слоя БД (замечание 6) —
домен допускает противоречивое состояние прямо при создании записи. Остальное — замечания
по качеству и покрытию на усмотрение автора (дублирование счётчиков и typecast'ов,
рассинхрон ширины колонки `tags.text` и инварианта `TagText`, отсутствие feature-теста
гидрации связи `Post HasMany PostTag`, широкий `\Throwable` в feature-тестах).

## Проблемы сверки с планом

Набор сущностей, VO, typecast, коллекций, репозиториев и таблиц совпадает с контрактами
планов; порядок `down()` в миграции совпадает с заявленным; self-FK для
`parent_post_id`/`parent_comment_id` сделаны отдельным `->update()` с `indexCreate => false`,
как и предписано. Отсутствие в `Posts` классов `Tag`/`TagText`/`TagId`/`TagCollection`/
`TagRepository` — осознанный результат плана 2, а не недоделка.

Одно расхождение с планом есть и вынесено в обязательное замечание ниже (раздел
«Замечания», пункт 6): `Post::create()` не держит инвариант взаимоисключающего вложения,
который план 1 явно отнёс к домену слоя БД.

## Замечания

### 1. Четыре класса-счётчика почти полностью дублируют друг друга

Счётчики `LikesCount`, `RepostsCount`, `CommentsCount` и `RepliesCount` — это четыре
практически одинаковых класса: у всех совпадают `zero()`, `increment()`, `decrement()` и
границы, отличаются только человекочитаемое имя и текст ошибки. Сейчас это не баг и работает
корректно, но любое изменение логики счётчика (например, новая проверка переполнения или
иной текст ошибки) придётся вносить в четыре места и легко забыть одно из них. Риск —
расхождение поведения счётчиков со временем при сопровождении.

Частичный образец в проекте есть — `MediaProcessingAttempts` тоже объявляет собственный
`increment()`, поэтому такой стиль не противоречит проекту. Важная оговорка: этот образец
покрывает только `increment()`/`zero()` — метод `decrement()` в `MediaProcessingAttempts`
отсутствует (`MAX = 100`, не `PHP_INT_MAX`), он добавлен в счётчиках Posts сверх образца
по плану 1. То есть прецедент оправдывает `increment()`/`zero()`, а четырёхкратное
дублирование `decrement()` образцом не прикрыто.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Posts/Domain/ValueObject/LikesCount.php`,
  `RepostsCount.php`, `CommentsCount.php`, `RepliesCount.php` — методы
  `zero()/increment()/decrement()` идентичны по структуре.
- **Что подтверждает проблему:** тела `increment()`/`decrement()` различаются только
  строкой сообщения; `zero()` идентичен во всех четырёх; общая логика «инкремент/декремент
  с границами» не выражена в одном месте.
- **Как исправить:** при желании ввести **промежуточный абстрактный счётчик** (например
  `AbstractCounterValue extends AbstractIntegerValue`) с общими `zero()`/`increment()`/
  `decrement()`, строящими текст ошибки из `NAME`, и наследовать от него четыре счётчика,
  оставив им только `MIN`/`MAX`/`NAME`. Складывать эти методы прямо в общий
  `AbstractIntegerValue` не стоит: от него наследуются не только счётчики (`MediaPosition`,
  `MediaProcessingAttempts` и др.), и не всем наследникам нужны `increment()`/`decrement()`.
  Поэтому правка локальна для семейства счётчиков и `MediaProcessingAttempts` (у него своего
  `decrement()` нет) не трогает.
- **Тесты:** существующие тесты счётчиков (`PostsCounterTest`) остаются валидными; при
  обобщении достаточно убедиться, что они по-прежнему зелёные.

### 2. FK-нарушения в feature-тестах проверяются через широкий `\Throwable`

Проверки целостности базы (уникальные индексы, RESTRICT при удалении) во всех
feature-тестах ожидают `\Throwable` — самый широкий тип. Это работает, но такая проверка
пройдёт и в случае, когда до обращения к базе упадёт совсем другая, неожиданная ошибка
(например, ошибка типа в фикстуре). То есть тест подтверждает «что-то упало», а не «упало
именно ограничение БД». Риск проявится при будущих правках фикстур или сущностей: тест
может «зеленеть» по неверной причине и маскировать регрессию.

Замечание мягкое и осознанно остаётся optional: `expectException(\Throwable::class)` —
это сплошной канон проекта для проверки нарушений целостности БД, тем же способом
проверяются constraint-ы в `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php`
и `tests/Feature/Modules/User/Repository/UserRepositoryTest.php`. Конкретный класс
исключения, который бросает Cycle/PDO на нарушении constraint
(`Cycle\Database\Exception\StatementException\ConstrainException`), в `app/src` и `tests`
нигде не используется и не зафиксирован как контракт, поэтому жёсткой нормы тут нет —
новые тесты лишь повторяют существующую практику, а не вводят локальную слабость.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:231`,
  `LikesAndMentionsRepositoryTest.php` (несколько `testPostLikeIsUnique*`/`*MentionIsUnique*`),
  `PostMediaRepositoryTest.php:103,122`, `PostTagRepositoryTest.php:47,61`,
  `PostBlockRepositoryTest.php:128`, `CommentRepositoryTest.php:222`,
  `tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php:56,67` —
  везде `$this->expectException(\Throwable::class)`.
- **Что подтверждает проблему:** ожидаемый тип исключения не привязан к нарушению
  constraint, поэтому любой `\Throwable` в этой точке зачтётся как успех теста.
- **Как исправить:** при желании сузить ожидание до конкретного исключения целостности
  Cycle/PDO (например, `Cycle\Database\Exception\StatementException\ConstrainException` или
  актуальный класс этой версии Cycle), либо ввести общий хелпер в базовом тест-кейсе. Перед
  сужением проверить реальный класс исключения, который бросается в Docker, чтобы не
  захардкодить несуществующий тип.
- **Тесты:** изменение касается самих тестов; после сужения прогнать `make test`, что
  ограничение действительно бросает выбранный тип.

### 3. Двенадцать typecast-классов null-object'ов дублируют друг друга

Дублирование тут крупнее по объёму, чем у счётчиков, и ревью первой редакции его не
учло. Все 12 typecast-классов в `Posts/Infrastructure/Cycle` имеют структурно идентичный
`uncastValue()` (`null`-guard + `return $value->value();`; различаются только тип параметра
и тип возврата), а `castDatabaseValue()` различается только именами фабрик присутствия/
отсутствия (`none()`/`notDeleted()`/`pointingTo()`/`fromString()` и т.п.). Три
datetime-typecast (`PostDeletionTypecast`, `CommentDeletedAtTypecast`,
`BlockUnblockedAtTypecast`) вдобавок содержат идентичный блок разбора
`DateTimeInterface`/строки. Риск тот же, что у счётчиков: правка логики гидрации
null-object придётся вносить в 12 мест.

Это ровно тот канон, что уже принят в `User/Infrastructure/Cycle/BanUnbanned{At,By,Reason}Typecast`
(новые классы — его копии), поэтому решение проекту не противоречит; рекомендация мягкая.
Обобщение упирается в инфраструктурное ограничение: `ColumnValueTypecast` — пустой
маркер-интерфейс, а Cycle вызывает статические `castDatabaseValue()`/`uncastValue()`,
поэтому аккуратная общая реализация затронула бы и `User`-модуль.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Posts/Infrastructure/Cycle/PostTextTypecast.php`,
  `PostDeletionTypecast.php`, `PostLessonTypecast.php`, `PostPracticeTypecast.php`,
  `PostOriginalTypecast.php`, `CommentParentTypecast.php`, `CommentDeletedAtTypecast.php`,
  `CommentDeletedByTypecast.php`, `CommentDeletionReasonTypecast.php`,
  `BlockUnblockedAtTypecast.php`, `BlockUnblockedByTypecast.php`,
  `BlockUnblockedReasonTypecast.php`.
- **Что подтверждает проблему:** `uncastValue()` идентичен во всех 12; `castDatabaseValue()`
  отличается только парой фабричных вызовов; datetime-разбор продублирован в трёх классах.
- **Как исправить:** при желании вынести общий `null`→null-object/uncast-glue в базовый
  абстрактный typecast (со ссылкой на фабрики через шаблонный метод), оставив наследникам
  только выбор VO и фабрик. Затрагивает и `User`-модуль (`BanUnbanned*Typecast`), поэтому
  только при согласии расширить область задачи за пределы слоя БД Posts/Tags.
- **Тесты:** покрыты `PostsTypecastTest`; при обобщении достаточно сохранить их зелёными.

### 4. Ширина колонки `tags.text` (64) шире инварианта `TagText` (50)

Колонка `tags.text` объявлена `string(64)` в миграции и в Entity `Tag`
(`#[Column(type: 'string(64)')]`), при этом `TagText::MAX_LENGTH = 50`. Схема (64) шире
доменного инварианта (50): запись никогда не дойдёт до 64 символов, и при будущем
ужесточении/ослаблении длины VO рассинхрон останется незаметным. Это не баг — VO строже
схемы, обрезания данных не происходит, — но нестыковка слоя БД, которую стоит свести:
либо колонку привести к `string(50)`, либо явно зафиксировать запас 64 как намеренный.
Нарушения плана нет: план 1 фиксировал только колонку `string(64)`, длину VO не задавал.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:17`,
  `app/src/Modules/Tags/Domain/Entity/Tag.php:30` (`string(64)`) против
  `app/src/Modules/Tags/Domain/ValueObject/TagText.php:11` (`MAX_LENGTH = 50`).
- **Что подтверждает проблему:** доменный максимум (50) меньше ширины колонки (64); единый
  контракт длины не выражен в одном месте.
- **Как исправить:** при желании синхронизировать — `string(50)` в миграции и Entity, либо
  оставить 64 с явным комментарием-обоснованием запаса. Если менять ширину — это правка
  миграции (таблица новая, данных нет, backfill не нужен).
- **Тесты:** граничная длина `TagText` (50/51) покрыта `TagTextValueObjectTest`; при смене
  ширины колонки проверить раунд-трип в `TagRepositoryTest`.

### 5. Нет прямого теста связи `Post HasMany PostTag`

План 1 (раздел тестов фазы 5) явно требует проверить, что `Post HasMany PostMedia` **и
`PostTag`** возвращают коллекции нужных классов и нужной длины. Для `PostMedia` такой
feature-тест есть (через `$post->media` после чтения из БД и lazy-load `PostMedia->post`).
Для `PostTag` есть unit-проверка пустой коллекции `$post->tags` (в `create()`) и покрыт
`PostTagRepository`, но именно **feature-теста ORM-гидрации связи** — перечитать `Post` из
БД и убедиться, что `$post->tags` отдаёт `PostTagCollection` нужной длины — нет. Связь
подтверждается только косвенно (CASCADE при удалении записи и репозиторий). Пробел минорный:
сама связь объявлена в `Post` (`HasMany` на `PostTag` с `collection: PostTagCollection`), но
заявленный планом сценарий гидрации eager-связи `PostTag` из БД отсутствует.

Сюда же примыкает мелкий пробел пагинации: пустая страница за концом выборки явно
проверена для `findByUserId` (`assertCount(0, ...)`), но не для `findByPostId`/`findReplies`.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php` (есть проверка
  `$post->media`, нет аналогичной `$post->tags`/`PostTagCollection`).
- **Что подтверждает проблему:** `grep` по `tests/Feature/Modules/Posts` не находит ни
  обращения к `$post->tags`, ни использования `PostTagCollection` — eager-связь `PostTag`
  через сущность напрямую не тестируется.
- **Как исправить:** при желании добавить round-trip: создать `Post` с несколькими
  `PostTag`, после `cleanOrmHeap()` перечитать и проверить, что `$post->tags` —
  `PostTagCollection` нужной длины; опционально дополнить пустые страницы `findByPostId`/
  `findReplies`.
- **Тесты:** добавляемый сценарий и есть тест; после добавления прогнать `make test`.

### 6. `Post::create()` не держит инвариант взаимоисключающего вложения

План 1 (фаза 3) явно относит к домену слоя БД инвариант «ровно один из {None, Media,
Lesson, Practice}» и «при Lesson/Practice заполнен ровно один id». Мутаторы
`setLesson()`/`setPractice()`/`setMediaAttachment()`/`clearAttachment()` этот инвариант
держат корректно (guard-clause + `clearOtherAttachments()`). Но `Post::create()` — основной
путь конструирования — принимает `attachmentType`, `lesson`, `practice` как независимые
параметры и **записывает их без проверки согласованности**. В результате домен допускает
противоречивые состояния прямо при создании:

- `attachmentType: AttachmentType::Lesson` + `lesson: PostLesson::none()` — тип «занятие»
  без ссылки на занятие;
- `attachmentType: AttachmentType::None` + заполненный `lesson`/`practice` — ссылка есть, а
  тип говорит «нет вложения»;
- одновременно заполненные `lesson` и `practice` — два взаимоисключающих вложения сразу.

Это прямое расхождение с планом: инвариант, который план поручил домену, на пути `create()`
не закрыт. Мутаторы лечат только переходы после создания, а не само создание. Сейчас от
полного «провисания» спасает лишь то, что Application-слой (вне задачи) ещё не написан —
как только Handler начнёт собирать VO и звать `create()`, противоречивая запись пройдёт в БД.

Технические детали:

- **Тип:** `bug`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Posts/Domain/Entity/Post.php:98-125` (`create()` присваивает
  `attachmentType`/`lesson`/`practice`/`original` без guard); ср. с
  `setLesson()`/`setPractice()` (строки 193-215), где инвариант проверяется.
- **Что подтверждает проблему:** в `create()` нет ни одной проверки соответствия
  `attachmentType` ↔ заполненности `lesson`/`practice`; в `tests/Unit/.../PostEntityTest.php`
  исключения проверены только для `setLesson(none)`/`setPractice(none)` (строки 121-133), а
  негативных кейсов на `create()` с противоречивым вложением нет.
- **Как исправить:** добавить в `create()` (или в общий приватный валидатор, вызываемый и из
  `create()`, и из мутаторов) guard-проверку: для `AttachmentType::Lesson` — `lesson`
  заполнен, `practice`/`original`-конфликт исключён; для `Practice` — симметрично; для
  `None`/`Media` — `lesson` и `practice` пусты. Нарушение → `InvalidDomainValueException`.
- **Тесты:** добавить unit-кейсы на невалидные сочетания в `create()` (Lesson+none,
  None+заполненный lesson, lesson+practice одновременно) → `InvalidDomainValueException`, и
  позитивные кейсы (Lesson+lesson, Practice+practice, None+пусто, Media+пусто) проходят.

## Рекомендации

- **Править обязательно:** 6.
- **На усмотрение автора:** 1, 2, 3, 4, 5.

## Изменения после мета-ревью

Режим `strict` (из `docs/settings.yaml`: `defaults.strict: true`,
`review.include_code_quality: true`). Запущены `architecture-check`, `rules-check`,
`plan-check` (оба плана из front matter) и `quality-check` (модель sonnet). Все находки
ниже подтверждены чтением реального кода перед применением.

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:**
  - Замечание 3 (`quality`, optional): дублирование 12 typecast-классов null-object'ов —
    `uncastValue()` идентичен во всех, `castDatabaseValue()` различается только фабриками;
    объёмнее, чем дублирование счётчиков, ревью его пропустило. Подтверждено grep/чтением.
  - Замечание 4 (`quality`, optional): рассинхрон ширины колонки `tags.text` (`string(64)`)
    и инварианта `TagText::MAX_LENGTH = 50`. Подтверждено в миграции, Entity и VO.
  - Замечание 5 (`tests`, optional): нет прямого теста связи `Post HasMany PostTag` через
    `$post->tags` (для `PostMedia` тест есть; план 1 требует обе); плюс мелкий пробел пустой
    страницы `findByPostId`/`findReplies`. Подтверждено grep по feature-тестам.
- **~ Изменено:**
  - Замечание 1 (счётчики): убрана неточность — образец `MediaProcessingAttempts` НЕ имеет
    `decrement()` (`MAX = 100`), поэтому он прикрывает только `increment()`/`zero()`, а
    четырёхкратный `decrement()` добавлен сверх образца; уточнено, что обобщение локально и
    поведение `MediaProcessingAttempts` не меняет.
  - Замечание 2 (`\Throwable`): добавлен контекст, что это сплошной канон проекта
    (Media/User feature-тесты), `ConstrainException` нигде не используется как контракт;
    рекомендация осознанно остаётся optional, а не ужесточается.
  - Шапка оценки и список рекомендаций приведены к новому набору (1–5).
- **− Убрано:** ничего — оба исходных замечания подтверждены кодом построчно, ложных
  утверждений в ревью не найдено.
- **Отклонено:**
  - Ужесточение замечания 2 до «править обязательно» — отклонено: `architecture-check` и
    `quality-check` подтвердили, что широкий `\Throwable` для constraint-нарушений —
    устоявшийся канон проекта, а конкретного класса-контракта в проекте нет.
  - Вынос пробела пустой страницы `findByPostId`/`findReplies` в отдельное замечание —
    отклонено как несамостоятельное: свёрнут абзацем в замечание 5, чтобы не дробить
    минорный тестовый пробел.
  - Снижение балла из-за расхождения `unblock_reason` (research) ↔ `unblocked_reason`
    (план/код) — отклонено: код консистентен с планом 1 (зеркало `user_bans.unbanned_*`),
    расхождения «план↔код» нет, это лишь устаревшее имя в более раннем research.

### После соседнего CLI (codex, gpt-5.5)

Полный вывод — в `2026-06-17_21-23_posts-tags-db-layer_cli_meta.md`.

- **+ Добавлено:**
  - Замечание 6 (`bug`, **править обязательно**): `Post::create()` не держит инвариант
    взаимоисключающего вложения (можно создать `AttachmentType::Lesson` + `PostLesson::none()`,
    либо заполнить одновременно `lesson` и `practice`). Проверено по `Post.php:98-125`:
    `create()` присваивает поля без guard, мутаторы держат инвариант — `create()` нет;
    негативных тестов на `create()` с противоречивым вложением нет. Это прямое расхождение с
    планом 1 (фаза 3 поручает инвариант домену слоя БД). Балл снижен 90 → 82.
- **~ Изменено:**
  - Замечание 1 (счётчики): «как исправить» переписано — обобщать в отдельный промежуточный
    `AbstractCounterValue`, а не складывать `increment()`/`decrement()` в общий
    `AbstractIntegerValue` (от него наследуются и не-счётчики).
  - Замечание 3 (typecast): «байт-в-байт идентичный `uncastValue()`» → «структурно
    идентичный» (сигнатуры/типы различаются, одинакова логика).
  - Замечание 5 (`Post HasMany PostTag`): уточнено, что unit-проверка пустой `$post->tags`
    и `PostTagRepository` есть; пробел именно в feature-тесте ORM-гидрации связи из БД.
- **− Убрано:** ничего — пять optional-замечаний подтверждены кодом, ни одно не снято.
- **Отклонено:**
  - Жёсткое снижение до 82-85 «пока инвариант не закрыт» как формула — применено по существу
    (балл 82), но без привязки к «закрытию тестами»: ревью фиксирует проблему, а не статус
    исправления.
  - Совет в замечании 2 хардкодить конкретный класс `ConstrainException` как гарантированный
    — учтено: в тексте он дан лишь как пример «при желании сузить», с оговоркой проверить
    реальный тип в Docker; жёсткой рекомендации нет.

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-06-17_21-47_posts-tags-db-layer.md`

Обязательное замечание 6 исправлено (guard инварианта вложения в `Post::create()` + unit-кейсы).
Из optional приняты 4 (комментарий-обоснование запаса `tags.text`) и 5 (feature-тест гидрации
`Post HasMany PostTag`).

Отклонённые optional-решения (чтобы следующие ревьюеры не повторяли без новых аргументов):

- **1** (обобщение 4 счётчиков в `AbstractCounterValue`) — отклонено: рефактор работающего
  кода без изменения поведения, расширяет scope за пределы бага, риск регрессии; текущий
  стиль не противоречит проекту.
- **2** (сузить `\Throwable` до constraint-исключения) — отклонено: устоявшийся канон проекта
  (Media/User feature-тесты), `ConstrainException` не зафиксирован как контракт; сужение ввело
  бы локальную несогласованность.
- **3** (обобщение 12 typecast-классов) — отклонено: корректное обобщение задевает общий
  `ColumnValueTypecast` и модуль `User`, то есть расширяет область за пределы слоя БД
  Posts/Tags; продуктово-архитектурное решение вне рамок задачи.
