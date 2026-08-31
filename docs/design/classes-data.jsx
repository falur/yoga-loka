// classes-data.jsx — данные для страницы «Занятия»
//   MY_CLASSES     — занятия, которые веду я: создаю, изменяю, удаляю.
//                    visibility — кто видит занятие (см. ниже).
//   JOINED_CLASSES — занятия других преподавателей, где я участвую:
//                    смотрю подробности и могу «не участвовать».
//   AUDIENCE       — аккаунты-ученики, которым можно открыть доступ к моему занятию.
//
// visibility.mode: 'all'      — все мои подписчики
//                  'selected' — только выбранные аккаунты (visibility.accounts — их id)
//                  'link'     — только у кого есть ссылка
// Экспорт в window: AUDIENCE, MY_CLASSES, JOINED_CLASSES, plAcc

// ── аккаунты-ученики (для выбора аудитории занятия) ──────────────
const AUDIENCE = [
  { id: 'u_vera',  name: 'Вера Лотос',   handle: '@vera.om',     emoji: '🪷', hue: '#9B5DE5', note: 'Постоянная ученица' },
  { id: 'u_oleg',  name: 'Олег Поток',   handle: '@oleg.flow',   emoji: '🌊', hue: '#4D9DE0', note: 'Виньяса · утро' },
  { id: 'u_nika',  name: 'Ника Аум',     handle: '@nika.aum',    emoji: '🕉️', hue: '#FF6F61', note: 'Новичок' },
  { id: 'u_mark',  name: 'Марк Тишина',  handle: '@shanti',      emoji: '🌙', hue: '#22C2B0', note: 'Медитация' },
  { id: 'u_dasha', name: 'Дарья Свет',   handle: '@dasha.light', emoji: '🌻', hue: '#FFB020', note: 'Инь · вечер' },
  { id: 'u_sveta', name: 'Светлана Ом',  handle: '@sveta.om',    emoji: '💫', hue: '#F15BB5', note: 'Пранаяма' },
  { id: 'u_anton', name: 'Антон Ритм',   handle: '@anton.flow',  emoji: '🍃', hue: '#5DBB63', note: 'Поток' },
  { id: 'u_lena',  name: 'Лена Ясная',   handle: '@lena.clear',  emoji: '🌸', hue: '#FF8A7E', note: 'Хатха' },
  { id: 'u_kira',  name: 'Кира Волна',   handle: '@kira.wave',   emoji: '🐚', hue: '#4D9DE0', note: 'Восстановление' },
  { id: 'u_roma',  name: 'Рома Корень',  handle: '@roma.root',   emoji: '🌳', hue: '#5DBB63', note: 'Силовая практика' },
];

// ── мои занятия ──────────────────────────────────────────────────
// co — соведущие из window.TEACHERS (surya / mira / kira); вы (self) ведёте всегда
const MY_CLASSES = [
  { id: 'mc1', title: 'Мягкое утро · Хатха', kind: 'video', dur: '25 мин', level: 'Начало', tone: 'a',
    when: 'Пн · Ср · Пт · 08:00', live: true, free: true, image: 'mat', participants: 18,
    desc: 'Бережное хатха-утро для пробуждения тела. Дыхание и мягкие асаны на 25 минут — спокойный старт дня.',
    co: ['mira'],
    visibility: { mode: 'all', accounts: [] } },
  { id: 'mc2', title: 'Инь-вечер · раскрытие бёдер', kind: 'video', dur: '50 мин', level: 'Любой', tone: 'b',
    when: 'Чт · 20:00', price: '500 ₽', image: 'candle', participants: 6,
    desc: 'Глубокая инь-практика на раскрытие тазобедренных суставов. Долгие удержания и полное расслабление — камерная группа для своих.',
    visibility: { mode: 'selected', accounts: ['u_vera', 'u_mark', 'u_dasha', 'u_sveta', 'u_kira', 'u_lena'] } },
  { id: 'mc3', title: 'Пранаяма перед сном', kind: 'audio', dur: '20 мин', level: 'Любой', tone: 'c',
    when: 'Запись · доступна всегда', recording: true, free: true, image: 'breath', participants: 42,
    desc: 'Спокойная пранаяма перед сном: замедляем дыхание, отпускаем мысли и мягко готовимся ко сну.',
    visibility: { mode: 'link', accounts: [] } },
  { id: 'mc4', title: 'Хатха-поток · живое занятие', kind: 'video', dur: '75 мин', level: 'Любой', tone: 'a',
    when: 'Сб · 11:00', place: 'studio', price: '1 000 ₽', image: 'studio', participants: 9,
    desc: 'Динамичный хатха-поток в студии. Сила, баланс и вытяжение в кругу единомышленников под живое сопровождение.',
    co: ['surya'],
    visibility: { mode: 'selected', accounts: ['u_vera', 'u_oleg', 'u_nika', 'u_anton', 'u_lena', 'u_roma', 'u_kira', 'u_mark', 'u_dasha'] },
    venue: { studio: 'Лофт «Лотос»', address: 'ул. Покровка, 27', metro: 'Китай-город' } },
];

// ── занятия, где я участвую (ведут другие) ──────────────────────
// teacher — id из window.TEACHERS (surya / mira / kira)
const JOINED_CLASSES = [
  { id: 'jc1', title: 'Утренняя Виньяса · живой эфир', kind: 'video', dur: '60 мин', level: 'Любой', tone: 'a',
    when: 'Завтра · 08:00', live: true, teacher: 'surya', image: 'morning', free: true,
    desc: 'Энергичная утренняя виньяса в прямом эфире. Разогреваем тело серией Сурья Намаскар и плавных переходов.' },
  { id: 'jc2', title: 'Инь-практика · раскрытие', kind: 'video', dur: '50 мин', level: 'Начало', tone: 'b',
    when: 'Пт · 19:00', place: 'studio', teacher: 'mira', image: 'candle', free: true,
    desc: 'Глубокая инь-практика в уютной студии. Долгие удержания и полное расслабление. Вход свободный.',
    venue: { studio: 'Студия «Прана»', address: 'Чистопрудный б-р, 12', metro: 'Чистые пруды' } },
  { id: 'jc3', title: 'Нади Шодхана · пранаяма', kind: 'audio', dur: '12 мин', level: 'Любой', tone: 'c',
    when: 'Запись · доступна всегда', recording: true, teacher: 'kira', image: 'temple', price: '199 ₽',
    desc: 'Техника попеременного дыхания для баланса полушарий и ясности ума. Всего 12 минут до тишины внутри.' },
  { id: 'jc4', title: '40 дней медитации', kind: 'audio', dur: '40 дней', level: 'Любой', tone: 'c',
    when: 'С 15 июня · 07:00', live: true, teacher: 'surya', image: 'breath', price: '1 900 ₽',
    desc: 'Сорокадневный курс утренней медитации. Каждый день — короткая практика, дыхание и тишина.' },
];

// склонение слова «аккаунт» по числу
const plAcc = (n) => {
  const a = n % 10, b = n % 100;
  if (a === 1 && b !== 11) return 'аккаунт';
  if (a >= 2 && a <= 4 && (b < 10 || b >= 20)) return 'аккаунта';
  return 'аккаунтов';
};

Object.assign(window, { AUDIENCE, MY_CLASSES, JOINED_CLASSES, plAcc });
