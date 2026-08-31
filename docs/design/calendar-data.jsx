// calendar-data.jsx — данные и хелперы для страницы «Календарь»
// Экспорт в window: CAL (хелперы), CAL_CLASSES, SEED_SADHANAS, SEED_TODOS, SADHANA_PRESETS, EMOJI_POOL

/* ── русская локализация дат ─────────────────────────────────────── */
const RU_MONTH_NOM = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
const RU_MONTH_GEN = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
const RU_WD_SHORT = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const RU_WD_MINI = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
const RU_WD_LONG = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];

const pad2 = (n) => String(n).padStart(2, '0');
const keyOf = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
const fromKey = (k) => { const [y, m, dd] = k.split('-').map(Number); return new Date(y, m - 1, dd); };
const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
const addMonths = (d, n) => { const x = new Date(d.getFullYear(), d.getMonth() + n, 1); return x; };
const monIdx = (d) => (d.getDay() + 6) % 7;            // Пн=0 … Вс=6
const startOfWeek = (d) => addDays(d, -monIdx(d));
const sameDay = (a, b) => keyOf(a) === keyOf(b);
const parseMin = (t) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
const fmtMin = (m) => `${pad2(Math.floor(m / 60))}:${pad2(m % 60)}`;
const daysBetween = (a, b) => Math.round((fromKey(keyOf(b)) - fromKey(keyOf(a))) / 86400000);

// «Понедельник, 8 июня»
const longLabel = (d) => `${RU_WD_LONG[monIdx(d)]}, ${d.getDate()} ${RU_MONTH_GEN[d.getMonth()]}`;
// «8 июня»
const dayMonth = (d) => `${d.getDate()} ${RU_MONTH_GEN[d.getMonth()]}`;
// относительная подпись для занятия: Сегодня / Завтра / Пн, 8 июня
const relDay = (d, today) => {
  const diff = daysBetween(today, d);
  if (diff === 0) return 'Сегодня';
  if (diff === 1) return 'Завтра';
  if (diff === -1) return 'Вчера';
  return `${RU_WD_SHORT[monIdx(d)]}, ${dayMonth(d)}`;
};

/* «сегодня» прототипа — фиксируем 8 июня 2026 (понедельник) */
const TODAY = new Date(2026, 5, 8);

/* ── сетка месяца: 42 ячейки c понедельника ─────────────────────── */
function monthMatrix(viewDate) {
  const first = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
  const start = startOfWeek(first);
  const cells = [];
  for (let i = 0; i < 42; i++) cells.push(addDays(start, i));
  return cells;
}
function weekDays(viewDate) {
  const start = startOfWeek(viewDate);
  return Array.from({ length: 7 }, (_, i) => addDays(start, i));
}

/* ── ПЕРИОДИЧНОСТЬ садханы ────────────────────────────────────────── */
// freq: 'daily' | 'weekdays' | 'weekly' | 'monthly'
//   s.days  — mondayIndex выбранных дней недели (для 'weekdays')
//   s.total — число повторений (вхождений), а не календарных дней
const weeksWord = (n) => { const a = Math.abs(n) % 100, b = n % 10; if (a >= 11 && a <= 14) return 'недель'; if (b === 1) return 'неделю'; if (b >= 2 && b <= 4) return 'недели'; return 'недель'; };
const monthsWord = (n) => { const a = Math.abs(n) % 100, b = n % 10; if (a >= 11 && a <= 14) return 'месяцев'; if (b === 1) return 'месяц'; if (b >= 2 && b <= 4) return 'месяца'; return 'месяцев'; };
const SAD_FREQ = {
  daily:    { id: 'daily',    chip: 'Каждый день',   short: 'ежедневно',   occ: (n, t) => `День ${n} из ${t}`,   spanLabel: 'Срок практики',   spanUnit: 'дн.',  presets: [40, 90, 120, 1000], reminder: 'Каждый день', hint: 'Классические сроки садханы — 40, 90, 120 и 1000 дней.' },
  weekdays: { id: 'weekdays', chip: 'По дням недели', short: '',           occ: (n, t) => `Раз ${n} из ${t}`,    spanLabel: 'Сколько недель',  spanUnit: 'нед.', presets: [4, 8, 12, 24],      reminder: 'По расписанию', hint: 'Практика в выбранные дни недели — 2–3 раза в неделю и т. д.' },
  weekly:   { id: 'weekly',   chip: 'Раз в неделю',  short: 'еженедельно', occ: (n, t) => `Раз ${n} из ${t}`,    spanLabel: 'Сколько раз',     spanUnit: 'раз',  presets: [4, 8, 12, 24],      reminder: 'Каждую неделю', hint: 'Через выбранный интервал недель — в день старта.', interval: true, intervalUnit: 'нед.', intervalWord: weeksWord, intervalPresets: [1, 2, 3, 4] },
  monthly:  { id: 'monthly',  chip: 'Раз в месяц',   short: 'ежемесячно',  occ: (n, t) => `Раз ${n} из ${t}`,    spanLabel: 'Сколько раз',     spanUnit: 'раз',  presets: [3, 6, 12, 24],      reminder: 'Каждый месяц', hint: 'Через выбранный интервал месяцев — в число старта.', interval: true, intervalUnit: 'мес.', intervalWord: monthsWord, intervalPresets: [1, 2, 3, 6] },
};
const sadEvery = (s) => Math.max(1, (s && s.every) || 1);
const sadFreqOf = (s) => SAD_FREQ[s && s.freq] || SAD_FREQ.daily;
const sadWeekdays = (s) => (s && s.days && s.days.length ? s.days : [monIdx(fromKey(s.start))]);
// «раз» с правильным окончанием: 1 раз · 2 раза · 5 раз
const razWord = (n) => { const a = Math.abs(n) % 100, b = n % 10; if (a >= 11 && a <= 14) return 'раз'; if (b >= 2 && b <= 4) return 'раза'; return 'раз'; };
const sadUnit = (s, n) => (sadFreqOf(s).id === 'daily' ? 'дн.' : razWord(n));

