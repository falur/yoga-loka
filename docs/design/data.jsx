// data.jsx — mock-контент для профиля Yoga Loka (русский)
// Экспортирует в window: PROFILE, FEED, GRID, PRACTICES

const PROFILE = {
  name: 'Алина Север',
  spiritual: 'Прия Дэви',
  username: '@alina.prana',
  role: 'Преподаватель хатха- и инь-йоги',
  bio: 'Дыхание — мост между телом и умом. Веду мягкие занятия и ретриты в Москве и онлайн. Учусь у тишины уже 12 лет.',
  location: 'Москва · онлайн',
  link: 'yogaloka.app/priya',
  stats: { posts: 248, followers: '12,4К', following: 312, practices: 56 },
  tags: ['Хатха', 'Инь', 'Виньяса', 'Пранаяма', 'Медитация'],
  badges: [
    { kind: 'streak', label: '108 дней занятий', glyph: 'flame' },
    { kind: 'level', label: 'Наставник', glyph: 'lotus' },
    { kind: 'challenge', label: '30 дней дыхания', glyph: 'spark' },
  ],
};

// смешанная лента: media — массив типов контента ('photo' | 'audio' | 'video'); text — если есть подпись
// type: 'class' — анонс занятия: ссылается на practice через practiceId, в ленте рисуется карточкой занятия
const FEED = [
  {
    id: 'a1',
    type: 'class',
    practiceId: 'pr11',
    tags: ['занятие', 'медитация'],
    time: '1 ч',
    text: 'Новый курс — 40 дней утренней медитации 🌅 Сорок дней тишины, дыхания и устойчивой привычки. Стартуем 15 июня, ведём вдвоём. Места уже открыты 👇',
    likes: 187,
    liked: false,
    comments: [
      { id: 'ac1', name: 'Вера Лотос', handle: 'vera.om', text: 'Записалась! Давно ждала такой курс ✨', likes: 9, time: '38 мин' },
      { id: 'ac2', name: 'Олег Поток', handle: 'oleg.flow', text: 'А если пропущу день — можно догнать?', likes: 2, time: '24 мин' },
    ],
  },
  {
    id: 'p1',
    type: 'text',
    media: [],
    tags: ['осознанность', 'занятие'],
    time: '2 ч',
    text: 'Сегодня на коврике поймала простую мысль: гибкость тела начинается с гибкости отношения к себе. Не тянитесь силой — тянитесь вниманием.',
    likes: 342,
    liked: false,
    comments: [
      { id: 'c1', name: 'Марк Тишина', handle: 'shanti', text: 'Сохранил себе. Спасибо за напоминание.', likes: 12, time: '1 ч' },
      { id: 'c2', name: 'Вера Лотос', handle: 'vera.om', text: 'Как раз об этом думала утром на Капотасане', likes: 4, time: '47 мин' },
    ],
  },
  {
    id: 'p2',
    type: 'photo',
    media: ['photo'],
    tags: ['утро', 'асаны', 'поток'],
    time: '1 д',
    image: 'morning',
    text: 'Утренняя Сурья Намаскар на рассвете. 12 кругов в тишине — лучший способ начать день.',
    likes: 1208,
    liked: true,
    comments: [
      { id: 'c3', name: 'Дарья Свет', handle: 'dasha.light', text: 'Какая красота, где это снято?', likes: 8, time: '20 ч' },
    ],
  },
  {
    id: 'a2',
    type: 'class',
    practiceId: 'pr9',
    tags: ['занятие', 'асаны'],
    time: '1 д',
    text: 'В субботу проводим открытый класс «Приветствие солнцу» ☀️ Без опыта, с нуля и бесплатно. Берите коврик и хорошее настроение!',
    likes: 412,
    liked: false,
    comments: [
      { id: 'ac3', name: 'Ника Аум', handle: 'nika.aum', text: 'Первый раз приду, очень волнуюсь 🙈', likes: 7, time: '19 ч' },
    ],
  },
  {
    id: 'p5',
    type: 'text',
    media: ['audio'],
    tags: ['медитация', 'дыхание'],
    time: '1 д',
    audio: { title: 'Медитация: возвращение к дыханию', dur: '14:20' },
    text: 'Мягкое аудио-занятие на вечер. Закройте глаза и просто слушайте голос и дыхание.',
    likes: 489,
    liked: false,
    comments: [
      { id: 'c6', name: 'Светлана Ом', handle: 'sveta.om', text: 'Засыпаю под неё каждый вечер 🙏', likes: 17, time: '18 ч' },
    ],
  },
  {
    id: 'p6',
    type: 'photo',
    media: ['video'],
    tags: ['виньяса', 'поток', 'асаны'],
    time: '2 д',
    image: 'asana',
    video: { dur: '8:45' },
    text: 'Короткий виньяса-поток для разминки. Повторяйте за мной в своём темпе.',
    likes: 1543,
    liked: false,
    comments: [
      { id: 'c7', name: 'Антон Ритм', handle: 'anton.flow', text: 'Идеальный темп, спасибо!', likes: 9, time: '1 д' },
      { id: 'c8', name: 'Лена Ясная', handle: 'lena.clear', text: 'Больше таких видео пожалуйста', likes: 5, time: '22 ч' },
    ],
  },
  {
    id: 'p3',
    type: 'text',
    media: [],
    tags: ['ретрит', 'тишина'],
    time: '3 д',
    pinned: true,
    text: 'Открыта запись на майский ретрит «Тишина гор». 5 дней занятий, дыхания и молчания в Архызе. Осталось 4 места — пишите в директ.',
    likes: 564,
    liked: false,
    comments: [
      { id: 'c4', name: 'Олег Поток', handle: 'oleg.flow', text: 'Уже еду! Беру коврик и термос.', likes: 21, time: '2 д' },
      { id: 'c5', name: 'Ника Аум', handle: 'nika.aum', text: 'А для новичков подойдёт?', likes: 2, time: '2 д' },
    ],
  },
  {
    id: 'p7',
    type: 'text',
    media: ['audio'],
    tags: ['дыхание', 'пранаяма'],
    time: '4 д',
    audio: { title: 'Нади Шодхана · вечерняя пранаяма', dur: '06:10' },
    text: 'Попеременное дыхание перед сном. Всего шесть минут — и ум затихает.',
    likes: 321,
    liked: false,
    comments: [],
  },
  {
    // аудио без подписи — в сетке помечается иконкой и волной
    id: 'p9',
    type: 'text',
    media: ['audio'],
    tags: ['мантра', 'звук'],
    audio: { title: 'Гаятри-мантра · 108 повторов', dur: '11:20' },
    time: '3 д',
    likes: 96,
    liked: false,
    comments: [],
  },
  {
    id: 'p4',
    type: 'photo',
    media: ['photo'],
    tags: ['студия', 'пространство'],
    time: '5 д',
    image: 'studio',
    text: 'Наша новая студия наполнилась светом. Приходите чувствовать пространство.',
    likes: 877,
    liked: false,
    comments: [],
  },
  {
    id: 'p8',
    type: 'photo',
    media: ['video'],
    tags: ['инь', 'вечер', 'поток'],
    time: '6 д',
    image: 'meadow',
    video: { dur: '12:30' },
    text: 'Инь-занятие на закате среди трав. Длинные удержания и полный покой.',
    likes: 702,
    liked: false,
    comments: [],
  },
];

