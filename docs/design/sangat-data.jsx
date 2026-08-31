// sangat-data.jsx — мок-контент для ленты сообщества «Сангат» (русский)
// Сангат — духовное сообщество. Это общая лента: записи разных людей студии,
// истории сверху и личные сообщения (директ), как в Instagram.
// Экспорт в window: SANGAT_PEOPLE, SANGAT_STORIES, SANGAT_FEED, SANGAT_CHATS

// ── участники сообщества ────────────────────────────────────────
// handle → детерминированный аватар через avaFor(handle)
const SANGAT_PEOPLE = {
  you:    { name: 'Вы',            handle: 'you',         spiritual: 'Прия Дэви' },
  alina:  { name: 'Алина Север',   handle: 'alina.prana', spiritual: 'Прия Дэви',  verified: true },
  surya:  { name: 'Антон Рассвет', handle: 'surya.das',   spiritual: 'Сурья Дас',  verified: true },
  mira:   { name: 'Мира Лотос',    handle: 'mira.om',     spiritual: 'Мира Деви' },
  kira:   { name: 'Кира Волна',    handle: 'kira.jala',   spiritual: 'Джала Деви' },
  vera:   { name: 'Вера Лотос',    handle: 'vera.om',     spiritual: 'Шанти' },
  oleg:   { name: 'Олег Поток',    handle: 'oleg.flow' },
  dasha:  { name: 'Дарья Свет',    handle: 'dasha.light' },
  nika:   { name: 'Ника Аум',      handle: 'nika.aum' },
  studio: { name: 'Yoga Loka',     handle: 'yogaloka',    spiritual: 'Студия',     verified: true },
  sveta:  { name: 'Светлана Ом',   handle: 'sveta.om' },
  anton:  { name: 'Антон Ритм',    handle: 'anton.flow' },
};

// ── истории (сверху ленты) ───────────────────────────────────────
// первая — ваша (добавить). cover — ключ из REAL_IMG. slides — кадры истории.
const SANGAT_STORIES = [
  { id: 'st-you', who: 'you', label: 'Ваша история', add: true, seen: false, slides: [] },
  { id: 'st1', who: 'mira',   label: 'mira.om',     cover: 'candle', seen: false,
    slides: [ { image: 'candle', time: '2 ч', text: 'Вечерняя инь-практика 🕯️' }, { image: 'meadow', time: '2 ч', text: 'Полный покой' } ] },
  { id: 'st2', who: 'surya',  label: 'surya.das',   cover: 'asana',  seen: false,
    slides: [ { image: 'asana', time: '3 ч', text: 'Утро началось с потока ☀️' } ] },
  { id: 'st3', who: 'studio', label: 'yogaloka',    cover: 'studio', seen: false,
    slides: [ { image: 'studio', time: '5 ч', text: 'Новый зал залит светом' }, { image: 'temple', time: '5 ч', text: 'Ждём вас 🙏' } ] },
  { id: 'st4', who: 'vera',   label: 'vera.om',     cover: 'morning', seen: true,
    slides: [ { image: 'morning', time: '8 ч', text: 'Сурья Намаскар на рассвете' } ] },
  { id: 'st5', who: 'kira',   label: 'kira.jala',   cover: 'breath', seen: true,
    slides: [ { image: 'breath', time: '11 ч', text: 'Нади Шодхана перед сном' } ] },
  { id: 'st6', who: 'dasha',  label: 'dasha.light', cover: 'forest', seen: true,
    slides: [ { image: 'forest', time: '14 ч', text: 'Лес дышит вместе с нами' } ] },
];