// все даты-вхождения практики (по возрастанию); cap ограничивает количество для рендера
function sadDates(s, cap) {
  const f = (s && s.freq) || 'daily';
  const start = fromKey(s.start);
  const n = Math.min(s.total || 0, cap || (s.total || 0));
  const out = [];
  const ev = sadEvery(s);
  if (f === 'weekly') {
    for (let i = 0; i < n; i++) out.push(addDays(start, i * 7 * ev));
  } else if (f === 'monthly') {
    for (let i = 0; i < n; i++) {
      const dt = new Date(start.getFullYear(), start.getMonth() + i * ev, 1);
      const dim = new Date(dt.getFullYear(), dt.getMonth() + 1, 0).getDate();
      dt.setDate(Math.min(start.getDate(), dim));
      out.push(dt);
    }
  } else if (f === 'weekdays') {
    const days = sadWeekdays(s);
    let d = new Date(start), guard = 0, max = (s.total || 0) * 10 + 90;
    while (out.length < n && guard++ < max) {
      if (days.includes(monIdx(d))) out.push(new Date(d));
      d = addDays(d, 1);
    }
  } else {
    for (let i = 0; i < n; i++) out.push(addDays(start, i));
  }
  return out;
}
// индекс вхождения для даты d (0-based) либо -1, если день вне расписания/срока
function sadIndexOf(s, d) {
  const f = (s && s.freq) || 'daily';
  const start = fromKey(s.start), total = s.total || 0;
  const dk = keyOf(d);
  if (keyOf(start) > dk) return -1;
  if (f === 'daily') { const n = daysBetween(start, d); return (n >= 0 && n < total) ? n : -1; }
  const ev = sadEvery(s);
  if (f === 'weekly') { const n = daysBetween(start, d); const step = 7 * ev; return (n >= 0 && n % step === 0 && n / step < total) ? n / step : -1; }
  if (f === 'monthly') {
    const months = (d.getFullYear() - start.getFullYear()) * 12 + (d.getMonth() - start.getMonth());
    if (months < 0 || months % ev !== 0 || months / ev >= total) return -1;
    const dim = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
    return d.getDate() === Math.min(start.getDate(), dim) ? months / ev : -1;
  }
  const days = sadWeekdays(s);
  if (!days.includes(monIdx(d))) return -1;
  let idx = -1, cur = new Date(start), guard = 0, max = total * 10 + 90;
  while (guard++ < max) {
    if (days.includes(monIdx(cur))) idx++;
    if (keyOf(cur) === dk) return idx < total ? idx : -1;
    if (keyOf(cur) > dk) break;
    cur = addDays(cur, 1);
  }
  return -1;
}
const sadActiveOn = (s, d) => sadIndexOf(s, d) >= 0;
// сколько вхождений запланировано на дату d включительно
function sadElapsed(s, d) {
  const dk = keyOf(d);
  let c = 0;
  for (const o of sadDates(s)) { if (keyOf(o) <= dk) c++; else break; }
  return Math.min(c, s.total || 0);
}
// человекочитаемая периодичность для подписи: «пн·ср·пт» или «еженедельно»
function sadFreqShort(s) {
  const f = (s && s.freq) || 'daily';
  const ev = sadEvery(s);
  if (f === 'weekdays') return sadWeekdays(s).map((i) => RU_WD_MINI[i]).join('·');
  if (f === 'weekly') return ev === 1 ? 'еженедельно' : `раз в ${ev} ${weeksWord(ev)}`;
  if (f === 'monthly') return ev === 1 ? 'ежемесячно' : `раз в ${ev} ${monthsWord(ev)}`;
  return sadFreqOf(s).short;
}
// человекочитаемый ярлык напоминания с учётом интервала
function sadReminderLabel(s) {
  const f = (s && s.freq) || 'daily';
  const ev = sadEvery(s);
  if (f === 'weekly') return ev === 1 ? 'Каждую неделю' : `Раз в ${ev} ${weeksWord(ev)}`;
  if (f === 'monthly') return ev === 1 ? 'Каждый месяц' : `Раз в ${ev} ${monthsWord(ev)}`;
  return sadFreqOf(s).reminder;
}

