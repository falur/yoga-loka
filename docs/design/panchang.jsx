// panchang.jsx — ведический календарь (панчанга): титхи · накшатра · йога · карана,
//                фаза Луны и экадаши. Расчёт по упрощённой теории Луны (Schlyter),
//                привязан к восходу по IST (00:00 UT ≈ 05:30 IST). Точность ~0.05°.
// Экспорт в window: PANCHANG, MoonPhase, EKADASHI_2026

/* ── астрономия ─────────────────────────────────────────────────── */
const D2R = Math.PI / 180,R2D = 180 / Math.PI;
const rev = (x) => {x %= 360;return x < 0 ? x + 360 : x;};
const sind = (x) => Math.sin(x * D2R),cosd = (x) => Math.cos(x * D2R);

// день по Schlyter (эпоха 2000.0); UT в часах
function pDayNum(Y, M, D, UT) {
  return 367 * Y - Math.floor(7 * (Y + Math.floor((M + 9) / 12)) / 4) + Math.floor(275 * M / 9) + D - 730530 + UT / 24;
}
function pSun(d) {
  const w = 282.9404 + 4.70935e-5 * d,e = 0.016709 - 1.151e-9 * d,M = rev(356.0470 + 0.9856002585 * d);
  const E = M + e * R2D * sind(M) * (1 + e * cosd(M));
  const xv = cosd(E) - e,yv = Math.sqrt(1 - e * e) * sind(E);
  const v = rev(Math.atan2(yv, xv) * R2D);
  return { lon: rev(v + w), Ms: M, ws: w };
}
function pMoon(d) {
  const N = rev(125.1228 - 0.0529538083 * d),i = 5.1454,w = rev(318.0634 + 0.1643573223 * d);
  const e = 0.054900,M = rev(115.3654 + 13.0649929509 * d);
  let E = M + e * R2D * sind(M) * (1 + e * cosd(M));
  for (let k = 0; k < 3; k++) E = E - (E - e * R2D * sind(E) - M) / (1 - e * cosd(E));
  const xv = cosd(E) - e,yv = Math.sqrt(1 - e * e) * sind(E);
  const v = rev(Math.atan2(yv, xv) * R2D),r = Math.sqrt(xv * xv + yv * yv);
  const xh = r * (cosd(N) * cosd(v + w) - sind(N) * sind(v + w) * cosd(i));
  const yh = r * (sind(N) * cosd(v + w) + cosd(N) * sind(v + w) * cosd(i));
  return { lon: rev(Math.atan2(yh, xh) * R2D), Mm: M, Nm: N, wm: w };
}
// долгота Луны с основными возмущениями
function moonLonPerturbed(d) {
  const s = pSun(d),m = pMoon(d);
  const Ls = rev(s.Ms + s.ws),Lm = rev(m.Mm + m.wm + m.Nm);
  const Dm = rev(Lm - Ls),F = rev(Lm - m.Nm),Ms = s.Ms,Mm = m.Mm;
  let p = 0;
  p += -1.274 * sind(Mm - 2 * Dm);
  p += 0.658 * sind(2 * Dm);
  p += -0.186 * sind(Ms);
  p += -0.059 * sind(2 * Mm - 2 * Dm);
  p += -0.057 * sind(Mm - 2 * Dm + Ms);
  p += 0.053 * sind(Mm + 2 * Dm);
  p += 0.046 * sind(2 * Dm - Ms);
  p += 0.041 * sind(Mm - Ms);
  p += -0.035 * sind(Dm);
  p += -0.031 * sind(Mm + Ms);
  p += -0.015 * sind(2 * F - 2 * Dm);
  p += 0.011 * sind(Mm - 4 * Dm);
  return { moon: rev(m.lon + p), sun: s.lon };
}