// ── общая лента сообщества: все типы записей ─────────────────────
// type: 'photo' | 'video' | 'audio' | 'text' | 'class'
const SANGAT_FEED = [
  {
    id: 'f1', who: 'surya', type: 'photo', image: 'asana', time: '40 мин',
    tags: ['виньяса', 'поток'],
    text: 'Поймал свет на утреннем потоке. Бакасана наконец-то стала лёгкой — не силой, а дыханием 🕊️',
    likes: 214, liked: false, place: 'Студия «Прана»',
    comments: [
      { id: 'c1', who: 'vera',  text: 'Какая чистая линия! 🔥', likes: 6, time: '32 мин' },
      { id: 'c2', who: 'oleg',  text: 'Учи меня балансу 🙏', likes: 1, time: '20 мин' },
    ],
  },
  {
    id: 'f2', who: 'mira', type: 'audio', time: '1 ч',
    audio: { title: 'Инь-медитация: отпускаем день', dur: '12:40' },
    tags: ['медитация', 'инь'],
    text: 'Записала мягкое аудио на вечер. Лягте удобно, закройте глаза и просто слушайте дыхание.',
    likes: 389, liked: true,
    comments: [
      { id: 'c3', who: 'sveta', text: 'Засыпаю под неё каждый вечер 🌙', likes: 12, time: '47 мин' },
    ],
  },
  {
    id: 'f3', who: 'studio', type: 'class', time: '2 ч',
    klass: { title: 'Открытый класс · Приветствие солнцу', kind: 'video', when: 'Сб · 10:00', dur: '30 мин', level: 'Начало', free: true, image: 'morning', tone: 'a',
      desc: 'Знакомимся с базовыми асанами и дыханием. Без опыта, с нуля и бесплатно — берите коврик и хорошее настроение.' },
    tags: ['занятие', 'студия'],
    text: '☀️ В эту субботу проводим открытый класс для всех желающих. Вход свободный!',
    likes: 156, liked: false,
    comments: [
      { id: 'c4', who: 'nika', text: 'Первый раз приду, очень волнуюсь 🙈', likes: 4, time: '1 ч' },
    ],
  },
  {
    id: 'f4', who: 'vera', type: 'text', time: '3 ч',
    tags: ['осознанность'],
    text: 'Сегодня на коврике поняла простое: гибкость тела начинается с гибкости отношения к себе. Не тянитесь силой — тянитесь вниманием.',
    likes: 502, liked: false,
    comments: [
      { id: 'c5', who: 'alina', text: 'Сохранила себе. Спасибо ✨', likes: 18, time: '2 ч' },
      { id: 'c6', who: 'dasha', text: 'Как раз об этом думала утром', likes: 3, time: '1 ч' },
    ],
  },
  {
    id: 'f5', who: 'kira', type: 'video', image: 'meadow', video: { dur: '8:45' }, time: '5 ч',
    tags: ['пранаяма', 'дыхание'],
    text: 'Короткая дыхательная разминка на траве. Повторяйте за мной в своём темпе 🌬️',
    likes: 277, liked: false,
    comments: [
      { id: 'c7', who: 'anton', text: 'Идеальный темп, спасибо!', likes: 5, time: '3 ч' },
    ],
  },
  {
    id: 'f6', who: 'alina', type: 'photo', image: 'temple', time: '7 ч',
    tags: ['ретрит', 'тишина'],
    text: 'Открыта запись на майский ретрит «Тишина гор». 5 дней практики и молчания в Архызе. Осталось 4 места — пишите в директ 🏔️',
    likes: 631, liked: false, place: 'Архыз',
    comments: [
      { id: 'c8', who: 'oleg', text: 'Уже еду! Беру коврик и термос ⛰️', likes: 21, time: '5 ч' },
      { id: 'c9', who: 'nika', text: 'А для новичков подойдёт?', likes: 2, time: '4 ч' },
    ],
  },
  {
    id: 'f7', who: 'dasha', type: 'photo', image: 'candle', time: '11 ч',
    tags: ['вечер', 'свет'],
    text: 'Тихий вечер при свечах. Иногда лучшая практика — просто посидеть в тишине.',
    likes: 198, liked: false,
    comments: [],
  },
  {
    id: 'f8', who: 'oleg', type: 'text', time: '1 д',
    tags: ['благодарность'],
    text: '108 дней практики без пропусков. Спасибо этому сообществу — без вас бы не дошёл 🙏 Сангат держит.',
    likes: 421, liked: true,
    comments: [
      { id: 'c10', who: 'surya', text: 'Горжусь тобой, брат 🔥', likes: 9, time: '22 ч' },
      { id: 'c11', who: 'mira',  text: 'Вот это устойчивость! 🪷', likes: 7, time: '20 ч' },
    ],
  },
];