const CAL = {
  RU_MONTH_NOM, RU_MONTH_GEN, RU_WD_SHORT, RU_WD_MINI, RU_WD_LONG,
  pad2, keyOf, fromKey, addDays, addMonths, monIdx, startOfWeek, sameDay,
  parseMin, fmtMin, daysBetween, longLabel, dayMonth, relDay,
  TODAY, monthMatrix, weekDays,
  sadDates, sadIndexOf, sadActiveOn, sadElapsed, sadFreqShort, sadFreqOf, sadWeekdays, sadUnit, razWord,
  sadEvery, sadReminderLabel, weeksWord, monthsWord,
};

/* ── ЗАНЯТИЯ, на которые «вы записались» ─────────────────────────── */
// эмодзи/смысл занятия по типу:  studio→📍 · audio→🎧 · live→🔴 · else 🧘
function classGlyph(c) {
  if (c.place === 'studio') return '📍';
  if (c.live) return '🔴';
  if (c.kind === 'audio') return '🎧';
  return '🧘';
}

// повторяющиеся занятия в расписании (mondayIndex дней недели)
const RECUR = [
  { title: 'Утренняя виньяса', days: [0, 2, 4], time: '08:00', durMin: 60, kind: 'video', tone: 'a', live: true, teacher: 'Прия Дэви' },
  { title: 'Инь · вечерний поток', days: [1, 3], time: '19:30', durMin: 50, kind: 'video', tone: 'b', place: 'studio', teacher: 'Мира Деви', venue: 'Студия «Прана»' },
];
// разовые занятия
const ONEOFF = [
  { date: '2026-06-08', time: '12:00', title: 'Разбор асан · онлайн-эфир', durMin: 45, kind: 'video', tone: 'b', live: true, teacher: 'Прия Дэви' },
  { date: '2026-06-10', time: '21:30', title: 'Пранаяма перед сном', durMin: 20, kind: 'audio', tone: 'c', teacher: 'Джала Деви' },
  { date: '2026-06-13', time: '10:00', title: 'Открытый класс · Приветствие солнцу', durMin: 30, kind: 'video', tone: 'a', free: true, teacher: 'Прия Дэви' },
  { date: '2026-06-13', time: '11:30', title: 'Хатха-поток · студия', durMin: 75, kind: 'video', tone: 'a', place: 'studio', teacher: 'Сурья Дас', venue: 'Лофт «Лотос»' },
  { date: '2026-06-20', time: '10:00', title: 'Ретрит-день · Тишина', durMin: 240, kind: 'video', tone: 'c', place: 'studio', teacher: 'Прия Дэви', venue: 'Загородный дом' },
];

