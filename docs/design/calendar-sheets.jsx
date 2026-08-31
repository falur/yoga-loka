// calendar-sheets.jsx — оверлеи страницы «Календарь»
// Экспорт: CreateMenu, ClassSheet, TodoSheet, PracticeSheet, TodoMenu, SadhanaSheet, CSwitch, CALUI

const { useState: uSh } = React;
const { POP: sPOP, TINT: sTINT } = window;
const SLEAF = 'var(--c-leaf)';
const { keyOf: shKey, fromKey: shFromKey, relDay: shRel, longLabel: shLong, fmtMin: shFmt, parseMin: shParse, daysBetween: shBetween, TODAY: shToday } = window.CAL;
const { TONE: shTONE } = window;

/* ── общие атомы ─────────────────────────────────────────────────── */
const CALUI = {
  card: { background: 'var(--p-elev)', borderRadius: 24, boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' },
  grab: { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 6px' },
  label: { display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11.5, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 9 },
  input: { width: '100%', height: 46, border: 'none', borderRadius: 13, background: 'var(--p-ctrl)', padding: '0 14px', fontFamily: "'Nunito Sans', sans-serif", fontSize: 15, fontWeight: 600, color: 'var(--p-ink)', outline: 'none', boxSizing: 'border-box' },
  cancel: { width: '100%', marginTop: 10, height: 52, borderRadius: 16, border: 'none', cursor: 'pointer', background: 'var(--p-elev)', color: 'var(--p-ink)', fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 15.5, boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.32)' },
  primary: (c) => ({ width: '100%', height: 52, marginTop: 16, border: 'none', cursor: 'pointer', borderRadius: 16, background: c || sPOP.coral, color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15.5, boxShadow: `0 8px 20px -8px ${c || sPOP.coral}` }),
};

function CSwitch({ on, onChange, color }) {
  const c = color || SLEAF;
  return (
    <button className="tap" onClick={() => onChange(!on)} aria-pressed={on} style={{
      width: 46, height: 28, borderRadius: 999, border: 'none', cursor: 'pointer', flexShrink: 0,
      background: on ? c : 'var(--p-ctrl)', position: 'relative', transition: 'background .18s ease',
    }}>
      <span style={{ position: 'absolute', top: 3, left: on ? 21 : 3, width: 22, height: 22, borderRadius: '50%', background: '#fff', boxShadow: '0 2px 5px rgba(0,0,0,0.25)', transition: 'left .18s ease' }} />
    </button>
  );
}

/* ── выбор «за сколько напомнить» ───────────────────────────────── */
const LEAD_OPTS = [
  { v: 10, label: 'За 10 мин' },
  { v: 30, label: 'За 30 мин' },
  { v: 60, label: 'За час' },
  { v: 'custom', label: 'Своё' },
];
const leadIsPreset = (m) => m === 10 || m === 30 || m === 60;
const fmtLead = (m) => {
  if (!m) return 'в момент начала';
  if (m === 60) return 'за час';
  if (m % 60 === 0) return `за ${m / 60} ч`;
  if (m > 60) { const h = Math.floor(m / 60), r = m % 60; return `за ${h} ч ${r} мин`; }
  return `за ${m} мин`;
};
function ReminderLead({ lead, onChange, color }) {
  const c = color || sPOP.sky;
  const preset = leadIsPreset(lead) ? lead : 'custom';
  const chip = (on, label, fn) => (
    <button key={label} className="tap pbtn" onClick={fn} style={{
      flex: 1, height: 38, border: 'none', cursor: 'pointer', borderRadius: 11,
      fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12.5, padding: '0 4px',
      background: on ? c : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-mute)',
      boxShadow: on ? `0 5px 14px -7px ${c}` : 'none', transition: 'all .14s ease',
    }}>{label}</button>
  );
  return (
    <div style={{ marginTop: 12 }}>
      <span style={CALUI.label}>За сколько напомнить</span>
      <div style={{ display: 'flex', gap: 6 }}>
        {LEAD_OPTS.map((o) => chip(
          preset === o.v,
          o.label,
          () => onChange(o.v === 'custom' ? (preset === 'custom' ? lead : 15) : o.v),
        ))}
      </div>
      {preset === 'custom' && (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 10 }}>
          <input type="number" min="1" value={lead} onChange={(e) => onChange(Math.max(1, parseInt(e.target.value || '0', 10) || 1))} style={{ ...CALUI.input, flex: 1 }} />
          <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--p-mute)', whiteSpace: 'nowrap' }}>мин до начала</span>
        </div>
      )}
    </div>
  );
}