/* ── названия ───────────────────────────────────────────────────── */
const TITHI = ['Пратипада', 'Двития', 'Тритья', 'Чатуртхи', 'Панчами', 'Шаштхи', 'Саптами', 'Аштами', 'Навами', 'Дашами', 'Экадаши', 'Двадаши', 'Трайодаши', 'Чатурдаши'];
const NAK = ['Ашвини', 'Бхарани', 'Криттика', 'Рохини', 'Мригашира', 'Ардра', 'Пунарвасу', 'Пушья', 'Ашлеша', 'Магха', 'Пурва Пхалгуни', 'Уттара Пхалгуни', 'Хаста', 'Читра', 'Свати', 'Вишакха', 'Анурадха', 'Джьештха', 'Мула', 'Пурва Ашадха', 'Уттара Ашадха', 'Шравана', 'Дхаништха', 'Шатабхиша', 'Пурва Бхадрапада', 'Уттара Бхадрапада', 'Ревати'];
const YOGA = ['Вишкамбха', 'Прити', 'Аюшман', 'Саубхагья', 'Шобхана', 'Атиганда', 'Сукарма', 'Дхрити', 'Шула', 'Ганда', 'Вриддхи', 'Дхрува', 'Вьягхата', 'Харшана', 'Ваджра', 'Сиддхи', 'Вьятипата', 'Вариян', 'Паригха', 'Шива', 'Сиддха', 'Садхья', 'Шубха', 'Шукла', 'Брахма', 'Индра', 'Вайдхрити'];
const KARANA_MOV = ['Бава', 'Балава', 'Каулава', 'Тайтила', 'Гара', 'Ваниджа', 'Вишти'];
const KARANA_FIX = ['Шакуни', 'Чатушпада', 'Нага'];

// фаза Луны → русское имя + ключ
function phaseName(illum, waxing) {
  if (illum < 0.035) return { key: 'new', ru: 'Новолуние', sym: '🌑' };
  if (illum > 0.965) return { key: 'full', ru: 'Полнолуние', sym: '🌕' };
  if (Math.abs(illum - 0.5) < 0.06) return waxing ? { key: 'fq', ru: 'Первая четверть', sym: '🌓' } : { key: 'lq', ru: 'Последняя четверть', sym: '🌗' };
  if (waxing) return illum < 0.5 ? { key: 'wxc', ru: 'Растущий серп', sym: '🌒' } : { key: 'wxg', ru: 'Растущая луна', sym: '🌔' };
  return illum > 0.5 ? { key: 'wng', ru: 'Убывающая луна', sym: '🌖' } : { key: 'wnc', ru: 'Убывающий серп', sym: '🌘' };
}

const AYANAMSA = 24.20; // Лахири ~2026
const TZ_OFF = 3; // часовой пояс отображения времён — МСК (UTC+3)

/* ── риту (сезон) — 6 ведических сезонов по солнцу (сидерически) ──── */
const RITU = [
  { ru: 'Васанта', sa: 'Vasanta', season: 'весна', emoji: '🌸', tone: 'bubble', months: 'Чайтра · Вайшакха', note: 'Время пробуждения и роста — мягкие, текучие практики и лёгкость в теле.' },
  { ru: 'Гришма', sa: 'Grīṣma', season: 'лето', emoji: '☀️', tone: 'sun', months: 'Джьештха · Ашадха', note: 'Жаркий сезон — охлаждающие пранаямы (ситали), вода, покой в полдень.' },
  { ru: 'Варша', sa: 'Varṣā', season: 'сезон дождей', emoji: '🌧️', tone: 'sky', months: 'Шравана · Бхадрапада', note: 'Сезон дождей — заземление, регулярность, тёплая и простая пища.' },
  { ru: 'Шарад', sa: 'Śarad', season: 'осень', emoji: '🍂', tone: 'leaf', months: 'Ашвина · Картика', note: 'Ясность и баланс — практики на очищение и успокоение питты.' },
  { ru: 'Хеманта', sa: 'Hemanta', season: 'предзимье', emoji: '🌫️', tone: 'grape', months: 'Маргаширша · Пауша', note: 'Сбор силы — насыщенные, согревающие практики, питательная еда.' },
  { ru: 'Шишира', sa: 'Śiśira', season: 'зима', emoji: '❄️', tone: 'mint', months: 'Магха · Пхалгуна', note: 'Глубокий покой — интроспекция, медитация, тепло и неспешность.' }
];

// имя караны по индексу 0..59
function karanaName(kIdx) {
  kIdx = ((kIdx % 60) + 60) % 60;
  if (kIdx === 0) return 'Кимстугхна';
  if (kIdx >= 57) return KARANA_FIX[kIdx - 57];
  return KARANA_MOV[(kIdx - 1) % 7];
}

