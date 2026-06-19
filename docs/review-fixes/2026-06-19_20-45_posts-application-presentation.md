---
review: docs/reviews/2026-06-19_20-30_posts-application-presentation.md
date: 2026-06-19 20:45
status: done
---

# Фиксы по ревью: Модуль Posts/Tags/User — прикладной и презентационный слой (4-й круг)

Режим `apply-optional`. Обязательных дефектов в ревью нет — все 6 пунктов «на усмотрение
автора». Применены пять (#1, #2, #3, #4, #5, #6 — фактически все шесть, см. ниже), все объективно
улучшают корректность/устойчивость/документацию без расширения scope.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 + 4 | Вынести разрешение получателей упоминаний и сборку профиля в один общий сервис `MentionRecipientResolver` с двумя режимами (строгий — валидация входа при создании, мягкий — рассылка по сохранённым при публикации); переиспользовать из обоих Composer-ов; убрать дублирование `resolveRecipients`/`profile` и захардкоженный ключ перевода; `notifyPostMentions` перевести на мягкий режим (без 422 на публикации) | `app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php` (новый), `PostContentComposer.php`, `CommentComposer.php` | покрыто существующими HTTP-тестами Posts (создание опубликованной/черновика с упоминанием, публикация черновика, комментарий с упоминанием, 422 на несуществующего) + новые кейсы ниже | ✓ применено |
| 2 | Для черновика заменить дорогую сборку полного профиля (с разрешением URL аватара) на дешёвую проверку существования; полный профиль — только при реальной рассылке | `MentionRecipientResolver::requireAllExist()`, `PostContentComposer::attachPostMentions()`, `app/src/Modules/User/Application/Query/CheckUsersExist/CheckUsersExistQuery.php` + `CheckUsersExistHandler.php` (новые), `app/src/Modules/User/Repository/UserRepository.php` (`countByIds`) | `tests/Feature/Modules/User/Application/CheckUsersExistHandlerTest.php` (3 ✓), `UserRepositoryTest::testCountByIdsCountsOnlyExistingUsers` (1 ✓), `CreatePostHttpTest::testRejectsNonexistentMentionInDraft` (1 ✓) | ✓ применено |
| 3 | В пакетных путях ассемблеров обрабатывать отсутствие автора явно (доменное исключение 404, как в одиночном пути), не полагаться на undefined array key | `PostViewAssembler.php` (`requireAuthor`, `fromPosts`, `originalViewsByPost`), `CommentViewAssembler.php` (`requireAuthor`, `fromComments`) | покрыто существующими тестами ленты/листингов (happy-path) | ✓ применено |
| 5 | Добавить в докблок `PostViewAssembler::mediaItem` обоснование прямого вызова `FindMediaUrlHandler` в обход QueryBus (теряемый null через шину), как в `UserPublicProfileAssembler` | `PostViewAssembler.php` (докблок `mediaItem`) | — (только документация) | ✓ применено |
| 6 | Уточнить докблок `PostViewAssembler`: батч относится к выборкам из БД, разрешение URL медиа идёт поэлементно; снять переоценку «без N+1» | `PostViewAssembler.php` (докблок класса) | — (только документация) | ✓ применено |

## Решения по optional

- **Принято: #1 + #4.** Заведён общий прикладной сервис
  `App\Modules\Posts\Application\Post\MentionRecipientResolver` с тремя методами и разведением
  ролей по назначению:
  - `requireAllExist(userIds)` — строгая дешёвая проверка существования без сборки профилей (422 на
    несуществующего). Используется при создании **черновика**.
  - `resolveRequired(userIds)` — строгое разрешение профилей + проверка полноты (422). Используется
    при создании **опубликованной** записи и в комментариях (валидация входного списка).
  - `resolveExisting(userIds)` — **мягкое** разрешение по уже сохранённым упоминаниям без проверки
    полноты (недоступные тихо пропускаются). Используется в `notifyPostMentions` при публикации
    черновика — устраняет семантический misuse строгой проверки (#1) и структурную хрупкость 422 на
    публикации.

  Ключ перевода `app.posts.mention_user_not_found` и правило проверки полноты теперь живут в одном
  месте. Дублирующиеся приватные `resolveRecipients`/`profile` удалены из обоих Composer-ов. (В
  прошлых кругах дублирование Composer-ов отклонялось, но в этом круге переоформлено как часть
  двухрежимного resolver-а с новым аргументом — применение уместно.)
- **Принято: #2.** Лёг аккуратно на новый resolver через режим «только проверка существования».
  Добавлена дешёвая межмодульная проверка `CheckUsersExist` в `User/Application` (считает строки в
  БД через `UserRepository::countByIds`, без разрешения URL аватара). Для черновика теперь идёт
  только эта проверка (422 сохранена), полный профиль с аватаром собирается лишь при реальной
  рассылке (опубликованная запись / публикация черновика).
- **Принято: #3.** В обоих ассемблерах добавлен guard `requireAuthor()`, который бросает
  `NotFoundException('app.user.not_found')` (404) при отсутствии автора в пакетной карте — то же
  поведение, что и одиночный путь (`fromPost`/`fromComment` -> `GetUserPublicProfile`). Одиночный и
  пакетный пути приведены к единому контракту; неконтролируемый undefined array key -> 500 устранён.
- **Принято: #5, #6.** Документация. В докблок `mediaItem` добавлено обоснование прямого вызова
  `FindMediaUrl` в обход шины (теряемый null), как в `UserPublicProfileAssembler`. В докблоке класса
  `PostViewAssembler` снята переоценка «без N+1»: батч относится к выборкам из БД, разрешение URL
  медиа идёт поэлементно (батч-контракта в Media нет) — это сознательный компромисс.
- **Отклонено:** ничего из шести пунктов не отклонено. Все они — чистые улучшения
  корректности/устойчивости/документации без риска регрессии.

## Заметки по реализации

- `MentionRecipientResolver` и оба Composer-а — `final readonly class`, авто-вайрятся через DI
  Spiral, явных биндингов не требуется.
- Поведение для текущей модели данных не изменилось (все шесть кейсов латентны на FK
  CASCADE/RESTRICT) — правки убирают структурную хрупкость и переоценку в документации, не меняя
  наблюдаемый ответ API. Новый negative-кейс `testRejectsNonexistentMentionInDraft` фиксирует, что
  черновик с несуществующим упоминанием по-прежнему отдаёт 422 (теперь через дешёвую проверку).
- 100% покрытие сохранено: единственная новая непокрытая ветка (throw в `requireAllExist`) закрыта
  тестом `testRejectsNonexistentMentionInDraft`.

## Финальная проверка

- **Тесты:** `make qa` (Unit, Kernel, Feature, ParaTest x4) — ✓ 1125 тестов, 3697 ассертов.
- **PHPStan:** `make qa` (composer phpstan, level max) — ✓ No errors.
- **Стиль:** `make qa` (cs) — ✓.
- **Покрытие:** `make qa` (PCOV clover) — ✓ 100.00% при пороге 100.00%.
- **Заметки:** в прогоне есть 1 PHPUnit Notice — он предсуществующий и не относится к затронутым
  файлам (изолированный прогон затронутых тестов чистый, без notices). Вне scope этих фиксов.