const wrapSheet = (host, onClose, body, pad = '0 8px 12px') =>
  ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: pad }}>{body}</div>
    </React.Fragment>, host);

/* ── меню «+»: дело / практика ──────────────────────────────────── */
function CreateMenu({ host, onClose, onPick }) {
  if (!host) return null;
  const opt = (emoji, title, sub, tone) => (
    <button className="tap pbtn" onClick={() => onPick(tone)} style={{
      display: 'flex', alignItems: 'center', gap: 13, width: '100%', border: 'none', cursor: 'pointer',
      background: 'var(--p-ctrl)', borderRadius: 16, padding: '14px 16px', textAlign: 'left', marginTop: 10,
    }}>
      <span style={{ width: 44, height: 44, borderRadius: 13, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 22, background: tone === 'todo' ? sTINT.sky : sTINT.leaf }}>{emoji}</span>
      <span style={{ minWidth: 0 }}>
        <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15.5, color: 'var(--p-ink)' }}>{title}</span>
        <span style={{ display: 'block', fontSize: 12.5, fontWeight: 600, color: 'var(--p-mute)', marginTop: 1 }}>{sub}</span>
      </span>
    </button>
  );
  return wrapSheet(host, onClose, (
    <React.Fragment>
      <div style={CALUI.card}>
        <div style={CALUI.grab} />
        <div style={{ padding: '2px 16px 16px' }}>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)', textAlign: 'center', padding: '2px 0 4px' }}>Добавить в календарь</div>
          {opt('📝', 'Дело', 'Задача с датой и временем или без', 'todo')}
          {opt('🪷', 'Практику', 'Садхана: 40 / 90 / 120 / 1000 дней', 'practice')}
        </div>
      </div>
      <button className="tap" onClick={onClose} style={CALUI.cancel}>Отмена</button>
    </React.Fragment>
  ));
}

/* ── деталь занятия ─────────────────────────────────────────────── */
function ClassSheet({ host, c, onClose, onCancel, onToggleReminder, onSetLead }) {
  if (!host || !c) return null;
  const col = shTONE[c.tone] || shTONE.a;
  const d = shFromKey(c.date);
  const fmt = c.place === 'studio' ? '📍 Очно' : (c.kind === 'audio' ? '🎧 Аудио' : '🎬 Видео');
  const reminder = c.reminder !== false;
  const lead = c.lead == null ? 30 : c.lead;
  const row = (icon, val) => (
    <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '10px 0', borderBottom: '1px solid var(--p-border)' }}>
      <span style={{ fontSize: 16, width: 22, textAlign: 'center', flexShrink: 0 }}>{icon}</span>
      <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--p-soft)' }}>{val}</span>
    </div>
  );
  return wrapSheet(host, onClose, (
    <div style={{ ...CALUI.card, overflow: 'hidden' }}>
      <div style={CALUI.grab} />
      {/* цветная шапка */}
      <div style={{ margin: '4px 16px 0', borderRadius: 18, padding: '16px 16px 15px', background: col.tint, display: 'flex', alignItems: 'center', gap: 12 }}>
        <span style={{ width: 48, height: 48, borderRadius: 14, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 24, background: 'var(--p-elev)' }}>{c.glyph}</span>
        <div style={{ minWidth: 0 }}>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 17, color: 'var(--p-ink)', lineHeight: 1.2 }}>{c.title}</div>
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginTop: 6, padding: '4px 10px', borderRadius: 999, background: 'var(--p-elev)', fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 11.5, color: sPOP.leaf }}>✓ Вы записаны</div>
        </div>
      </div>
      <div style={{ padding: '6px 18px 16px' }}>
        {row('📅', `${shRel(d, shToday)} · ${d.getDate()} ${window.CAL.RU_MONTH_GEN[d.getMonth()]}`)}
        {row('🕐', `${c.time}–${shFmt(c.endMin)} · ${c.durMin} мин`)}
        {row('🧑‍🏫', `Ведёт ${c.teacher}`)}
        {c.venue && row('📍', c.venue)}
        {row(fmt.split(' ')[0], fmt.split(' ')[1] + (c.live ? ' · прямой эфир' : '') + (c.course ? ' · курс' : '') + (c.recurring && !c.course ? ' · регулярное' : ''))}
        {/* напоминание */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '14px 0 4px' }}>
          <span style={{ fontSize: 16, width: 22, textAlign: 'center' }}>🔔</span>
          <span style={{ flex: 1, minWidth: 0 }}>
            <span style={{ display: 'block', fontSize: 14.5, fontWeight: 700, color: 'var(--p-ink)' }}>Напоминание</span>
            {reminder && <span style={{ display: 'block', fontSize: 12, fontWeight: 700, color: 'var(--p-mute)', marginTop: 1 }}>{fmtLead(lead)} до начала</span>}
          </span>
          <CSwitch on={reminder} onChange={() => onToggleReminder(c)} color={sPOP.leaf} />
        </div>
        {reminder && <ReminderLead lead={lead} onChange={(v) => onSetLead(c, v)} color={sPOP.leaf} />}
        <button className="tap pbtn" onClick={() => onCancel(c)} style={{ width: '100%', marginTop: 16, height: 50, border: 'none', cursor: 'pointer', borderRadius: 15, background: sTINT.coral, color: sPOP.coral, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15 }}>Отменить запись</button>
      </div>
    </div>
  ));
}