// генерим занятия в окне [25 мая … 12 июля 2026]
function buildClasses() {
  const out = [];
  const winStart = new Date(2026, 4, 25);
  const winEnd = new Date(2026, 6, 12);
  let n = 0;
  for (let d = new Date(winStart); d <= winEnd; d = addDays(d, 1)) {
    const wi = monIdx(d);
    RECUR.forEach((r) => {
      if (r.days.includes(wi)) {
        out.push({ id: `rc${n++}`, date: keyOf(d), time: r.time, durMin: r.durMin, title: r.title, kind: r.kind, tone: r.tone, live: r.live, place: r.place, teacher: r.teacher, venue: r.venue, recurring: true });
      }
    });
  }
  // курс «40 дней медитации» — с 15 июня, ежедневно 07:00, в пределах окна
  const courseStart = new Date(2026, 5, 15);
  for (let i = 0; i < 40; i++) {
    const d = addDays(courseStart, i);
    if (d > winEnd) break;
    out.push({ id: `med${i}`, date: keyOf(d), time: '07:00', durMin: 20, title: `Медитация · день ${i + 1}`, kind: 'audio', tone: 'c', course: true, teacher: 'Прия Дэви', recurring: true });
  }
  ONEOFF.forEach((o, i) => out.push({ id: `oo${i}`, ...o }));
  // финальные поля: startMin + glyph
  out.forEach((c) => { c.startMin = parseMin(c.time); c.endMin = c.startMin + c.durMin; c.glyph = classGlyph(c); });
  return out;
}
const CAL_CLASSES = buildClasses();

/* ── ЛИЧНЫЕ ПРАКТИКИ (садхана) ───────────────────────────────────── */
// длительности из вопросов
const SADHANA_PRESETS = [40, 90, 120, 1000];
const EMOJI_POOL = ['🔥', '💗', '🌬️', '🧘‍♀️', '🪷', '🌅', '🙏', '✨', '🌙', '💧', '🕉️', '🌀'];

// done — множество ключей дат, когда практика отмечена
function doneRange(startKey, count) {
  const out = {};
  const s = fromKey(startKey);
  for (let i = 0; i < count; i++) out[keyOf(addDays(s, i))] = true;
  return out;
}
const SEED_SADHANAS = [
  // началась 2 июня → сегодня (8 июня) идёт день 7; отмечены 2–7 июня (6 дней), сегодня ещё нет
  { id: 'sd1', name: 'Дыхание огня', emoji: '🔥', kind: 'physical', durMin: 11, total: 40, start: '2026-06-02', time: '07:00', tone: 'coral', reminder: true, done: doneRange('2026-06-02', 6) },
  // началась 25 мая → день 15; отмечены 25 мая–7 июня (14 дней) + сегодня уже отмечена
  { id: 'sd2', name: 'Медитация на сердце', emoji: '💗', kind: 'meditation', durMin: 31, total: 120, start: '2026-05-25', time: '21:00', tone: 'bubble', reminder: true, done: { ...doneRange('2026-05-25', 14), '2026-06-08': true } },
];

/* ── ДЕЛА ────────────────────────────────────────────────────────── */
// dated: { id, text, date, time, reminder, done }   ·  undated: { date:null }
const SEED_TODOS = [
  { id: 't1', text: 'Купить новый коврик для йоги', date: '2026-06-08', time: '09:30', reminder: true, done: false },
  { id: 't2', text: 'Позвонить Мире про майский ретрит', date: '2026-06-08', time: '18:00', reminder: false, done: false },
  { id: 't3', text: 'Оплатить курс «40 дней медитации»', date: '2026-06-09', time: '12:00', reminder: true, done: false },
  { id: 't4', text: 'Собрать плейлист для инь-класса', date: '2026-06-12', time: '16:00', reminder: false, done: false },
  { id: 't5', text: 'Утром выпить тёплой воды с лимоном', date: '2026-06-08', time: '07:30', reminder: true, done: true },
  // без даты — общий список
  { id: 'u1', text: 'Перечитать «Свет на йогу» Айенгара', date: null, time: null, reminder: false, done: false },
  { id: 'u2', text: 'Записать видео-приветствие для учениц', date: null, time: null, reminder: false, done: false },
  { id: 'u3', text: 'Обновить расписание занятий на июль', date: null, time: null, reminder: false, done: true },
];

// тип практики — для статистики
const PRACTICE_KIND = {
  meditation: { label: 'Медитация', short: 'Медитация', icon: '🧘', defaultEmoji: '🧘‍♀️' },
  physical: { label: 'Физическая', short: 'Крия · тело', icon: '🔥', defaultEmoji: '🔥' },
};
// пресеты длительности одной практики (минут)
const DUR_PRESETS = [11, 31, 45, 62];

Object.assign(window, { CAL, CAL_CLASSES, SEED_SADHANAS, SEED_TODOS, SADHANA_PRESETS, EMOJI_POOL, PRACTICE_KIND, DUR_PRESETS, SAD_FREQ });
