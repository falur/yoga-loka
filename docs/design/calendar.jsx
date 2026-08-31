// calendar.jsx — страница «Календарь»: виды месяц/неделя/день, занятия, садханы, дела
// Экспорт в window: CalendarPage

const { useState: uCal, useRef: uRef } = React;
const C = window.CAL;
const { POP: kPOP, TINT: kTINT } = window;
const KLEAF = 'var(--c-leaf)';

/* ── производные по садхане ─────────────────────────────────────── */
function sadStat(s, today) {
  const doneCount = Object.values(s.done).filter(Boolean).length;
  // стрик: подряд отмеченные вхождения, заканчивая последним прошедшим (сегодня не рвёт)
  const dates = C.sadDates(s);
  let streak = 0;
  for (let i = dates.length - 1; i >= 0; i--) {
    const o = dates[i];
    if (o > today) continue;
    if (s.done[C.keyOf(o)]) streak++;
    else if (C.keyOf(o) === C.keyOf(today)) continue; // сегодня ещё можно отметить
    else break;
  }
  return { dayNum: Math.max(1, C.sadElapsed(s, today)), doneCount, pct: Math.round((doneCount / s.total) * 100), streak };
}
const activeOn = (s, d) => C.sadActiveOn(s, d);

/* ── маленькая кнопка-колокольчик ───────────────────────────────── */
function BellBtn({ on, color, onClick }) {
  return (
    <button className="tap pbtn" onClick={onClick} aria-label="Напоминание" style={{
      width: 34, height: 34, borderRadius: 10, border: 'none', cursor: 'pointer', flexShrink: 0,
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      background: on ? (color === kPOP.sky ? kTINT.sky : kTINT.leaf) : 'var(--p-ctrl)',
    }}>
      <IconBell size={17} fill={on ? color : 'none'} style={{ color: on ? color : 'var(--p-faint)' }} />
    </button>
  );
}
/* чек-кружок */
function Checker({ done, color, onClick }) {
  return (
    <button className="tap pbtn" onClick={onClick} aria-label="Отметить" style={{
      width: 27, height: 27, borderRadius: '50%', flexShrink: 0, cursor: 'pointer',
      border: done ? 'none' : '2px solid var(--p-border)', background: done ? color : 'transparent',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center', transition: 'all .14s ease',
    }}>
      {done && <IconCheck size={16} style={{ color: '#fff' }} />}
    </button>
  );
}

/* ── строки агенды ──────────────────────────────────────────────── */
function ClassRow({ c, onOpen }) {
  const col = window.classColor(c);
  return (
    <button className="tap pbtn" onClick={() => onOpen(c)} style={{
      display: 'flex', alignItems: 'center', gap: 11, width: '100%', textAlign: 'left',
      border: 'none', cursor: 'pointer', background: 'var(--p-elev)', borderRadius: 16, padding: '11px 12px',
      boxShadow: '0 1px 0 var(--p-border)',
    }}>
      <span style={{ width: 4, alignSelf: 'stretch', borderRadius: 999, background: col.pop, flexShrink: 0 }} />
      <span style={{ width: 40, height: 40, borderRadius: 12, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 20, background: col.tint }}>{c.glyph}</span>
      <span style={{ flex: 1, minWidth: 0 }}>
        <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.title}</span>
        <span style={{ display: 'block', fontSize: 12, fontWeight: 700, color: col.pop, marginTop: 2 }}>{c.time}–{C.fmtMin(c.endMin)} · {c.teacher}</span>
      </span>
      {c.reminder !== false && <IconBell size={15} fill="var(--p-faint)" style={{ color: 'var(--p-faint)', flexShrink: 0 }} />}
      <IconChevron size={16} style={{ color: 'var(--p-faint)', flexShrink: 0 }} />
    </button>
  );
}