// сетка — только фото-записи + дополнительные кадры (с метрикой)
const GRID = [
  { image: 'morning', likes: 1240, comments: 48,  views: 12400, date: '5 июн' },
  { image: 'studio',  likes: 860,  comments: 21,  views: 8230,  date: '2 июн' },
  { image: 'asana',   likes: 3120, comments: 96,  views: 31200, date: '29 мая' },
  { image: 'breath',  likes: 540,  comments: 12,  views: 5470,  date: '24 мая' },
  { image: 'meadow',  likes: 1980, comments: 73,  views: 19800, date: '18 мая' },
  { image: 'candle',  likes: 970,  comments: 34,  views: 9650,  date: '11 мая' },
  { image: 'mat',     likes: 410,  comments: 7,   views: 4120,  date: '6 мая'  },
  { image: 'temple',  likes: 2260, comments: 58,  views: 22600, date: '1 мая'  },
  { image: 'forest',  likes: 730,  comments: 29,  views: 7340,  date: '24 апр' },
];

// ── РОСТЕР ВЕДУЩИХ ──────────────────────────────────────────────
// id 'self' — аккаунт, на котором размещено занятие (ведущий по умолчанию).
// Остальные — другие учителя студии: их можно отметить как ведущих
// (например, когда занятие ведёт коллега или студия назначает преподавателя).
const TEACHERS = [
  { id: 'self',  name: PROFILE.name, spiritual: PROFILE.spiritual, glyph: '🧘‍♀️', owner: true, role: 'Это вы · аккаунт' },
  { id: 'surya', name: 'Антон Рассвет', spiritual: 'Сурья Дас', glyph: '🧘‍♂️', role: 'Виньяса · аштанга' },
  { id: 'mira',  name: 'Мира Лотос',   spiritual: 'Мира Деви', glyph: '🪷', role: 'Инь · восстановление' },
  { id: 'kira',  name: 'Кира Волна',   spiritual: 'Джала Деви', glyph: '🌊', role: 'Пранаяма · медитация' },
];

