---
review: docs/reviews/2026-06-19_17-42_posts-application-presentation.md
date: 2026-06-19 18:30
status: done
---

# Фиксы по ревью: Модуль Posts/Tags/User — прикладной и презентационный слой (3-й круг)

Режим: `apply-optional`. Обязательных к правке дефектов в ревью нет — все шесть пунктов
`на усмотрение автора`. Применены все шесть (включая продуктовую развилку 5 с безопасным
дефолтом), отклонённых optional-пунктов нет.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `findByTexts` без guard-а пустого списка → ошибка БД на `IN ()` | `app/src/Modules/Tags/Repository/TagRepository.php` | `tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php::testFindByTextsWithoutArgumentsReturnsEmptyCollection` (1 ✓) | ✓ применено |
| 2 | Трансформация `UserCollection` через `array_map(...->all())` вместо метода коллекции | `app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php` | покрыто существующими mention/feed-тестами | ✓ применено |
| 3 | Англокомментарии в `en/posts.php:6,12` | `app/locale/en/posts.php` | — (комментарии, поведение не меняется) | ✓ применено |
| 4 | `PublishPostHandler` передавал `viewer: $post->userId` вместо `authUserId` | `app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php` | покрыто `PublishPostHttpTest` | ✓ применено |
| 5 | `post_mention` на черновик с битой deep-link-ссылкой (продуктовая развилка) | `app/src/Modules/Posts/Application/Post/PostContentComposer.php`, `app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php` | `CreatePostHttpTest::testDraftMentionsArePersistedButNotNotified` (1 ✓), `PublishPostHttpTest::testPublishingDraftNotifiesMentionedUsers` (1 ✓) | ✓ применено |
| 6 | Дубликаты `mediaIds` → 500 на unique-индексе `(post_id, media_id)` | `app/src/Modules/Posts/Application/Post/PostContentComposer.php` | `CreatePostHttpTest::testDeduplicatesRepeatedMediaId` (1 ✓), `RepostPostHttpTest::testDeduplicatesRepeatedMediaId` (1 ✓) | ✓ применено |
| — | Артефактные неточности плана (проза «16»→«15», битый `sources.research`) | `docs/plans/2026-06-18_17-42_posts-application-presentation.md` | — (не код) | ✓ применено для гигиены артефактов |

## Подробности

### 6. Дедуп `mediaIds`
`attachMedia` теперь проходит по `array_values(array_unique($mediaIds))` — повторяющийся
`mediaId` даёт ровно одну строку `PostMedia` вместо нарушения unique-индекса и 500. Образец —
дедуп в `attachPostMentions`. Тесты: повторённый `mediaId` в `CreatePost` и `RepostPost` →
`media` содержит ровно один элемент, `attachmentType: media`, HTTP 200.

### 1. Guard пустого списка в `findByTexts`
Добавлен ранний возврат пустой `TagCollection` при `$tagTexts === []`, симметрично `findByIds`.
Прямой repository-тест на вызов без аргументов покрывает новую ветку (раньше путь до неё не
доходил из-за guard-а в `ResolveTagsHandler`).

### 2. `->map()` вместо `array_map(...->all())`
`GetUserPublicProfilesHandler` собирает профили через `UserCollection::map(...)` и затем
`->all()` передаёт в конструктор `UserPublicProfileCollection` (разрешённое использование `->all()`
по правилу). `->all()` как аргумент `array_map` устранён. Поведение и тесты прежние; PHPStan
зелёный (типы зафиксированы локальными `@var`, т.к. larastan для `final`-коллекции сохраняет
исходный элемент при `map`).

### 3. Перевод комментариев
Два PHP-комментария `en/posts.php` переведены на русский — зеркально `ru/posts.php`.

### 4. `viewer` в `PublishPostHandler`
`fromPost(..., viewer: UserId::fromString($command->authUserId))`. Значение сейчас равно
`$post->userId` (ownership-guard строкой выше), но намерение стало явным и согласованным с
остальными handler-ами.

### 5. Уведомления о черновике (продуктовая развилка — выбран безопасный дефолт)
Выбран безопасный дефолт, согласованный с политикой видимости: упоминания на черновике
**сохраняются** (`PostMention`), но `post_mention` **не рассылается**, пока запись `Draft` —
иначе deep-link `linkTo('post', postId)` вёл бы в 404 (чужой черновик невидим). Рассылка по уже
сохранённым `PostMention` выполняется при `PublishPost`.

Правка локальная: `attachPostMentions` сохраняет упоминания всегда, а уведомляет только при
не-Draft (валидация несуществующего упоминания → 422 сохранена и для черновика, т.к.
`resolveRecipients` вызывается до раннего возврата). Новый метод
`PostContentComposer::notifyPostMentions` рассылает по сохранённым упоминаниям; он вызывается из
`PublishPostHandler` внутри той же `#[Transactional]`-транзакции при переходе `Draft → Published`
(до `run()`). Модель уведомлений (outbox/sender), `PostNotifier` и остальные пути не тронуты.
Тесты: создание черновика с упоминанием стейджит 0 уведомлений, но сохраняет `PostMention`;
публикация черновика рассылает ровно одно `post_mention` упомянутому.

## Решения по optional
- **Принято:** 1, 2, 3, 4, 5, 6 — все шесть. 6 и 5 — материальные (реальный 500 на валидном вводе
  и битая ссылка уведомления соответственно), 1 — дефект устойчивости публичного метода, 2/3/4 —
  тривиальная согласованность с правилами/остальным кодом. 5 реализован как локальная аккуратная
  правка с тестом без широкой переделки модели уведомлений, поэтому применён, а не отклонён.
- **Отклонено:** нет.
- Дополнительно (вне списка замечаний, артефактная гигиена плана): исправлены «16»→«15» в прозе
  плана (строки 24 и 551) и битый `sources.research` во front matter (на пометку «отдельный
  research-файл не создавался», как и оговаривает сам план). Это не дефект кода.

## Финальная проверка
- **QA-гейт (стиль + PHPStan + coverage):** `make qa` — ✓ зелёный. Style OK, PHPStan «No errors»,
  1120 тестов / 3689 ассертов, coverage 100.00% при пороге 100%.
- **PHPStan отдельно:** `make phpstan` — ✓ «No errors».
- **Точечный прогон затронутых тестов:** `phpunit` по CreatePost/Repost/PublishPost/TagRepository —
  ✓ 37 тестов, 95 ассертов.
- **Заметки:** в полном прогоне 1 PHPUnit-нотис — он не из затронутых файлов (точечный прогон
  затронутых тестов с `--display-notices` нотисов не дал), на зелёный статус гейта не влияет.