function SadhanaDayRow({ s, dayKey, dayNum, onToggle, onOpen }) {
  const pop = kPOP[s.tone] || kPOP.coral;
  const tint = kTINT[s.tone] || kTINT.coral;
  const done = !!s.done[dayKey];
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 11, background: 'var(--p-elev)', borderRadius: 16, padding: '10px 12px', boxShadow: '0 1px 0 var(--p-border)' }}>
      <span className="tap" onClick={() => onOpen(s)} style={{ width: 40, height: 40, borderRadius: 12, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 20, background: tint, cursor: 'pointer' }}>{s.emoji}</span>
      <span className="tap" onClick={() => onOpen(s)} style={{ flex: 1, minWidth: 0, cursor: 'pointer' }}>
        <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.name}</span>
        <span style={{ display: 'block', fontSize: 12, fontWeight: 700, color: pop, marginTop: 2 }}>🪷 {C.sadFreqOf(s).occ(dayNum, s.total)}{s.durMin ? ` · ${s.durMin} мин` : ''}</span>
      </span>
      <Checker done={done} color={pop} onClick={() => onToggle(s, dayKey)} />
    </div>
  );
}

function TodoRow({ t, onToggle, onMenu, onBell }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 11, background: 'var(--p-elev)', borderRadius: 16, padding: '10px 12px', boxShadow: '0 1px 0 var(--p-border)' }}>
      <Checker done={t.done} color={kPOP.sky} onClick={() => onToggle(t)} />
      <span className="tap" onClick={() => onMenu(t)} style={{ flex: 1, minWidth: 0, cursor: 'pointer' }}>
        <span style={{ display: 'block', fontSize: 14.5, fontWeight: 600, color: t.done ? 'var(--p-faint)' : 'var(--p-ink)', textDecoration: t.done ? 'line-through' : 'none', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.text}</span>
        {t.time && <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 11.5, fontWeight: 700, color: kPOP.sky, marginTop: 3 }}><IconClock size={12} />{t.time}</span>}
      </span>
      {t.date && <BellBtn on={t.reminder} color={kPOP.sky} onClick={() => onBell(t)} />}
    </div>
  );
}

const sectionHead = (icon, title, count, color) => (
  <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '0 0 10px' }}>
    <span style={{ fontSize: 15 }}>{icon}</span>
    <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, color: 'var(--p-ink)' }}>{title}</span>
    {count != null && <span style={{ fontSize: 11.5, fontWeight: 800, color: color, background: 'var(--p-ctrl)', borderRadius: 999, padding: '2px 8px' }}>{count}</span>}
  </div>
);

const subHead = (title, count, color) => (
  <div style={{ display: 'flex', alignItems: 'center', gap: 7, margin: '0 0 8px', paddingLeft: 2 }}>
    <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12, letterSpacing: 0.4, textTransform: 'uppercase', color: 'var(--p-faint)' }}>{title}</span>
    <span style={{ flex: 1, height: 1, background: 'var(--p-border)' }} />
    {count != null && count > 0 && <span style={{ fontSize: 11, fontWeight: 800, color: color }}>{count}</span>}
  </div>
);