// kind: 'video' | 'audio' — формат занятия
// status: 'upcoming' (эфир) | 'recording' (запись)
// teachers — массив id ведущих из TEACHERS. Пусто/не указано → ведёт аккаунт (self).
//   Отмечают, когда занятие ведёт другой учитель или их несколько (студия / соведение).
// enrolled — записан ли на эфир; purchased/price — куплена ли запись
// free — бесплатное занятие (доступно без оплаты); у записи может не быть даты (when)
// place: 'online' (по умолчанию) | 'studio' (живое занятие); live — онлайн-эфир
// free — без оплаты; price — для платных (коммерческих); image — обложка; desc — описание
const PRACTICES = [
  // мероприятие-курс — два ведущих (аккаунт + другой учитель)
  { id: 'pr11', title: '40 дней медитации', kind: 'audio', dur: '40 дней', level: 'Любой', tone: 'c', status: 'upcoming', when: 'С 15 июня · 07:00', live: true, enrolled: false, price: '1 900 ₽', image: 'breath', teachers: ['self', 'surya'], pub: true,
    desc: 'Сорокадневный курс утренней медитации. Каждый день — короткая практика по 20 минут, дыхание и тишина. Ведём вдвоём, сменяя друг друга, чтобы вы прошли путь от первого вдоха до устойчивой привычки.' },
  // предстоящие — онлайн-эфиры и живые занятия в студии
  { id: 'pr5', title: 'Утренняя Виньяса · живой эфир', kind: 'video', dur: '60 мин', level: 'Любой', tone: 'a', status: 'upcoming', when: 'Завтра · 08:00', live: true, enrolled: true, free: true, image: 'morning', pub: true,
    desc: 'Энергичная утренняя виньяса в прямом эфире. Разогреваем тело серией Сурья Намаскар и плавных переходов — заряд бодрости на весь день.' },
  { id: 'pr9', title: 'Открытый класс · Приветствие солнцу', kind: 'video', dur: '30 мин', level: 'Начало', tone: 'b', status: 'upcoming', when: 'Сб · 10:00', enrolled: false, free: true, image: 'asana', pub: true,
    desc: 'Открытый класс для всех желающих. Знакомимся с базовыми асанами и дыханием — приходите без опыта, всё покажем с нуля.' },
  { id: 'pr6', title: 'Пранаяма перед сном', kind: 'audio', dur: '20 мин', level: 'Любой', tone: 'c', status: 'upcoming', when: 'Ср · 21:30', enrolled: false, price: '199 ₽', image: 'breath', pub: false,
    desc: 'Спокойная пранаяма перед сном: замедляем дыхание, отпускаем мысли и мягко готовимся ко сну.' },
  // живые занятия в студии — могут быть платными и бесплатными
  { id: 'pr10', title: 'Хатха-поток · живое занятие', kind: 'video', dur: '75 мин', level: 'Любой', tone: 'a', status: 'upcoming', when: 'Сб · 11:00', place: 'studio', price: '1 000 ₽', image: 'studio', teachers: ['surya'],
    desc: 'Динамичный хатха-поток в студии. Сила, баланс и вытяжение — живая практика в кругу единомышленников под живое сопровождение.',
    venue: { studio: 'Лофт «Лотос»', address: 'ул. Покровка, 27', metro: 'Китай-город', spots: 3 } },
  { id: 'pr7', title: 'Инь-практика · раскрытие бёдер', kind: 'video', dur: '50 мин', level: 'Начало', tone: 'b', status: 'upcoming', when: 'Пт · 19:00', place: 'studio', free: true, image: 'candle', teachers: ['mira'],
    desc: 'Глубокая инь-практика на раскрытие тазобедренных суставов. Долгие удержания и полное расслабление в уютной студии. Вход свободный.',
    venue: { studio: 'Студия «Прана»', address: 'Чистопрудный б-р, 12', metro: 'Чистые пруды', spots: 6 } },
  // записи — доступны после покупки (или бесплатно)
  { id: 'pr8', title: 'Шавасана · глубокое расслабление', kind: 'audio', dur: '10 мин', level: 'Любой', tone: 'c', status: 'recording', free: true, image: 'meadow', pub: true,
    desc: 'Десятиминутная шавасана для глубокого восстановления. Лягте удобно и просто слушайте голос — тело отдохнёт само.' },
  { id: 'pr1', title: 'Мягкое утро · Хатха', kind: 'video', dur: '25 мин', level: 'Начало', tone: 'a', status: 'recording', when: '2 дня назад', purchased: true, price: '249 ₽', image: 'mat', pub: true,
    desc: 'Мягкое хатха-утро для пробуждения тела. Бережные асаны и дыхание на 25 минут — идеально для начала дня.' },
  { id: 'pr2', title: 'Инь для спины перед сном', kind: 'video', dur: '40 мин', level: 'Любой', tone: 'b', status: 'recording', when: 'Неделю назад', purchased: false, price: '299 ₽', image: 'forest', pub: false,
    desc: 'Инь для спины перед сном. Снимаем напряжение поясницы и расслабляем всё тело долгими, спокойными удержаниями.' },
  { id: 'pr3', title: 'Дыхание Нади Шодхана', kind: 'audio', dur: '12 мин', level: 'Любой', tone: 'c', status: 'recording', when: 'Неделю назад', purchased: false, price: '149 ₽', image: 'temple', pub: false,
    desc: 'Техника попеременного дыхания Нади Шодхана для баланса полушарий и ясности ума. Всего 12 минут до тишины внутри.' },
  { id: 'pr4', title: 'Виньяса-поток · Огонь', kind: 'video', dur: '55 мин', level: 'Продвинуто', tone: 'a', status: 'recording', when: '2 недели назад', purchased: true, price: '349 ₽', image: 'asana', pub: true,
    desc: 'Мощный виньяса-поток «Огонь». Продвинутый уровень: динамичные связки, баланс и выносливость на грани.' },
];

// уникальные хэштеги в порядке появления — для строки фильтра
const FEED_TAGS = (() => {
  const seen = [];
  FEED.forEach((p) => (p.tags || []).forEach((t) => { if (!seen.includes(t)) seen.push(t); }));
  return seen;
})();

Object.assign(window, { PROFILE, FEED, GRID, PRACTICES, FEED_TAGS, TEACHERS });