/* ── добавить / изменить дело ───────────────────────────────────── */
function TodoSheet({ host, initial, defaultDate, onClose, onSave }) {
  const [text, setText] = uSh(initial ? initial.text : '');
  const [dated, setDated] = uSh(initial ? !!initial.date : true);
  const [date, setDate] = uSh(initial && initial.date ? initial.date : (defaultDate || shKey(shToday)));
  const [time, setTime] = uSh(initial && initial.time ? initial.time : '09:00');
  const [reminder, setReminder] = uSh(initial ? !!initial.reminder : false);
  const [lead, setLead] = uSh(initial && initial.lead != null ? initial.lead : 30);
  if (!host) return null;
  const seg = (on, label, fn) => (
    <button className="tap pbtn" onClick={fn} style={{ flex: 1, height: 40, border: 'none', cursor: 'pointer', borderRadius: 11, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5, background: on ? 'var(--p-elev)' : 'transparent', color: on ? sPOP.sky : 'var(--p-mute)', boxShadow: on ? '0 3px 10px -5px rgba(40,20,60,0.4)' : 'none', transition: 'all .15s ease' }}>{label}</button>
  );
  const save = () => {
    if (!text.trim()) return;
    onSave({ id: initial ? initial.id : 'td' + Date.now(), text: text.trim(), date: dated ? date : null, time: dated ? time : null, reminder: dated ? reminder : false, lead, done: initial ? initial.done : false });
  };
  return wrapSheet(host, onClose, (
    <React.Fragment>
      <div style={{ ...CALUI.card, padding: '0 18px 18px' }}>
        <div style={CALUI.grab} />
        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)', textAlign: 'center', padding: '2px 0 16px' }}>{initial ? 'Изменить дело' : 'Новое дело'}</div>
        <input autoFocus value={text} onChange={(e) => setText(e.target.value)} placeholder="Что нужно сделать…" style={{ ...CALUI.input, marginBottom: 16 }} />
        <div style={{ display: 'flex', gap: 6, background: 'var(--p-ctrl)', borderRadius: 13, padding: 4, marginBottom: 14 }}>
          {seg(dated, '📅 С датой', () => setDated(true))}
          {seg(!dated, '📝 Без даты', () => setDated(false))}
        </div>
        {dated && (
          <React.Fragment>
            <div style={{ display: 'flex', gap: 12, marginBottom: 14 }}>
              <div style={{ flex: 1.4 }}>
                <span style={CALUI.label}>Дата</span>
                <input type="date" value={date} onChange={(e) => setDate(e.target.value)} style={CALUI.input} />
              </div>
              <div style={{ flex: 1 }}>
                <span style={CALUI.label}>Время</span>
                <input type="time" value={time} onChange={(e) => setTime(e.target.value)} style={CALUI.input} />
              </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '4px 2px' }}>
              <span style={{ fontSize: 16 }}>🔔</span>
              <span style={{ flex: 1, fontSize: 14.5, fontWeight: 700, color: 'var(--p-ink)' }}>Напоминание</span>
              <CSwitch on={reminder} onChange={setReminder} color={sPOP.sky} />
            </div>
            {reminder && <div style={{ padding: '2px 2px 0' }}><ReminderLead lead={lead} onChange={setLead} color={sPOP.sky} /></div>}
          </React.Fragment>
        )}
        <button className="tap pbtn" onClick={save} style={CALUI.primary(sPOP.sky)}>{initial ? 'Сохранить' : 'Добавить дело'}</button>
      </div>
      <button className="tap" onClick={onClose} style={CALUI.cancel}>Отмена</button>
    </React.Fragment>
  ));
}