/* ── нижняя навигация (активен «Календарь») ─────────────────────── */
const CAL_NAV = [
  { key: 'practice', label: 'Моя практика', emoji: '🌀', pop: '#E8615A', soft: 'rgba(232,97,90,0.16)' },
  { key: 'classes', label: 'Занятия', emoji: '🧘', pop: '#E8902F', soft: 'rgba(232,144,47,0.16)' },
  { key: 'calendar', label: 'Календарь', emoji: '📅', pop: '#4FA85B', soft: 'rgba(79,168,91,0.16)' },
  { key: 'sangat', label: 'Сангат', emoji: '❤️', pop: '#3E92D8', soft: 'rgba(62,146,216,0.16)' },
  { key: 'ahamkara', label: 'Ахамкара', emoji: '🪬', pop: '#8E55D8', soft: 'rgba(142,85,216,0.16)' },
];
function CalBottomNav({ active: activeProp, onNav } = {}) {
  const [activeState, setActiveState] = uCal('calendar');
  const active = activeProp != null ? activeProp : activeState;
  const setActive = onNav || setActiveState;
  return (
    <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderTop: '1px solid var(--p-border)', display: 'flex', alignItems: 'stretch', justifyContent: 'space-around', padding: '8px 4px 24px' }}>
      {CAL_NAV.map(({ key, label, emoji, pop, soft }) => {
        const on = active === key;
        return (
          <button key={key} className="tap pbtn" onClick={() => setActive(key)} style={{ flex: 1, minWidth: 0, border: 'none', background: 'transparent', cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 5, padding: '2px 1px' }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 36, height: 36, borderRadius: '50%', backgroundColor: on ? soft : 'transparent', transform: on ? 'translateY(-1px)' : 'none', transition: 'background .18s ease, transform .18s ease' }}>
              <span style={{ fontSize: 19, lineHeight: 1, filter: on ? 'none' : 'saturate(0.8) opacity(0.6)' }}>{emoji}</span>
            </span>
            <span style={{ fontFamily: "'Nunito Sans', sans-serif", fontSize: 9.5, lineHeight: 1, letterSpacing: '-0.1px', fontWeight: on ? 800 : 600, whiteSpace: 'nowrap', color: on ? pop : 'var(--p-mute)', transition: 'color .18s ease' }}>{label}</span>
          </button>
        );
      })}
    </div>
  );
}

/* ── шапка периода + навигация ──────────────────────────────────── */
function PeriodHeader({ view, cur, onPrev, onNext, onToday, isToday }) {
  let label;
  if (view === 'month') label = `${C.RU_MONTH_NOM[cur.getMonth()]} ${cur.getFullYear()}`;
  else if (view === 'day') label = C.longLabel(cur);
  else {
    const s = C.startOfWeek(cur), e = C.addDays(s, 6);
    label = s.getMonth() === e.getMonth()
      ? `${s.getDate()}–${e.getDate()} ${C.RU_MONTH_GEN[s.getMonth()]}`
      : `${s.getDate()} ${C.RU_MONTH_GEN[s.getMonth()]} – ${e.getDate()} ${C.RU_MONTH_GEN[e.getMonth()]}`;
  }
  const arrow = (dir) => (
    <button className="picon pbtn" onClick={dir < 0 ? onPrev : onNext} style={{ width: 34, height: 34, borderRadius: 11, background: 'var(--p-ctrl)' }}>
      <IconChevron size={17} style={{ color: 'var(--p-soft)', transform: dir < 0 ? 'rotate(180deg)' : 'none' }} />
    </button>
  );
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '4px 14px 10px' }}>
      <div style={{ flex: 1, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', letterSpacing: '-0.01em' }}>{label}</div>
      {!isToday && (
        <button className="tap pbtn" onClick={onToday} style={{ border: 'none', cursor: 'pointer', background: kTINT.leaf, color: kPOP.leaf, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12.5, padding: '7px 12px', borderRadius: 999 }}>К сегодня</button>
      )}
      {arrow(-1)}
      {arrow(1)}
    </div>
  );
}