/* ── главная функция: панчанга на дату (Date) ───────────────────── */
function panchangFor(date) {
  const Y = date.getFullYear(),M = date.getMonth() + 1,D = date.getDate();
  const { moon, sun } = moonLonPerturbed(pDayNum(Y, M, D, 0));
  const elong = rev(moon - sun);
  const illum = (1 - cosd(elong)) / 2;
  const waxing = elong < 180;
  const tIdx = Math.floor(elong / 12); // 0..29
  const paksha = tIdx < 15 ? 'shukla' : 'krishna';
  const tNum = tIdx % 15 + 1; // 1..15
  let tName;
  if (tNum === 15) tName = paksha === 'shukla' ? 'Пурнима' : 'Амавасья';else
  tName = TITHI[tNum - 1];
  const isEkadashi = tIdx === 10 || tIdx === 25;
  // накшатра (сидерическая долгота Луны)
  const sid = rev(moon - AYANAMSA);
  const nakIdx = Math.floor(sid / (360 / 27));
  const pada = Math.floor(sid % (360 / 27) / (360 / 108)) + 1;
  // йога
  const yogaIdx = Math.floor(rev(rev(sun - AYANAMSA) + sid) / (360 / 27)) % 27;
  // карана (полу-титхи)
  const kIdx = Math.floor(elong / 6); // 0..59
  let kName;
  if (kIdx === 0) kName = 'Кимстугхна';else
  if (kIdx >= 57) kName = KARANA_FIX[kIdx - 57];else
  kName = KARANA_MOV[(kIdx - 1) % 7];

  const ph = phaseName(illum, waxing);
  const key = `${Y}-${String(M).padStart(2, '0')}-${String(D).padStart(2, '0')}`;
  // риту (сезон) по сидерической долготе Солнца
  const sidSun = rev(sun - AYANAMSA);
  const rashiIdx = Math.floor(sidSun / 30);
  const ritu = RITU[Math.floor((rashiIdx + 1) % 12 / 2)];
  return {
    illum, waxing, phase: ph,
    tIdx, tNum, tName, paksha,
    pakshaName: paksha === 'shukla' ? 'Шукла пакша' : 'Кришна пакша',
    pakshaShort: paksha === 'shukla' ? 'Шукла' : 'Кришна',
    isEkadashi,
    nak: NAK[nakIdx], nakIdx, pada,
    yoga: YOGA[yogaIdx], yogaIdx,
    karana: kName, kIdx,
    ritu,
    ekadashi: isEkadashi ? EKADASHI_2026[key] || GENERIC_EKADASHI(paksha) : null,
    key
  };
}

/* ── времена начала/конца элементов панчанги ────────────────────── */
// мгновенное значение нужного угла при смещении utH часов от 00:00 UT даты
function _angleAt(Y, M, D, utH, which) {
  const { moon, sun } = moonLonPerturbed(pDayNum(Y, M, D, utH));
  if (which === 'tithi' || which === 'karana') return rev(moon - sun);
  const sidMoon = rev(moon - AYANAMSA);
  if (which === 'nak') return sidMoon;
  return rev(rev(sun - AYANAMSA) + sidMoon); // yoga
}
// бисекция: момент (в часах UT) пересечения границы шага step.  dir +1 — следующая, −1 — предыдущая
function _crossUT(Y, M, D, which, step, dir) {
  const v0 = _angleAt(Y, M, D, 0, which);
  const elapsed = (v0 % step + step) % step;
  if (dir > 0) {
    const remaining = step - elapsed;
    let lo = 0, hi = 48;
    for (let i = 0; i < 60; i++) { const m = (lo + hi) / 2; (rev(_angleAt(Y, M, D, m, which) - v0) < remaining) ? lo = m : hi = m; }
    return (lo + hi) / 2;
  }
  let lo = -48, hi = 0;
  for (let i = 0; i < 60; i++) { const m = (lo + hi) / 2; (rev(v0 - _angleAt(Y, M, D, m, which)) > elapsed) ? lo = m : hi = m; }
  return (lo + hi) / 2;
}
// часы UT → {hm:'HH:MM', dayOff, d, mo} в поясе отображения
function _clock(Y, M, D, utH) {
  const dt = new Date(Date.UTC(Y, M - 1, D, 0, 0, 0) + (utH + TZ_OFF) * 3600000);
  return {
    hm: `${String(dt.getUTCHours()).padStart(2, '0')}:${String(dt.getUTCMinutes()).padStart(2, '0')}`,
    d: dt.getUTCDate(), mo: dt.getUTCMonth(),
    dayOff: Math.round((Date.UTC(dt.getUTCFullYear(), dt.getUTCMonth(), dt.getUTCDate()) - Date.UTC(Y, M - 1, D)) / 86400000)
  };
}
// полные времена для титхи/накшатры/йоги/караны на дату
function panchangTimes(date) {
  const Y = date.getFullYear(), M = date.getMonth() + 1, D = date.getDate();
  const b = panchangFor(date);
  const N = 360 / 27;
  const span = (which, step) => ({ begin: _clock(Y, M, D, _crossUT(Y, M, D, which, step, -1)), end: _clock(Y, M, D, _crossUT(Y, M, D, which, step, 1)) });
  // следующая титхи
  const nti = (b.tIdx + 1) % 30, npk = nti < 15 ? 'shukla' : 'krishna', nnum = nti % 15 + 1;
  const nextTithi = nnum === 15 ? (npk === 'shukla' ? 'Пурнима' : 'Амавасья') : TITHI[nnum - 1];
  return {
    tithi: { ...span('tithi', 12), cur: b.tName, next: nextTithi },
    nak: { ...span('nak', N), cur: b.nak, next: NAK[(b.nakIdx + 1) % 27] },
    yoga: { ...span('yoga', N), cur: b.yoga, next: YOGA[(b.yogaIdx + 1) % 27] },
    karana: { ...span('karana', 6), cur: b.karana, next: karanaName(b.kIdx + 1) }
  };
}