/* ── меню дела: изменить / удалить ──────────────────────────────── */
function TodoMenu({ host, todo, onClose, onEdit, onDelete }) {
  if (!host || !todo) return null;
  const rowS = (danger) => ({ display: 'flex', alignItems: 'center', gap: 12, width: '100%', border: 'none', background: 'none', cursor: 'pointer', padding: '15px 18px', fontFamily: "'Nunito Sans', sans-serif", fontWeight: 700, fontSize: 15.5, color: danger ? sPOP.coral : 'var(--p-ink)' });
  return wrapSheet(host, onClose, (
    <React.Fragment>
      <div style={{ ...CALUI.card, overflow: 'hidden' }}>
        <div style={CALUI.grab} />
        <div style={{ padding: '0 18px 8px', textAlign: 'center', fontSize: 13.5, fontWeight: 700, color: 'var(--p-mute)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{todo.text}</div>
        <div style={{ height: 1, background: 'var(--p-border)' }} />
        <button className="tap" onClick={onEdit} style={rowS(false)}><IconEdit size={20} style={{ color: 'var(--p-mute)' }} />Изменить</button>
        <div style={{ height: 1, background: 'var(--p-border)' }} />
        <button className="tap" onClick={onDelete} style={rowS(true)}><IconTrash size={20} />Удалить</button>
      </div>
      <button className="tap" onClick={onClose} style={CALUI.cancel}>Отмена</button>
    </React.Fragment>
  ));
}

/* ── формат длительности: 90 → «1 ч 30 мин» ─────────────────────── */
const fmtDur = (m) => {
  if (!m) return '0 мин';
  if (m < 60) return `${m} мин`;
  const h = Math.floor(m / 60), r = m % 60;
  return r ? `${h} ч ${r} мин` : `${h} ч`;
};

/* ── создать практику (садхана / разовая) ───────────────────────── */
const SAD_COLORS = ['coral', 'sun', 'mint', 'sky', 'grape', 'bubble', 'leaf'];
function PracticeSheet({ host, onClose, onCreate, onLibrary }) {
  const KIND = window.PRACTICE_KIND;
  const [mode, setMode] = uSh('course');         // 'course' | 'once'
  const [kind, setKind] = uSh('meditation');     // 'meditation' | 'physical'
  const [name, setName] = uSh('');
  const [emoji, setEmoji] = uSh(KIND.meditation.defaultEmoji);
  const [emojiTouched, setEmojiTouched] = uSh(false);
  const [preset, setPreset] = uSh(40);
  const [custom, setCustom] = uSh('');
  const [freq, setFreq] = uSh('daily');          // 'daily' | 'weekdays' | 'weekly' | 'monthly'
  const [wdays, setWdays] = uSh([]);             // mondayIndex выбранных дней (для 'weekdays')
  const [every, setEvery] = uSh(1);              // интервал повтора (для 'weekly'/'monthly')
  const [everyCustom, setEveryCustom] = uSh('');
  const [start, setStart] = uSh(shKey(shToday));
  const [onceDate, setOnceDate] = uSh(shKey(shToday));
  const [time, setTime] = uSh('07:00');
  const [durPreset, setDurPreset] = uSh(31);
  const [durCustom, setDurCustom] = uSh('');
  const [tone, setTone] = uSh('grape');
  const [reminder, setReminder] = uSh(true);
  const [lead, setLead] = uSh(30);
  if (!host) return null;
  const presets = window.SADHANA_PRESETS;
  const durs = window.DUR_PRESETS;
  const freqMeta = window.SAD_FREQ[freq];
  const startWd = window.CAL.monIdx(shFromKey(start));
  const effWdays = wdays.length ? wdays : [startWd];
  const span = preset === 'custom' ? Math.max(1, parseInt(custom || '0', 10) || 0) : preset;
  const everyVal = every === 'custom' ? Math.max(1, parseInt(everyCustom || '0', 10) || 0) : every;
  const total = mode === 'once'
    ? 1
    : freq === 'weekdays' ? span * Math.max(1, effWdays.length) : span;
  const durMin = durPreset === 'custom' ? Math.max(1, parseInt(durCustom || '0', 10) || 0) : durPreset;
  const valid = name.trim() && total > 0 && durMin > 0;
  const reminderLabel = window.CAL.sadReminderLabel({ freq, every: everyVal });

  // смена периодичности — подбираем свои пресеты срока
  const pickFreq = (f) => {
    setFreq(f);
    setPreset(window.SAD_FREQ[f].presets[0]); setCustom('');
    setEvery(1); setEveryCustom('');
    if (f === 'weekdays' && wdays.length === 0) setWdays([startWd]);
  };
  const toggleWday = (i) => setWdays((arr) => {
    const base = arr.length ? arr : [startWd];
    if (base.includes(i)) return base.length > 1 ? base.filter((x) => x !== i) : base;
    return [...base, i].sort((a, b) => a - b);
  });

  // смена типа подменяет значок, если пользователь его ещё не трогал
  const pickKind = (k) => { setKind(k); if (!emojiTouched) setEmoji(KIND[k].defaultEmoji); };

  const save = () => {
    if (!valid) return;
    if (mode === 'once') {
      onCreate({ id: 'sd' + Date.now(), name: name.trim(), emoji, kind, durMin, total: 1, start: onceDate, time, tone, reminder: false, once: true, done: { [onceDate]: true } }, 'once');
    } else {
      onCreate({ id: 'sd' + Date.now(), name: name.trim(), emoji, kind, durMin, total, start, time, tone, reminder, lead, freq, days: freq === 'weekdays' ? effWdays : undefined, every: freqMeta.interval ? everyVal : undefined, done: {} }, 'course');
    }
  };

  const chip = (on, label, fn) => (
    <button key={label} className="tap pbtn" onClick={fn} style={{ flex: 1, height: 44, border: 'none', cursor: 'pointer', borderRadius: 13, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14, background: on ? 'var(--t-leaf)' : 'var(--p-ctrl)', color: on ? sPOP.leaf : 'var(--p-mute)', boxShadow: on ? `inset 0 0 0 1.5px ${sPOP.leaf}` : 'none', transition: 'all .14s ease' }}>{label}</button>
  );
  // сегмент-переключатель (тип / режим) — на белой подложке
  const segWrap = { display: 'flex', gap: 6, background: 'var(--p-ctrl)', borderRadius: 14, padding: 4 };
  const seg = (on, label, fn, accent) => (
    <button key={label} className="tap pbtn" onClick={fn} style={{ flex: 1, height: 42, border: 'none', cursor: 'pointer', borderRadius: 11, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13.5, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6, background: on ? 'var(--p-elev)' : 'transparent', color: on ? accent : 'var(--p-mute)', boxShadow: on ? '0 3px 10px -5px rgba(40,20,60,0.4)' : 'none', transition: 'all .15s ease' }}>{label}</button>
  );

  const ctaLabel = mode === 'once'
    ? 'Записать практику'
    : `Начать практику${total ? ` · ${total} ${freq === 'daily' ? 'дн.' : window.CAL.razWord(total)}` : ''}`;

  return wrapSheet(host, onClose, (
    <React.Fragment>
      <div style={{ ...CALUI.card, maxHeight: '84vh', overflowY: 'auto', WebkitOverflowScrolling: 'touch' }}>
        <div style={{ position: 'sticky', top: 0, background: 'var(--p-elev)', zIndex: 2, borderRadius: '24px 24px 0 0' }}>
          <div style={CALUI.grab} />
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)', textAlign: 'center', padding: '2px 0 12px' }}>Новая практика</div>
        </div>
        <div style={{ padding: '0 18px 18px', display: 'flex', flexDirection: 'column', gap: 16 }}>
          {/* добавить из библиотеки */}
          <button className="tap pbtn" onClick={() => onLibrary && onLibrary()} style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', border: 'none', cursor: 'pointer', background: sTINT.grape, borderRadius: 16, padding: '13px 14px', textAlign: 'left' }}>
            <span style={{ width: 40, height: 40, borderRadius: 12, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 20, background: 'var(--p-elev)' }}>📚</span>
            <span style={{ flex: 1, minWidth: 0 }}>
              <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)' }}>Добавить из библиотеки</span>
              <span style={{ display: 'block', fontSize: 12, fontWeight: 600, color: 'var(--p-mute)', marginTop: 1 }}>Готовые крии и медитации</span>
            </span>
            <IconChevron size={16} style={{ color: sPOP.grape, flexShrink: 0 }} />
          </button>

          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <span style={{ flex: 1, height: 1, background: 'var(--p-border)' }} />
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11.5, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.06em' }}>или своя</span>
            <span style={{ flex: 1, height: 1, background: 'var(--p-border)' }} />
          </div>

          {/* тип практики */}
          <div>
            <span style={CALUI.label}>Тип практики</span>
            <div style={segWrap}>
              {seg(kind === 'meditation', '🧘 Медитация', () => pickKind('meditation'), sPOP.grape)}
              {seg(kind === 'physical', '🔥 Физическая', () => pickKind('physical'), sPOP.coral)}
            </div>
          </div>

          <div>
            <span style={CALUI.label}>Название</span>
            <input autoFocus value={name} onChange={(e) => setName(e.target.value)} placeholder={kind === 'physical' ? 'Напр. Дыхание огня' : 'Напр. Медитация на сердце'} style={CALUI.input} />
          </div>
          <div>
            <span style={CALUI.label}>Значок</span>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              {window.EMOJI_POOL.map((e) => {
                const on = emoji === e;
                return <button key={e} className="tap pbtn" onClick={() => { setEmoji(e); setEmojiTouched(true); }} style={{ width: 42, height: 42, borderRadius: 12, border: 'none', cursor: 'pointer', fontSize: 20, background: on ? 'var(--t-leaf)' : 'var(--p-ctrl)', boxShadow: on ? `inset 0 0 0 2px ${sPOP.leaf}` : 'none' }}>{e}</button>;
              })}
            </div>
          </div>

          {/* длительность одной практики — для статистики */}
          <div>
            <span style={CALUI.label}>Длительность · для статистики</span>
            <div style={{ display: 'flex', gap: 8 }}>
              {durs.map((d) => chip(durPreset === d, d + ' мин', () => setDurPreset(d)))}
              {chip(durPreset === 'custom', 'Свой', () => setDurPreset('custom'))}
            </div>
            {durPreset === 'custom'
              ? <input type="number" min="1" value={durCustom} onChange={(e) => setDurCustom(e.target.value)} placeholder="Сколько минут?" style={{ ...CALUI.input, marginTop: 10 }} />
              : <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--p-faint)', marginTop: 9 }}>Сколько минут в день вы уделяете практике — копится в статистику.</div>}
          </div>

          {/* режим: курс / разово */}
          <div>
            <span style={CALUI.label}>Как ведём</span>
            <div style={segWrap}>
              {seg(mode === 'course', '🪷 Садхана', () => setMode('course'), sPOP.leaf)}
              {seg(mode === 'once', '📌 Один день', () => setMode('once'), sPOP.leaf)}
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--p-faint)', marginTop: 9 }}>
              {mode === 'course' ? 'Курс на много дней с отметками каждый день.' : 'Разовая запись — отметим выполненной сразу для статистики.'}
            </div>
          </div>

          {mode === 'course' ? (
            <React.Fragment>
              {/* периодичность */}
              <div>
                <span style={CALUI.label}>Периодичность</span>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                  {Object.values(window.SAD_FREQ).map((m) => chip(freq === m.id, m.chip, () => pickFreq(m.id)))}
                </div>
                {freq === 'weekdays' && (
                  <div style={{ display: 'flex', gap: 6, marginTop: 10 }}>
                    {window.CAL.RU_WD_SHORT.map((w, i) => {
                      const on = effWdays.includes(i);
                      return <button key={w} className="tap pbtn" onClick={() => toggleWday(i)} style={{ flex: 1, height: 40, border: 'none', cursor: 'pointer', borderRadius: 11, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12.5, background: on ? 'var(--t-leaf)' : 'var(--p-ctrl)', color: on ? sPOP.leaf : 'var(--p-mute)', boxShadow: on ? `inset 0 0 0 1.5px ${sPOP.leaf}` : 'none' }}>{w}</button>;
                    })}
                  </div>
                )}
              </div>
              {freqMeta.interval && (
                <div>
                  <span style={CALUI.label}>Как часто повторять</span>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    {freqMeta.intervalPresets.map((n) => chip(every === n, n === 1 ? (freq === 'weekly' ? 'Каждую неделю' : 'Каждый месяц') : `Раз в ${n} ${freqMeta.intervalWord(n)}`, () => { setEvery(n); setEveryCustom(''); }))}
                    {chip(every === 'custom', 'Свой', () => setEvery('custom'))}
                  </div>
                  {every === 'custom'
                    ? <input type="number" min="1" value={everyCustom} onChange={(e) => setEveryCustom(e.target.value)} placeholder={`Каждые сколько ${freq === 'weekly' ? 'недель' : 'месяцев'}?`} style={{ ...CALUI.input, marginTop: 10 }} />
                    : <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--p-faint)', marginTop: 9 }}>{`Практика повторяется ${everyVal === 1 ? (freq === 'weekly' ? 'каждую неделю' : 'каждый месяц') : `раз в ${everyVal} ${freqMeta.intervalWord(everyVal)}`}.`}</div>}
                </div>
              )}
              <div>
                <span style={CALUI.label}>{freqMeta.spanLabel}</span>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                  {freqMeta.presets.map((p) => chip(preset === p, freq === 'daily' ? p + '' : (freq === 'weekly' || freq === 'monthly') ? `${p} ${window.CAL.razWord(p)}` : `${p} ${freqMeta.spanUnit}`, () => setPreset(p)))}
                  {chip(preset === 'custom', 'Свой', () => setPreset('custom'))}
                </div>
                {preset === 'custom'
                  ? <input type="number" min="1" value={custom} onChange={(e) => setCustom(e.target.value)} placeholder={freqMeta.spanUnit === 'раз' ? 'Сколько раз?' : `Сколько ${freqMeta.spanUnit === 'дн.' ? 'дней' : 'недель'}?`} style={{ ...CALUI.input, marginTop: 10 }} />
                  : <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--p-faint)', marginTop: 9 }}>{freqMeta.hint}{freq !== 'daily' && total ? ` Всего ${total} ${window.CAL.razWord(total)}.` : ''}</div>}
              </div>
              <div style={{ display: 'flex', gap: 12 }}>
                <div style={{ flex: 1.4 }}>
                  <span style={CALUI.label}>Старт</span>
                  <input type="date" value={start} onChange={(e) => setStart(e.target.value)} style={CALUI.input} />
                </div>
                <div style={{ flex: 1 }}>
                  <span style={CALUI.label}>Время</span>
                  <input type="time" value={time} onChange={(e) => setTime(e.target.value)} style={CALUI.input} />
                </div>
              </div>
            </React.Fragment>
          ) : (
            <div>
              <span style={CALUI.label}>День практики</span>
              <input type="date" value={onceDate} max={shKey(shToday)} onChange={(e) => setOnceDate(e.target.value)} style={CALUI.input} />
            </div>
          )}

          <div>
            <span style={CALUI.label}>Цвет</span>
            <div style={{ display: 'flex', gap: 10 }}>
              {SAD_COLORS.map((cn) => {
                const on = tone === cn;
                return <button key={cn} className="tap pbtn" onClick={() => setTone(cn)} aria-label={cn} style={{ width: 34, height: 34, borderRadius: '50%', cursor: 'pointer', background: sPOP[cn], border: on ? '3px solid var(--p-elev)' : '3px solid transparent', boxShadow: on ? `0 0 0 2px ${sPOP[cn]}` : 'none' }} />;
              })}
            </div>
          </div>

          {mode === 'course' && (
            <div style={{ padding: '13px 14px', borderRadius: 14, background: 'var(--p-ctrl)' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
                <span style={{ fontSize: 18 }}>🔔</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14, color: 'var(--p-ink)' }}>Напоминание</div>
                  <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-mute)' }}>{reminder ? `${reminderLabel} · ${fmtLead(lead)} до ${time}` : `${reminderLabel} в ${time}`}</div>
                </div>
                <CSwitch on={reminder} onChange={setReminder} color={sPOP[tone]} />
              </div>
              {reminder && <ReminderLead lead={lead} onChange={setLead} color={sPOP[tone]} />}
            </div>
          )}

          <button className="tap pbtn" onClick={save} style={{ ...CALUI.primary(sPOP.leaf), marginTop: 2, opacity: valid ? 1 : 0.45 }}>{ctaLabel}</button>
        </div>
      </div>
      <button className="tap" onClick={onClose} style={CALUI.cancel}>Отмена</button>
    </React.Fragment>
  ), '0 8px 12px');
}