// ── личные сообщения (директ) ────────────────────────────────────
// from: 'me' | 'them'. Последний элемент превью = последнее сообщение.
const SANGAT_CHATS = [
  {
    id: 'm0', who: 'alina', online: true, unread: 1, time: 'сейчас',
    verified: true,
    msgs: [
      { from: 'them', text: 'Намасте 🙏 рада видеть тебя в Сангате', time: '14:02' },
      { from: 'them', text: 'Если есть вопросы по практике — пиши, отвечу ✨', time: '14:02' },
    ],
  },
  {
    id: 'm1', who: 'mira', online: true, unread: 2, time: '5 мин',
    msgs: [
      { from: 'them', text: 'Привет! Видела твою практику сегодня 🌸', time: '12:30' },
      { from: 'me',   text: 'Спасибо 🙏 ещё работаю над балансом', time: '12:31' },
      { from: 'them', text: 'Приходи завтра на инь — раскроем бёдра', time: '12:40' },
      { from: 'them', text: 'Начинаем в 19:00, место для тебя оставлю ✨', time: '12:40' },
    ],
  },
  {
    id: 'm2', who: 'surya', online: true, unread: 0, time: '1 ч',
    msgs: [
      { from: 'me',   text: 'Бакасана получилась! 🐦', time: '11:02' },
      { from: 'them', text: 'Я же говорил — дыхание, не сила 🔥', time: '11:10' },
    ],
  },
  {
    id: 'm3', who: 'studio', online: false, unread: 1, time: '3 ч',
    verified: true,
    msgs: [
      { from: 'them', text: 'Ваше место на субботний класс забронировано 🙏', time: '09:15' },
      { from: 'them', text: 'Ждём вас в 10:00, зал «Лотос»', time: '09:15' },
    ],
  },
  {
    id: 'm4', who: 'vera', online: false, unread: 0, time: '8 ч',
    msgs: [
      { from: 'them', text: 'Поделись, пожалуйста, той медитацией 🌙', time: 'Вчера' },
      { from: 'me',   text: 'Конечно, скинула в сохранённые ✨', time: 'Вчера' },
      { from: 'them', text: 'Ты лучшая 💫', time: 'Вчера' },
    ],
  },
  {
    id: 'm5', who: 'oleg', online: false, unread: 0, time: '1 д',
    msgs: [
      { from: 'them', text: 'Идём на ретрит вместе?', time: 'Пн' },
      { from: 'me',   text: 'Я за! Уже коврик собрал 🧘', time: 'Пн' },
    ],
  },
  {
    id: 'm6', who: 'dasha', online: false, unread: 0, time: '2 д',
    msgs: [
      { from: 'them', text: 'Какой свет на твоём фото 🕯️', time: 'Сб' },
      { from: 'me',   text: 'Спасибо 🙏 это закат в студии', time: 'Сб' },
    ],
  },
];

// ── уведомления (экран «сердечко» — активность сообщества) ───────
// type: 'follow' | 'like' | 'likes' | 'comment' | 'mention' | 'story' | 'studio' | 'tag'
// group: 'new' | 'today' | 'week' | 'earlier'
// thumb — ключ из REAL_IMG (превью вашей записи). others — «и ещё N».
const SANGAT_NOTIFS = [
  // ── новое ──
  { id: 'n1', type: 'like',    who: 'surya', group: 'new', time: '2 мин', thumb: 'asana',
    text: 'оценил(а) вашу запись.' },
  { id: 'n2', type: 'follow',  who: 'alina', group: 'new', time: '8 мин', followsYou: true },
  { id: 'n3', type: 'comment', who: 'mira',  group: 'new', time: '14 мин', thumb: 'meadow',
    text: 'прокомментировал(а): «Какая чистая линия дыхания 🌸»' },
  // ── сегодня ──
  { id: 'n4', type: 'likes',   who: 'vera', others: 12, group: 'today', time: '3 ч', thumb: 'candle',
    text: 'и ещё 12 человек оценили вашу запись.' },
  { id: 'n5', type: 'mention', who: 'oleg', group: 'today', time: '5 ч',
    text: 'упомянул(а) вас: «спасибо @you за поддержку на 108-й день 🙏»' },
  { id: 'n6', type: 'studio',  who: 'studio', group: 'today', time: '6 ч',
    text: 'Напоминание: открытый класс «Приветствие солнцу» в субботу в 10:00 🧘' },
  { id: 'n7', type: 'follow',  who: 'nika',  group: 'today', time: '9 ч' },
  // ── на этой неделе ──
  { id: 'n8', type: 'story',   who: 'kira', group: 'week', time: '2 д', thumb: 'breath',
    text: 'отреагировал(а) на вашу историю: ❤️' },
  { id: 'n9', type: 'like',    who: 'dasha', group: 'week', time: '3 д', thumb: 'temple',
    text: 'оценил(а) вашу запись.' },
  { id: 'n10', type: 'likes',  who: 'sveta', others: 5, group: 'week', time: '4 д',
    text: 'и ещё 5 человек оценили ваш комментарий.', thumb: 'morning' },
  { id: 'n11', type: 'follow', who: 'anton', group: 'week', time: '5 д', followsYou: true },
  // ── ранее ──
  { id: 'n12', type: 'like',   who: 'mira', group: 'earlier', time: '1 нед', thumb: 'forest',
    text: 'оценил(а) вашу запись.' },
  { id: 'n13', type: 'follow', who: 'dasha', group: 'earlier', time: '2 нед' },
];

Object.assign(window, { SANGAT_PEOPLE, SANGAT_STORIES, SANGAT_FEED, SANGAT_CHATS, SANGAT_NOTIFS });