const GENERIC_EKADASHI = (paksha) => ({
  ru: 'Экадаши', sa: 'Ekādaśī', paksha,
  desc: 'Одиннадцатый лунный день каждой половины месяца, посвящённый Вишну. День поста, тишины и духовной практики — очищение тела и ума.'
});
const EKADASHI_2026 = {
  '2026-01-14': { ru: 'Шаттила', sa: 'Ṣaṭtilā', desc: 'Кришна-экадаши месяца Магха. Связана с использованием кунжута (тила) шестью способами. Очищает от грехов, дарует достаток и здоровье.' },
  '2026-01-29': { ru: 'Джая', sa: 'Jayā', desc: 'Шукла-экадаши Магхи. «Победа» — освобождает от состояния призраков и тонких страданий, дарует чистоту и духовную силу.' },
  '2026-02-13': { ru: 'Виджая', sa: 'Vijayā', desc: 'Кришна-экадаши Пхалгуны. «Триумф» — её соблюдал Рама перед переправой на Ланку. Дарует успех в трудных начинаниях.' },
  '2026-02-27': { ru: 'Амалаки', sa: 'Āmalakī', desc: 'Шукла-экадаши Пхалгуны. Почитается дерево амалаки (мироболан), в котором пребывает Вишну. Дарует здоровье и долголетие.' },
  '2026-03-15': { ru: 'Папамочани', sa: 'Pāpamocanī', desc: 'Кришна-экадаши Чайтры. «Освобождающая от грехов» — завершает весенний цикл, смывает дурные поступки и наваждения.' },
  '2026-03-29': { ru: 'Камада', sa: 'Kāmadā', desc: 'Шукла-экадаши Чайтры, первая в лунном году. «Исполняющая желания» — устраняет проклятия и дарует благополучие.' },
  '2026-04-13': { ru: 'Варутхини', sa: 'Varūthinī', desc: 'Кришна-экадаши Вайшакхи. Дарует защиту и удачу, ведёт к освобождению; пост на неё равен великим пожертвованиям.' },
  '2026-04-27': { ru: 'Мохини', sa: 'Mohinī', desc: 'Шукла-экадаши Вайшакхи. Названа в честь Мохини — женского облика Вишну. Освобождает от иллюзии и привязанностей.' },
  '2026-05-13': { ru: 'Апара', sa: 'Aparā', desc: 'Кришна-экадаши Джьештхи. «Безграничная» — дарует огромную заслугу, славу и очищение от тяжких грехов.' },
  '2026-05-27': { ru: 'Падмини', sa: 'Padminī', desc: 'Шукла-экадаши добавочного месяца (Адхика-маса). Редкая экадаши високосного месяца, особенно благодатна и приравнивается ко всем экадаши года.' },
  '2026-06-11': { ru: 'Парама', sa: 'Paramā', desc: 'Кришна-экадаши добавочного месяца (Адхика-маса). «Высшая» — дарует исполнение желаний, процветание и освобождение от глубоких препятствий.' },
  '2026-06-25': { ru: 'Нирджала', sa: 'Nirjalā', desc: 'Шукла-экадаши Джьештхи — самый строгий пост, без еды и воды. Также Бхима-экадаши: одна несёт заслугу всех 24 экадаши года.' },
  '2026-07-10': { ru: 'Йогини', sa: 'Yoginī', desc: 'Кришна-экадаши Ашадхи. Очищает от грехов и недугов, исцеляет тело и ум, дарует благоденствие.' },
  '2026-07-25': { ru: 'Дэвшаяни', sa: 'Devaśayanī', desc: 'Шукла-экадаши Ашадхи. Вишну уходит в йога-нидру — начинается Чатурмас, четыре месяца усиленной практики и воздержания.' },
  '2026-08-09': { ru: 'Камика', sa: 'Kāmikā', desc: 'Кришна-экадаши Шраваны. Поклонение Вишну и туласи смывает грехи; считается равной паломничеству по святым местам.' },
  '2026-08-23': { ru: 'Путрада (Шравана)', sa: 'Pavitrā', desc: 'Шукла-экадаши Шраваны. «Дарующая потомство и чистоту» — благословляет семью, очищает карму рода.' },
  '2026-09-07': { ru: 'Аджа', sa: 'Ajā', desc: 'Кришна-экадаши Бхадрапады. Смывает тягчайшие грехи, восстанавливает утраченное; её соблюдал царь Харишчандра.' },
  '2026-09-22': { ru: 'Паривартини', sa: 'Parivartinī', desc: 'Шукла-экадаши Бхадрапады. Вишну поворачивается во сне на другой бок — поворотная точка Чатурмаса.' },
  '2026-10-06': { ru: 'Индира', sa: 'Indirā', desc: 'Кришна-экадаши Ашвина (Питру-пакша). Заслуга поста посвящается предкам и помогает им обрести лучшую участь.' },
  '2026-10-22': { ru: 'Папанкуша', sa: 'Pāpāṅkuśā', desc: 'Шукла-экадаши Ашвина. «Стрекало против грехов» — дарует здоровье, благополучие и путь к освобождению.' },
  '2026-11-05': { ru: 'Рама', sa: 'Ramā', desc: 'Кришна-экадаши Картики, перед Дивали. Посвящена Лакшми (Рама); устраняет грехи и дарует изобилие.' },
  '2026-11-20': { ru: 'Дэвутхана', sa: 'Devotthānā', desc: 'Шукла-экадаши Картики. Вишну пробуждается ото сна — завершается Чатурмас, возобновляются свадьбы и торжества.' },
  '2026-12-04': { ru: 'Утпанна', sa: 'Utpannā', desc: 'Кришна-экадаши Маргаширши. День явления богини Экадаши из тела Вишну — начало цикла экадаши-врат.' },
  '2026-12-20': { ru: 'Мокшада', sa: 'Mokṣadā', desc: 'Шукла-экадаши Маргаширши. Совпадает с Гита-джаянти и Вайкунтха-экадаши. «Дарующая мокшу» — освобождает и душу, и предков.' }
};