/* ── деталь садханы: прогресс, отметки, удаление ────────────────── */
function SadhanaSheet({ host, s, onClose, onMarkToday, onToggleReminder, onSetLead, onDelete }) {
  if (!host || !s) return null;
  const pop = sPOP[s.tone] || sPOP.coral;
  const tint = sTINT[s.tone] || sTINT.coral;
  const kindMeta = (window.PRACTICE_KIND && (window.PRACTICE_KIND[s.kind] || window.PRACTICE_KIND.meditation)) || { label: 'Практика', icon: '🪷' };
  const durMin = s.durMin || 0;
  const startD = shFromKey(s.start);
  const dayNum = Math.max(1, window.CAL.sadElapsed(s, shToday));
  const freqShort = s.once ? '' : window.CAL.sadFreqShort(s);
  const doneCount = Object.values(s.done).filter(Boolean).length;
  const totalMin = doneCount * durMin;
  const pct = Math.round((doneCount / s.total) * 100);
  const todayKey = shKey(shToday);
  const inRange = !s.once && window.CAL.sadActiveOn(s, shToday);
  const doneToday = !!s.done[todayKey];
  // ближайшие вхождения практики
  const cells = window.CAL.sadDates(s, 35);
  return wrapSheet(host, onClose, (
    <React.Fragment>
      <div style={{ ...CALUI.card, overflow: 'hidden' }}>
        <div style={CALUI.grab} />
        <div style={{ margin: '4px 16px 0', borderRadius: 18, padding: '16px', background: tint, display: 'flex', alignItems: 'center', gap: 13 }}>
          <span style={{ width: 50, height: 50, borderRadius: 15, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 26, background: 'var(--p-elev)' }}>{s.emoji}</span>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 17, color: 'var(--p-ink)' }}>{s.name}</div>
            <div style={{ fontSize: 12.5, fontWeight: 700, color: pop, marginTop: 2 }}>{s.once ? 'Разовая практика' : `${window.CAL.sadFreqOf(s).occ(dayNum, s.total)} · отмечено ${doneCount}`}</div>
            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 5, marginTop: 7, padding: '3px 9px', borderRadius: 999, background: 'var(--p-elev)', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11, color: pop }}>{kindMeta.icon} {kindMeta.label}{durMin ? ` · ${durMin} мин` : ''}{freqShort ? ` · ${freqShort}` : ''}</div>
          </div>
        </div>
        <div style={{ padding: '14px 18px 16px' }}>
          {/* прогресс-бар */}
          <div style={{ height: 10, borderRadius: 999, background: 'var(--p-ctrl)', overflow: 'hidden' }}>
            <div style={{ width: pct + '%', height: '100%', borderRadius: 999, background: pop, transition: 'width .3s ease' }} />
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 7, fontSize: 11.5, fontWeight: 700, color: 'var(--p-faint)' }}>
            <span>{pct}% пути</span>
            <span>осталось {Math.max(0, s.total - doneCount)} {window.CAL.sadUnit(s, Math.max(0, s.total - doneCount))}</span>
          </div>
          {/* статистика: всего времени практики */}
          {durMin > 0 && (
            <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
              <div style={{ flex: 1, background: tint, borderRadius: 14, padding: '12px 13px' }}>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: pop, lineHeight: 1.1 }}>{fmtDur(totalMin)}</div>
                <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-mute)', marginTop: 3 }}>всего практики</div>
              </div>
              <div style={{ flex: 1, background: 'var(--p-ctrl)', borderRadius: 14, padding: '12px 13px' }}>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)', lineHeight: 1.1 }}>{durMin} мин</div>
                <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-mute)', marginTop: 3 }}>за день</div>
              </div>
            </div>
          )}
          {/* сетка отметок */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 6, marginTop: 16 }}>
            {cells.map((d) => {
              const k = shKey(d);
              const done = !!s.done[k];
              const isToday = k === todayKey;
              const future = d > shToday;
              return (
                <div key={k} style={{ aspectRatio: '1', borderRadius: 9, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 800, fontFamily: "'Quicksand', sans-serif",
                  background: done ? pop : 'var(--p-ctrl)', color: done ? '#fff' : (future ? 'var(--p-faint)' : 'var(--p-mute)'),
                  opacity: future && !done ? 0.5 : 1, boxShadow: isToday ? `inset 0 0 0 2px ${pop}` : 'none' }}>{d.getDate()}</div>
              );
            })}
          </div>
          {/* отметить сегодня */}
          {inRange && (
            <button className="tap pbtn" onClick={() => onMarkToday(s)} style={{ width: '100%', height: 52, marginTop: 16, border: 'none', cursor: 'pointer', borderRadius: 16, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15.5,
              background: doneToday ? tint : pop, color: doneToday ? pop : '#fff', boxShadow: doneToday ? 'none' : `0 8px 20px -8px ${pop}` }}>
              {doneToday ? '✓ Сегодня отмечено — снять' : 'Отметить сегодня ✓'}
            </button>
          )}
          {!inRange && !s.once && (
            <div style={{ textAlign: 'center', marginTop: 16, padding: '12px', borderRadius: 14, background: 'var(--p-ctrl)', fontSize: 12.5, fontWeight: 700, color: 'var(--p-mute)' }}>Сегодня нет по расписанию · {window.CAL.sadFreqShort(s) || 'практика завершена'}</div>
          )}
          {/* напоминание */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '15px 0 4px' }}>
            <span style={{ fontSize: 16, width: 22, textAlign: 'center' }}>🔔</span>
            <span style={{ flex: 1, fontSize: 14.5, fontWeight: 700, color: 'var(--p-ink)' }}>Напоминание в {s.time}</span>
            <CSwitch on={s.reminder} onChange={() => onToggleReminder(s)} color={pop} />
          </div>
          {s.reminder && <ReminderLead lead={s.lead == null ? 30 : s.lead} onChange={(v) => onSetLead(s, v)} color={pop} />}
          <button className="tap pbtn" onClick={() => onDelete(s)} style={{ width: '100%', marginTop: 14, height: 48, border: 'none', cursor: 'pointer', borderRadius: 14, background: 'var(--p-ctrl)', color: sPOP.coral, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}><IconTrash size={18} />Завершить практику</button>
        </div>
      </div>
    </React.Fragment>
  ));
}

Object.assign(window, { CreateMenu, ClassSheet, TodoSheet, PracticeSheet, TodoMenu, SadhanaSheet, CSwitch, CALUI });