/* ── главный компонент ──────────────────────────────────────────── */
function CalendarPage({ dark = false, nav: appNav }) {
  const today = C.TODAY;
  const [view, setView] = uCal('month');
  const [cur, setCur] = uCal(() => new Date(today));
  const [classes, setClasses] = uCal(() => window.CAL_CLASSES.map((c) => ({ ...c, reminder: true })));
  const [sadhanas, setSadhanas] = uCal(() => JSON.parse(JSON.stringify(window.SEED_SADHANAS)));
  const [todos, setTodos] = uCal(() => JSON.parse(JSON.stringify(window.SEED_TODOS)));
  const [create, setCreate] = uCal(null);       // 'menu' | 'todo' | 'practice'
  const [editTodo, setEditTodo] = uCal(null);    // todo для редактирования
  const [openClass, setOpenClass] = uCal(null);
  const [openSad, setOpenSad] = uCal(null);      // id садханы
  const [menuTodo, setMenuTodo] = uCal(null);
  const [openEka, setOpenEka] = uCal(false);    // шторка экадаши
  const [openPan, setOpenPan] = uCal(false);    // шторка панчанги (детали дня)
  const [toast, setToast] = uCal(null);
  const rootRef = uRef(null);
  const tref = uRef(0);

  const ping = (m) => { setToast(m); clearTimeout(tref.current); tref.current = setTimeout(() => setToast(null), 1900); };
  React.useEffect(() => () => clearTimeout(tref.current), []);

  /* навигация */
  const nav = (dir) => {
    if (view === 'month') {
      const d = new Date(cur); const day = d.getDate(); d.setDate(1); d.setMonth(d.getMonth() + dir);
      const dim = new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate(); d.setDate(Math.min(day, dim)); setCur(d);
    } else if (view === 'week') setCur(C.addDays(cur, dir * 7));
    else setCur(C.addDays(cur, dir));
  };
  const goToday = () => { setCur(new Date(today)); setOpenEka(false); setOpenPan(false); };
  const isToday = C.sameDay(cur, today);
  const selectDay = (d) => { setCur(d); setOpenEka(false); setOpenPan(false); };
  const curPan = window.PANCHANG.for(cur);

  /* мутации */
  const cancelClass = (c) => { setClasses((xs) => xs.filter((x) => x.id !== c.id)); setOpenClass(null); ping('Запись отменена · занятие убрано'); };
  const toggleClassRem = (c) => setClasses((xs) => xs.map((x) => x.id === c.id ? { ...x, reminder: x.reminder === false } : x));
  const setClassLead = (c, lead) => setClasses((xs) => xs.map((x) => x.id === c.id ? { ...x, lead } : x));

  const toggleSad = (s, dayKey) => setSadhanas((xs) => xs.map((x) => {
    if (x.id !== s.id) return x; const nd = { ...x.done }; if (nd[dayKey]) delete nd[dayKey]; else { nd[dayKey] = true; ping('✓ День отмечен — так держать ✨'); } return { ...x, done: nd };
  }));
  const markToday = (s) => toggleSad(s, C.keyOf(today));
  const toggleSadRem = (s) => setSadhanas((xs) => xs.map((x) => x.id === s.id ? { ...x, reminder: !x.reminder } : x));
  const setSadLead = (s, lead) => setSadhanas((xs) => xs.map((x) => x.id === s.id ? { ...x, lead } : x));
  const deleteSad = (s) => { setSadhanas((xs) => xs.filter((x) => x.id !== s.id)); setOpenSad(null); ping('Практика завершена'); };
  const addSad = (s, mode) => { setSadhanas((xs) => [...xs, s]); setCreate(null); ping(mode === 'once' ? '✓ Практика записана · в статистике' : '🪷 Практика начата — день 1'); };

  const toggleTodo = (t) => setTodos((xs) => xs.map((x) => x.id === t.id ? { ...x, done: !x.done } : x));
  const bellTodo = (t) => setTodos((xs) => xs.map((x) => x.id === t.id ? { ...x, reminder: !x.reminder } : x));
  const saveTodo = (t) => { setTodos((xs) => xs.some((x) => x.id === t.id) ? xs.map((x) => x.id === t.id ? t : x) : [...xs, t]); setCreate(null); setEditTodo(null); ping(t.date ? '✓ Дело добавлено в календарь' : '✓ Дело в списке'); };
  const delTodo = (t) => { setTodos((xs) => xs.filter((x) => x.id !== t.id)); setMenuTodo(null); ping('Дело удалено'); };

  /* данные выбранного дня */
  const curKey = C.keyOf(cur);
  const dayClasses = classes.filter((c) => c.date === curKey).sort((a, b) => a.startMin - b.startMin);
  const dayTodos = todos.filter((t) => t.date === curKey).sort((a, b) => (a.time || '').localeCompare(b.time || ''));
  const daySad = sadhanas.filter((s) => activeOn(s, cur));
  const undated = todos.filter((t) => !t.date);
  const undatedOpen = undated.filter((t) => !t.done).length;
  const showClasses = view !== 'day';
  const agendaEmpty = (showClasses ? dayClasses.length === 0 : true) && daySad.length === 0;
  const todayKey = C.keyOf(today);
  const todayTodos = todos.filter((t) => t.date === todayKey).sort((a, b) => (a.time || '').localeCompare(b.time || ''));
  const todayOpen = todayTodos.filter((t) => !t.done).length;

  const host = rootRef.current && rootRef.current.classList.contains('ylp') ? rootRef.current : null;

  const VIEWS = [['month', 'Месяц'], ['week', 'Неделя'], ['day', 'День']];

  return (
    <div ref={rootRef} className={'ylp' + (dark ? ' dark' : '')}>
      {/* top bar */}
      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)', padding: '48px 14px 11px', display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 7, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 20, color: 'var(--p-ink)' }}>📅 Календарь</span>
        <button className="picon pbtn" onClick={() => setCreate('menu')} style={{ backgroundColor: kTINT.leaf }}>
          <IconPlus size={20} style={{ color: kPOP.leaf }} />
        </button>
      </div>

      <div className="pscroll">
        {/* распорка под фиксированную шапку */}
        <div style={{ height: 96 }} />
        {/* переключатель видов — прокручивается вместе с контентом */}
        <div style={{ background: 'var(--p-bg)', padding: '10px 14px 8px' }}>
          <div style={{ display: 'flex', gap: 6, background: 'var(--p-ctrl)', borderRadius: 14, padding: 4 }}>
            {VIEWS.map(([id, label]) => {
              const on = view === id;
              return (
                <button key={id} className="pbtn" onClick={() => { setView(id); }} style={{
                  flex: 1, height: 40, border: 'none', cursor: 'pointer', borderRadius: 11,
                  fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14,
                  background: on ? 'var(--p-elev)' : 'transparent', color: on ? kPOP.leaf : 'var(--p-mute)',
                  boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none', transition: 'all .15s ease',
                }}>{label}</button>
              );
            })}
          </div>
        </div>

        <PeriodHeader view={view} cur={cur} onPrev={() => nav(-1)} onNext={() => nav(1)} onToday={goToday} isToday={isToday} />

        {/* ведическая шапка — панчанга выбранного дня */}
        <window.PanchangBar date={cur} onOpenEkadashi={() => setOpenEka(true)} onOpenDetail={() => setOpenPan(true)} />

        {/* виджет календаря */}
        {view === 'month' && <window.MonthGrid viewDate={cur} selected={cur} today={today} onSelect={selectDay} classes={classes} todos={todos} />}
        {view === 'week' && <window.WeekGrid viewDate={cur} selected={cur} today={today} onSelect={selectDay} classes={classes} onOpenClass={setOpenClass} />}
        {view === 'day' && <window.DayTimeline selected={cur} today={today} classes={classes} onOpenClass={setOpenClass} />}

        <div style={{ height: 1, background: 'var(--p-border)', margin: '14px 14px 0' }} />

        {/* агенда выбранного дня */}
        <div style={{ padding: '16px 14px 6px' }}>
          <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 13 }}>
            <div>
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)' }}>{C.relDay(cur, today)}</div>
              <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--p-faint)', marginTop: 1 }}>{C.dayMonth(cur)}, {C.RU_WD_LONG[C.monIdx(cur)].toLowerCase()}</div>
            </div>
            <button className="tap pbtn" onClick={() => setCreate('menu')} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, border: 'none', cursor: 'pointer', background: kTINT.leaf, color: kPOP.leaf, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13, padding: '8px 13px', borderRadius: 999 }}><IconPlus size={15} style={{ strokeWidth: 2.4 }} />Добавить</button>
          </div>

          {agendaEmpty && (
            <div style={{ textAlign: 'center', padding: '20px 24px', background: 'var(--p-ctrl)', borderRadius: 18 }}>
              <div style={{ fontSize: 30 }}>🧘‍♀️</div>
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15, color: 'var(--p-ink)', marginTop: 6 }}>Пока пусто</div>
              <div style={{ fontSize: 12.5, color: 'var(--p-mute)', marginTop: 3, lineHeight: 1.5 }}>Нет занятий, практик и дел на этот день. Добавьте что-нибудь ✨</div>
            </div>
          )}

          {showClasses && dayClasses.length > 0 && (
            <div style={{ marginBottom: 16 }}>
              {sectionHead('🧘', 'Занятия', dayClasses.length, kPOP.coral)}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {dayClasses.map((c) => <ClassRow key={c.id} c={c} onOpen={setOpenClass} />)}
              </div>
            </div>
          )}

          {daySad.length > 0 && (
            <div style={{ marginBottom: 16 }}>
              {sectionHead('🪷', 'Мои практики', daySad.length, kPOP.leaf)}
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {daySad.map((s) => <SadhanaDayRow key={s.id} s={s} dayKey={curKey} dayNum={C.sadIndexOf(s, cur) + 1} onToggle={toggleSad} onOpen={(x) => setOpenSad(x.id)} />)}
              </div>
            </div>
          )}

        </div>

        {/* список дел — на сегодня + общие */}
        <div style={{ padding: '8px 14px 2px' }}>
          {sectionHead('🗒️', 'Список дел', todayOpen + undatedOpen, kPOP.sky)}

          {/* на сегодня */}
          <div style={{ marginBottom: 16 }}>
            {subHead('На сегодня', todayOpen, kPOP.sky)}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {todayTodos.map((t) => <TodoRow key={t.id} t={t} onToggle={toggleTodo} onMenu={setMenuTodo} onBell={bellTodo} />)}
              <button className="tap pbtn" onClick={() => { setEditTodo({ id: 'td' + Date.now(), text: '', date: todayKey, time: null, reminder: false, done: false, _new: true }); setCreate('todo'); }} style={{ display: 'flex', alignItems: 'center', gap: 11, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', borderRadius: 16, padding: '12px', color: 'var(--p-mute)' }}>
                <span style={{ width: 27, height: 27, borderRadius: '50%', border: '2px dashed var(--p-border)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><IconPlus size={15} style={{ color: 'var(--p-faint)' }} /></span>
                <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-soft)' }}>Дело на сегодня</span>
              </button>
            </div>
          </div>

          {/* общие */}
          <div>
            {subHead('Общие', undatedOpen, kPOP.sky)}
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {undated.map((t) => <TodoRow key={t.id} t={t} onToggle={toggleTodo} onMenu={setMenuTodo} onBell={bellTodo} />)}
              <button className="tap pbtn" onClick={() => { setEditTodo({ id: 'td' + Date.now(), text: '', date: null, time: null, reminder: false, done: false, _new: true }); setCreate('todo'); }} style={{ display: 'flex', alignItems: 'center', gap: 11, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', borderRadius: 16, padding: '12px', color: 'var(--p-mute)' }}>
                <span style={{ width: 27, height: 27, borderRadius: '50%', border: '2px dashed var(--p-border)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}><IconPlus size={15} style={{ color: 'var(--p-faint)' }} /></span>
                <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-soft)' }}>Добавить дело</span>
              </button>
            </div>
          </div>
        </div>

        {/* мои садханы */}
        <div style={{ padding: '10px 0 6px' }}>
          <div style={{ padding: '0 14px' }}>{sectionHead('🌿', 'Мои садханы', sadhanas.length, kPOP.leaf)}</div>
          <div style={{ display: 'flex', gap: 11, overflowX: 'auto', padding: '2px 14px 10px', scrollbarWidth: 'none' }}>
            {sadhanas.map((s) => {
              const st = sadStat(s, today); const pop = kPOP[s.tone] || kPOP.coral; const tint = kTINT[s.tone] || kTINT.coral;
              return (
                <button key={s.id} className="tap pbtn" onClick={() => setOpenSad(s.id)} style={{ flexShrink: 0, width: 158, textAlign: 'left', border: 'none', cursor: 'pointer', background: 'var(--p-elev)', borderRadius: 18, padding: 14, boxShadow: '0 6px 18px -12px rgba(20,14,30,0.5)', border: '1px solid var(--p-border)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <span style={{ width: 38, height: 38, borderRadius: 12, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 19, background: tint }}>{s.emoji}</span>
                    {st.streak > 0 && <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12, color: kPOP.coral }}>🔥 {st.streak}</span>}
                  </div>
                  <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', marginTop: 11, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.name}</div>
                  <div style={{ fontSize: 11.5, fontWeight: 700, color: pop, marginTop: 2 }}>{C.sadFreqOf(s).occ(st.dayNum, s.total)}</div>
                  <div style={{ height: 7, borderRadius: 999, background: 'var(--p-ctrl)', overflow: 'hidden', marginTop: 9 }}>
                    <div style={{ width: st.pct + '%', height: '100%', borderRadius: 999, background: pop }} />
                  </div>
                </button>
              );
            })}
            <button className="tap pbtn" onClick={() => setCreate('practice')} style={{ flexShrink: 0, width: 132, border: '2px dashed var(--p-border)', cursor: 'pointer', background: 'transparent', borderRadius: 18, padding: 14, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 8, color: 'var(--p-mute)' }}>
              <span style={{ width: 38, height: 38, borderRadius: '50%', background: kTINT.leaf, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconPlus size={20} style={{ color: kPOP.leaf }} /></span>
              <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13, color: 'var(--p-soft)' }}>Новая практика</span>
            </button>
          </div>
        </div>

        <div style={{ height: 96 }} />
      </div>

      {/* нижняя навигация */}
      <CalBottomNav {...appNav} />

      {/* оверлеи */}
      {create === 'menu' && <window.CreateMenu host={host} onClose={() => setCreate(null)} onPick={(k) => setCreate(k)} />}
      {(create === 'todo') && <window.TodoSheet host={host} initial={editTodo && !editTodo._new ? editTodo : (editTodo && editTodo._new ? { ...editTodo, _new: undefined } : null)} defaultDate={curKey} onClose={() => { setCreate(null); setEditTodo(null); }} onSave={saveTodo} />}
      {create === 'practice' && <window.PracticeSheet host={host} onClose={() => setCreate(null)} onCreate={addSad} onLibrary={() => ping('📚 Библиотека практик — скоро ✨')} />}
      {openClass && <window.ClassSheet host={host} c={classes.find((x) => x.id === openClass.id) || openClass} onClose={() => setOpenClass(null)} onCancel={cancelClass} onToggleReminder={toggleClassRem} onSetLead={setClassLead} />}
      {openSad && <window.SadhanaSheet host={host} s={sadhanas.find((x) => x.id === openSad)} onClose={() => setOpenSad(null)} onMarkToday={markToday} onToggleReminder={toggleSadRem} onSetLead={setSadLead} onDelete={deleteSad} />}
      {menuTodo && <window.TodoMenu host={host} todo={menuTodo} onClose={() => setMenuTodo(null)} onEdit={() => { setEditTodo(menuTodo); setMenuTodo(null); setCreate('todo'); }} onDelete={() => delTodo(menuTodo)} />}
      {openEka && curPan.isEkadashi && <window.EkadashiSheet host={host} date={cur} onClose={() => setOpenEka(false)} onPickDate={(d) => setCur(d)} />}
      {openPan && <window.PanchangSheet host={host} date={cur} onClose={() => setOpenPan(false)} onOpenEkadashi={() => { setOpenPan(false); setOpenEka(true); }} />}

      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{ position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)', backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999, fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap', boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)' }}>{toast}</div>, host)}
    </div>
  );
}

Object.assign(window, { CalendarPage });