/* ── ближайшие экадаши от даты (для списка в шторке) ─────────────── */
function upcomingEkadashi(fromDate, count) {
  const out = [];
  const keys = Object.keys(EKADASHI_2026).sort();
  const fk = `${fromDate.getFullYear()}-${String(fromDate.getMonth() + 1).padStart(2, '0')}-${String(fromDate.getDate()).padStart(2, '0')}`;
  for (const k of keys) {
    if (k >= fk) {
      const [y, m, d] = k.split('-').map(Number);
      const dt = new Date(y, m - 1, d);
      const pk = panchangFor(dt).paksha;
      out.push({ key: k, date: dt, paksha: pk, ...EKADASHI_2026[k] });
    }
    if (out.length >= count) break;
  }
  return out;
}

/* ── SVG-фаза Луны ──────────────────────────────────────────────── */
function moonPath(R, illum, waxing) {
  illum = Math.max(0, Math.min(1, illum));
  const rx = R * Math.abs(1 - 2 * illum);
  const limbSweep = waxing ? 1 : 0;
  const gibbous = illum > 0.5;
  const termSweep = gibbous ? limbSweep : 1 - limbSweep;
  return `M0,${-R} A${R},${R} 0 0 ${limbSweep} 0,${R} A${rx.toFixed(3)},${R} 0 0 ${termSweep} 0,${-R} Z`;
}
// props: size, illum, waxing, lit, dark, line, glow(bool), strokeW
function MoonPhase({ size = 40, illum = 0.5, waxing = true, lit = 'var(--moon-lit)', dark = 'var(--moon-dark)', line = 'var(--moon-line)', strokeW = 1, style }) {
  const R = 50,vb = 120;
  return (
    <svg width={size} height={size} viewBox={`${-vb / 2} ${-vb / 2} ${vb} ${vb}`} style={style} aria-hidden="true">
      <circle cx="0" cy="0" r={R} fill={dark} stroke={line} strokeWidth={strokeW} style={{ opacity: "0.4" }} />
      {illum > 0.012 && <path d={moonPath(R, illum, waxing)} fill={lit} />}
    </svg>);

}

window.PANCHANG = { for: panchangFor, times: panchangTimes, upcomingEkadashi, moonPath, TITHI, NAK, YOGA, RITU, karanaName };
window.MoonPhase = MoonPhase;
window.EKADASHI_2026 = EKADASHI_2026;