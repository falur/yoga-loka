// practice-editor.jsx — экран «Новая практика» (конструктор практики).
// Зависит от icons.jsx (Icon*) и ios-frame.jsx (через mount). Самодостаточен по медиа.
// Экспорт в window: PracticeEditorScreen.

const { useState, useRef } = React;

const QS = "'Quicksand', sans-serif";
const NS = "'Nunito Sans', system-ui, sans-serif";
const SG = "'Space Grotesk', sans-serif";

/* ── доп. иконки (currentColor) ──────────────────────────────── */
const _pei = (paths, vb = '0 0 24 24', sw = 1.7) => ({ size = 22, fill, style }) => (
  <svg width={size} height={size} viewBox={vb} fill={fill || 'none'} stroke="currentColor" strokeWidth={sw} strokeLinecap="round" strokeLinejoin="round" style={style}>{paths}</svg>
);
const IconImage = _pei(<><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path d="m4.5 17 4.5-4.2 3.4 3 3-2.6 4.1 3.8"/></>);
const IconMusic = _pei(<><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/></>);
const IconRepeat = _pei(<><path d="M4 11.5V10a3 3 0 0 1 3-3h11"/><path d="m15 4 3 3-3 3"/><path d="M20 12.5V14a3 3 0 0 1-3 3H6"/><path d="m9 20-3-3 3-3"/></>);
const IconPlay = _pei(<path d="M7 5.5v13l11-6.5-11-6.5Z"/>, '0 0 24 24', 1.6);
const IconGrip = _pei(<><circle cx="9" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="6" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="9" cy="18" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="1.2" fill="currentColor" stroke="none"/></>);
const IconWave = _pei(<><path d="M3 12h2l2-5 3 11 3-15 3 12 2-3h3"/></>, '0 0 24 24', 1.6);
const IconUpload = _pei(<><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 19h14"/></>, '0 0 24 24', 1.7);

/* ── библиотеки ──────────────────────────────────────────────── */
const PE_KINDS = [
  { id: 'hatha',      label: 'Хатха',     e: '☀️', c: 'var(--c-goldink)', t: 'var(--t-sun)'    },
  { id: 'vinyasa',    label: 'Виньяса',   e: '🌊', c: 'var(--c-coral)',   t: 'var(--t-coral)'  },
  { id: 'kundalini',  label: 'Кундалини', e: '🔥', c: 'var(--c-grape)',   t: 'var(--t-grape)'  },
  { id: 'pranayama',  label: 'Пранаяма',  e: '🌬', c: 'var(--c-sky)',     t: 'var(--t-sky)'    },
  { id: 'meditation', label: 'Медитация', e: '🪷', c: 'var(--c-mint)',    t: 'var(--t-mint)'   },
  { id: 'yin',        label: 'Инь',       e: '🌙', c: 'var(--c-bubble)',  t: 'var(--t-bubble)' },
];
const PE_kindById = (id) => PE_KINDS.find((k) => k.id === id) || PE_KINDS[0];

const PE_PLAYLISTS = [
  { id: 'morning', name: 'Утренний раунд',  tracks: 8,  min: 42, c: 'var(--c-coral)', t: 'var(--t-coral)' },
  { id: 'calm',    name: 'Глубокий покой',  tracks: 12, min: 58, c: 'var(--c-mint)',  t: 'var(--t-mint)'  },
  { id: 'mantra',  name: 'Мантры и киртан', tracks: 6,  min: 35, c: 'var(--c-grape)', t: 'var(--t-grape)' },
  { id: 'nature',  name: 'Звуки природы',   tracks: 10, min: 60, c: 'var(--c-leaf)',  t: 'var(--t-leaf)'  },
];

const PE_TRACKS = [
  { id: 't1', name: 'Раскрытие дыхания',   by: 'Anoushka', dur: '4:12' },
  { id: 't2', name: 'Тёплый поток',        by: 'Garth S.', dur: '5:48' },
  { id: 't3', name: 'Гонг и тишина',       by: 'Mirabai',  dur: '7:20' },
  { id: 't4', name: 'Мантра «Ом»',         by: 'Deva P.',  dur: '6:05' },
  { id: 't5', name: 'Дождь в горах',       by: 'Nature',   dur: '8:30' },
  { id: 't6', name: 'Светлая киртана',     by: 'Jai U.',   dur: '5:14' },
];

const PE_SOUNDS = [
  { id: 'bell',  label: 'Колокольчик',  e: '🔔' },
  { id: 'click', label: 'Клик',         e: '👆' },
  { id: 'gong',  label: 'Гонг',         e: '🪘' },
  { id: 'bowl',  label: 'Поющая чаша',  e: '🥣' },
  { id: 'vibro', label: 'Вибрация',     e: '📳' },
  { id: 'none',  label: 'Без звука',    e: '🔇' },
];

const PE_ANIMS = [
  { id: 'none',    label: 'Без анимации',     anim: 'none' },
  { id: 'inflate', label: 'Круг надувается', anim: 'peInflate 1.6s ease-in-out infinite' },
  { id: 'deflate', label: 'Круг сдувается',  anim: 'peDeflate 1.6s ease-in-out infinite' },
  { id: 'vibrate', label: 'Круг вибрирует',  anim: 'none', wave: true },
];

const PE_TYPES = [
  { id: 'simple', label: 'Простой',  e: '⏱',  c: 'var(--c-sky)',   t: 'var(--t-sky)',   desc: 'Одно действие с таймером' },
  { id: 'cycle',  label: 'Цикл',     e: '🔁', c: 'var(--c-grape)', t: 'var(--t-grape)', desc: 'Повторяющаяся последовательность' },
  { id: 'metro',  label: 'Метроном', e: '🎼', c: 'var(--c-sun)',   t: 'var(--t-sun)',   desc: 'Удары в заданном темпе' },
];
const PE_typeById = (id) => PE_TYPES.find((t) => t.id === id) || PE_TYPES[0];

let PE_uid = 100;
const nextId = (p) => p + ++PE_uid;
const newStep = () => ({ id: nextId('s'), name: '', sec: 60, track: null, startSound: 'bell', endSound: 'bell', anim: 'inflate' });
const newPart = () => ({
  id: nextId('p'), name: '', desc: '', photo: false, video: null, audio: null,
  type: 'simple',
  simpleSec: 300,
  steps: [newStep()],
  repeatMode: 'count', repeatCount: 4, repeatMin: 10,
  bpm: 60, endMode: 'beats', beats: 60, metroMin: 3,
});

/* ── форматирование ──────────────────────────────────────────── */
const mmss = (sec) => `${Math.floor(sec / 60)}:${String(sec % 60).padStart(2, '0')}`;
const minLabel = (sec) => {
  const m = Math.round(sec / 60);
  return m > 0 ? `${m} мин` : `${sec} сек`;
};
const partSummary = (p) => {
  if (p.type === 'simple') return mmss(p.simpleSec);
  if (p.type === 'cycle') {
    const rep = p.repeatMode === 'count' ? `×${p.repeatCount}` : `${p.repeatMin} мин`;
    return `${p.steps.length} ${p.steps.length === 1 ? 'шаг' : (p.steps.length < 5 ? 'шага' : 'шагов')} · ${rep}`;
  }
  return `${p.bpm} BPM · ${p.endMode === 'beats' ? p.beats + ' ударов' : p.metroMin + ' мин'}`;
};

/* ── общие стили ─────────────────────────────────────────────── */
const peLabel = { fontFamily: QS, fontWeight: 800, fontSize: 11.5, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.07em', marginBottom: 9, display: 'flex', alignItems: 'center', gap: 7 };
const peOpt = { textTransform: 'none', letterSpacing: 0, fontWeight: 700, color: 'var(--p-faint)', fontSize: 11.5 };
const peInput = {
  width: '100%', boxSizing: 'border-box', height: 50, padding: '0 15px',
  borderRadius: 15, border: '1.5px solid var(--p-border)', background: 'var(--p-ctrl)',
  outline: 'none', fontFamily: NS, fontSize: 16, fontWeight: 600, color: 'var(--p-ink)',
};
const peCardBox = { borderRadius: 22, background: 'var(--p-elev)', border: '1px solid var(--p-border)', boxShadow: '0 16px 34px -28px rgba(40,20,60,0.55)' };

/* ── секция-заголовок ────────────────────────────────────────── */
function PESection({ children }) {
  return <div style={{ ...peLabel, fontSize: 12, marginBottom: 0, padding: '0 4px' }}>{children}</div>;
}

/* ── поле с подписью ─────────────────────────────────────────── */
function PEField({ label, optional, hint, children }) {
  return (
    <div>
      <div style={peLabel}>
        <span>{label}</span>
        {optional && <span style={peOpt}>необязательно</span>}
        {hint && <span style={peOpt}>{hint}</span>}
      </div>
      {children}
    </div>
  );
}

/* ── степпер −/+ ─────────────────────────────────────────────── */
// press-and-hold: срабатывает сразу, затем ускоряется при удержании
function useHold() {
  const t = useRef({ to: null, iv: null });
  const stop = () => { clearTimeout(t.current.to); clearInterval(t.current.iv); t.current.to = null; t.current.iv = null; };
  const start = (fn) => {
    stop(); fn();
    t.current.to = setTimeout(() => {
      let n = 0;
      t.current.iv = setInterval(() => { n++; fn(); if (n === 6 && t.current.iv) { clearInterval(t.current.iv); t.current.iv = setInterval(fn, 55); } }, 130);
    }, 420);
  };
  return { start, stop };
}

function PEStepper({ value, onChange, min = 0, max = 999, step = 1, suffix, accent = 'var(--ctrl-accent)' }) {
  const set = (v) => onChange(Math.max(min, Math.min(max, v)));
  const dec = useHold(), inc = useHold();
  const [draft, setDraft] = useState(null); // строка при вводе с клавиатуры
  const btn = (dis) => ({
    width: 40, height: 40, flexShrink: 0, borderRadius: 12, border: 'none', cursor: dis ? 'default' : 'pointer',
    background: dis ? 'var(--p-ctrl)' : accent, color: dis ? 'var(--p-faint)' : '#fff',
    fontSize: 20, fontWeight: 700, lineHeight: 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', touchAction: 'manipulation', userSelect: 'none',
  });
  const hold = (h, fn) => ({
    onPointerDown: (e) => { e.preventDefault(); h.start(fn); },
    onPointerUp: h.stop, onPointerLeave: h.stop, onPointerCancel: h.stop,
  });
  const commit = () => {
    if (draft === null) return;
    const n = parseInt(draft, 10);
    if (!isNaN(n)) set(n);
    setDraft(null);
  };
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <button className="pbtn" {...hold(dec, () => set(value - step))} disabled={value <= min} style={btn(value <= min)}>−</button>
      <div style={{ flex: 1, height: 40, borderRadius: 12, background: 'var(--p-ctrl)', border: '1.5px solid var(--p-border)', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 5 }}>
        <input
          type="text" inputMode="numeric" pattern="[0-9]*"
          value={draft !== null ? draft : String(value)}
          onFocus={(e) => { setDraft(String(value)); requestAnimationFrame(() => e.target.select()); }}
          onChange={(e) => setDraft(e.target.value.replace(/[^0-9]/g, ''))}
          onBlur={commit}
          onKeyDown={(e) => { if (e.key === 'Enter') e.target.blur(); }}
          style={{
            width: `${Math.max(1.2, String(draft !== null ? draft : value).length + 0.3)}ch`,
            border: 'none', background: 'transparent', outline: 'none', textAlign: 'center', padding: 0,
            fontFamily: SG, fontWeight: 600, fontSize: 19, lineHeight: 1, color: 'var(--p-ink)', fontVariantNumeric: 'tabular-nums',
          }}
        />
        {suffix && <span style={{ fontFamily: QS, fontWeight: 700, fontSize: 12.5, lineHeight: 1, color: 'var(--p-mute)' }}>{suffix}</span>}
      </div>
      <button className="pbtn" {...hold(inc, () => set(value + step))} disabled={value >= max} style={btn(value >= max)}>+</button>
    </div>
  );
}

/* ── выбор времени мин:сек ───────────────────────────────────── */
function PEDuration({ sec, onChange, accent, secStep = 5 }) {
  const m = Math.floor(sec / 60), s = sec % 60;
  return (
    <div style={{ display: 'flex', gap: 10 }}>
      <div style={{ flex: 1 }}>
        <div style={{ fontFamily: QS, fontWeight: 700, fontSize: 11.5, color: 'var(--p-faint)', marginBottom: 6, textAlign: 'center' }}>минуты</div>
        <PEStepper value={m} onChange={(v) => onChange(v * 60 + s)} min={0} max={90} accent={accent} />
      </div>
      <div style={{ flex: 1 }}>
        <div style={{ fontFamily: QS, fontWeight: 700, fontSize: 11.5, color: 'var(--p-faint)', marginBottom: 6, textAlign: 'center' }}>секунды</div>
        <PEStepper value={s} onChange={(v) => onChange(m * 60 + ((v + 60) % 60))} min={0} max={60 - secStep} step={secStep} accent={accent} />
      </div>
    </div>
  );
}

/* ── сегмент-переключатель ───────────────────────────────────── */
function PESeg({ options, value, onChange, accent = 'var(--p-ink)' }) {
  return (
    <div style={{ display: 'flex', gap: 4, padding: 4, borderRadius: 14, background: 'var(--p-ctrl)' }}>
      {options.map((o) => {
        const on = o.id === value;
        return (
          <button key={o.id} className="pbtn" onClick={() => onChange(o.id)} style={{
            flex: 1, height: 38, border: 'none', cursor: 'pointer', borderRadius: 11,
            background: on ? 'var(--p-elev)' : 'transparent', color: on ? accent : 'var(--p-mute)',
            fontFamily: QS, fontWeight: 800, fontSize: 13.5,
            boxShadow: on ? '0 4px 12px -6px rgba(20,20,40,0.4)' : 'none',
          }}>{o.label}</button>
        );
      })}
    </div>
  );
}

/* ── слот фото ───────────────────────────────────────────────── */
function PEPhotoSlot({ filled, onToggle }) {
  if (filled) {
    return (
      <div style={{ position: 'relative', height: 132, borderRadius: 16, overflow: 'hidden', background: 'linear-gradient(135deg, var(--c-sun), var(--c-coral))' }}>
        <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'flex-end', padding: 10 }}>
          <span style={{ fontFamily: 'monospace', fontSize: 11, color: 'rgba(255,255,255,0.9)', background: 'rgba(20,14,30,0.3)', padding: '3px 8px', borderRadius: 8 }}>asana.jpg</span>
        </div>
        <button className="pbtn" onClick={onToggle} aria-label="Убрать" style={{ position: 'absolute', top: 8, right: 8, width: 30, height: 30, borderRadius: '50%', border: 'none', cursor: 'pointer', background: 'rgba(20,14,30,0.5)', color: '#fff', fontSize: 16, lineHeight: 1 }}>×</button>
      </div>
    );
  }
  return (
    <button className="pbtn" onClick={onToggle} style={{
      width: '100%', height: 132, cursor: 'pointer', borderRadius: 16,
      border: '1.5px dashed var(--p-faint)', color: 'var(--p-mute)',
      background: 'repeating-linear-gradient(135deg, var(--p-ctrl) 0 11px, transparent 11px 22px)',
      display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 7,
    }}>
      <IconImage size={26} style={{ color: 'var(--p-faint)' }} />
      <span style={{ fontFamily: 'monospace', fontSize: 12 }}>добавить фото</span>
    </button>
  );
}

/* ── строка медиа (видео / аудио) ────────────────────────────── */
function PEMediaRow({ icon, label, value, placeholder, onToggle, accent }) {
  return (
    <button className="pbtn" onClick={onToggle} style={{
      width: '100%', display: 'flex', alignItems: 'center', gap: 12, padding: '11px 13px', cursor: 'pointer',
      borderRadius: 14, border: '1.5px solid var(--p-border)', background: 'var(--p-ctrl)', textAlign: 'left',
    }}>
      <span style={{ width: 38, height: 38, borderRadius: 11, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', background: value ? accent : 'var(--p-elev)', color: value ? '#fff' : 'var(--p-mute)' }}>{icon}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 14, color: 'var(--p-ink)' }}>{label}</div>
        <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12.5, color: value ? 'var(--p-mute)' : 'var(--p-faint)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{value || placeholder}</div>
      </div>
      <span style={{ flexShrink: 0, color: value ? 'var(--c-coral)' : 'var(--p-faint)', fontFamily: QS, fontWeight: 800, fontSize: 13 }}>{value ? 'Убрать' : '+ Добавить'}</span>
    </button>
  );
}

/* ── чип-кружок для медиа в карточке ─────────────────────────── */
function MediaDot({ icon, c }) {
  return <span style={{ width: 24, height: 24, borderRadius: 7, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', background: 'var(--p-ctrl)', color: c }}>{icon}</span>;
}

/* ── нижняя шторка-обёртка ───────────────────────────────────── */
function PESheet({ title, onClose, children, accent }) {
  return (
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 7px 8px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 26, overflow: 'hidden', boxShadow: '0 -14px 48px -14px rgba(20,14,30,0.5)' }}>
          <div style={{ width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 0' }} />
          <div style={{ display: 'flex', alignItems: 'center', padding: '10px 18px 6px' }}>
            <h3 style={{ margin: 0, flex: 1, fontFamily: QS, fontWeight: 800, fontSize: 19, color: 'var(--p-ink)' }}>{title}</h3>
            <button className="pbtn" onClick={onClose} style={{ width: 34, height: 34, borderRadius: '50%', border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-mute)', fontSize: 18, lineHeight: 1 }}>×</button>
          </div>
          <div style={{ maxHeight: 520, overflowY: 'auto', scrollbarWidth: 'none', padding: '4px 16px 16px' }}>{children}</div>
        </div>
      </div>
    </React.Fragment>
  );
}

/* ── длительность набора треков ──────────────────────────────── */
const PE_secs = (d) => { const p = String(d).split(':'); return p.length === 2 ? (+p[0]) * 60 + (+p[1] || 0) : 0; };
const PE_sumMin = (items) => Math.max(1, Math.round(items.reduce((a, t) => a + PE_secs(t.dur), 0) / 60));
const PE_PL_COLORS = [['var(--c-sky)', 'var(--t-sky)'], ['var(--c-grape)', 'var(--t-grape)'], ['var(--c-mint)', 'var(--t-mint)'], ['var(--c-leaf)', 'var(--t-leaf)']];

/* строка выбора трека (чекбокс) */
function PETrackRow({ tr, on, onClick }) {
  return (
    <button className="pbtn" onClick={onClick} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 13px', cursor: 'pointer', textAlign: 'left', borderRadius: 14, border: on ? '1.5px solid var(--c-coral)' : '1.5px solid var(--p-border)', background: on ? 'var(--t-coral)' : 'var(--p-bg)' }}>
      <span style={{ width: 38, height: 38, borderRadius: 11, flexShrink: 0, background: on ? 'var(--c-coral)' : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-mute)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>{tr.up ? <IconWave size={17} /> : <IconPlay size={16} />}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
          <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{tr.name}</div>
          {tr.up && <span style={{ flexShrink: 0, padding: '1px 6px', borderRadius: 6, background: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: QS, fontWeight: 800, fontSize: 9.5 }}>файл</span>}
        </div>
        <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12, color: 'var(--p-mute)' }}>{tr.by}{tr.dur !== '—' ? ` · ${tr.dur}` : ''}</div>
      </div>
      <span style={{ width: 24, height: 24, flexShrink: 0, borderRadius: 8, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', border: on ? 'none' : '1.5px solid var(--p-border)', background: on ? 'var(--c-coral)' : 'transparent', color: '#fff' }}>{on && <IconCheck size={16} />}</span>
    </button>
  );
}

/* ── выбор плейлиста практики ────────────────────────────────── */
function PlaylistSheet({ preset, custom, extra, onCommit, onAddPlaylist, onClose }) {
  const [tab, setTab] = useState(custom ? 'own' : 'ready');
  const [selPreset, setSelPreset] = useState(preset || null);
  const [uploads, setUploads] = useState(() => (custom ? custom.items.filter((t) => t.up) : []));
  const [selIds, setSelIds] = useState(() => (custom ? custom.items.map((t) => t.id) : []));
  const [plName, setPlName] = useState(custom ? custom.name : '');
  const [query, setQuery] = useState('');
  // создание нового готового плейлиста
  const [creating, setCreating] = useState(false);
  const [newName, setNewName] = useState('');
  const [newSel, setNewSel] = useState([]);
  const fileRef = useRef(null);

  const allPlaylists = [...PE_PLAYLISTS, ...extra];
  const pool = [...PE_TRACKS, ...uploads];
  const q = query.trim().toLowerCase();
  const filteredPool = q ? pool.filter((t) => (t.name + ' ' + (t.by || '')).toLowerCase().includes(q)) : pool;
  const selectedItems = pool.filter((t) => selIds.includes(t.id));

  const toggleSel = (id) => setSelIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  const toggleNew = (id) => setNewSel((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

  const onFiles = (e) => {
    const files = [...(e.target.files || [])];
    if (!files.length) return;
    const added = files.map((f, i) => ({ id: 'up-' + Date.now() + '-' + i, name: f.name.replace(/\.[^.]+$/, ''), by: 'Загружено с устройства', dur: '—', up: true }));
    setUploads((prev) => [...prev, ...added]);
    setSelIds((prev) => [...prev, ...added.map((a) => a.id)]);
    e.target.value = '';
  };

  const createPlaylist = () => {
    const items = PE_TRACKS.filter((t) => newSel.includes(t.id));
    const [c, t] = PE_PL_COLORS[extra.length % PE_PL_COLORS.length];
    const pl = { id: 'pl-' + Date.now(), name: newName.trim() || 'Новый плейлист', tracks: items.length, min: PE_sumMin(items), c, t };
    onAddPlaylist(pl);
    setSelPreset(pl.id);
    setCreating(false); setNewName(''); setNewSel([]);
  };

  const commit = () => {
    if (tab === 'ready') { onCommit({ preset: selPreset, custom: null }); return; }
    if (selIds.length === 0) { onCommit({ preset: null, custom: null }); return; }
    onCommit({ preset: null, custom: { name: plName.trim() || 'Свой набор', items: selectedItems } });
  };

  const seg = (id, label) => (
    <button className="pbtn" onClick={() => setTab(id)} style={{
      flex: 1, height: 38, borderRadius: 11, border: 'none', cursor: 'pointer',
      background: tab === id ? 'var(--p-elev)' : 'transparent',
      color: tab === id ? 'var(--p-ink)' : 'var(--p-mute)',
      fontFamily: QS, fontWeight: 800, fontSize: 13.5,
      boxShadow: tab === id ? '0 2px 8px -3px rgba(20,14,30,0.35)' : 'none',
    }}>{label}</button>
  );

  return (
    <PESheet title="Плейлист на практику" onClose={onClose}>
      {/* переключатель режимов */}
      <div style={{ display: 'flex', gap: 4, padding: 4, borderRadius: 14, background: 'var(--p-ctrl)', marginBottom: 14 }}>
        {seg('ready', 'Готовые')}
        {seg('own', 'Свой набор')}
      </div>

      {tab === 'ready' ? (
        creating ? (
          /* форма нового плейлиста */
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <button className="pbtn" onClick={() => setCreating(false)} style={{ width: 34, height: 34, borderRadius: 10, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-soft)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconBack size={18} /></button>
              <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 15.5, color: 'var(--p-ink)' }}>Новый плейлист</div>
            </div>
            <input value={newName} onChange={(e) => setNewName(e.target.value)} placeholder="Название плейлиста" style={peInput} autoFocus />
            <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 12, color: 'var(--p-mute)', padding: '0 2px' }}>Треки в плейлисте</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {PE_TRACKS.map((tr) => <PETrackRow key={tr.id} tr={tr} on={newSel.includes(tr.id)} onClick={() => toggleNew(tr.id)} />)}
            </div>
            <button className="pbtn" onClick={createPlaylist} disabled={newSel.length === 0} style={{ marginTop: 4, width: '100%', height: 48, borderRadius: 14, border: 'none', cursor: newSel.length ? 'pointer' : 'default', background: newSel.length ? 'var(--c-coral)' : 'var(--p-ctrl)', color: newSel.length ? '#fff' : 'var(--p-faint)', fontFamily: QS, fontWeight: 800, fontSize: 15 }}>
              {newSel.length ? `Создать · ${newSel.length} трек.` : 'Выберите треки'}
            </button>
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {allPlaylists.map((pl) => {
              const on = selPreset === pl.id;
              return (
                <button key={pl.id} className="pbtn" onClick={() => setSelPreset(on ? null : pl.id)} style={{
                  display: 'flex', alignItems: 'center', gap: 13, padding: '13px 14px', cursor: 'pointer', textAlign: 'left',
                  borderRadius: 18, border: on ? `2px solid ${pl.c}` : '2px solid var(--p-border)', background: on ? pl.t : 'var(--p-bg)',
                }}>
                  <span style={{ width: 46, height: 46, borderRadius: 13, flexShrink: 0, background: pl.c, color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconMusic size={22} /></span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 15.5, color: 'var(--p-ink)' }}>{pl.name}</div>
                    <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12.5, color: 'var(--p-mute)' }}>{pl.tracks} треков · {pl.min} мин</div>
                  </div>
                  {on && <span style={{ color: pl.c }}><IconCheck size={22} /></span>}
                </button>
              );
            })}
            <button className="pbtn" onClick={() => setCreating(true)} style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, height: 50, cursor: 'pointer', borderRadius: 16, border: '1.5px dashed var(--c-coral)', background: 'transparent', color: 'var(--c-coral)', fontFamily: QS, fontWeight: 800, fontSize: 14.5 }}><IconPlus size={18} /> Добавить плейлист</button>
          </div>
        )
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div style={{ padding: '11px 13px', borderRadius: 13, background: 'var(--t-coral)', fontFamily: NS, fontWeight: 700, fontSize: 12.5, color: 'var(--p-soft)', lineHeight: 1.45 }}>
            Соберите треки под эту практику. Набор сохранится <b>только здесь</b> — в общие плейлисты он не попадёт.
          </div>
          <div style={{ position: 'relative' }}>
            <span style={{ position: 'absolute', left: 12, top: '50%', transform: 'translateY(-50%)', color: 'var(--p-mute)', pointerEvents: 'none' }}><IconSearch size={17} /></span>
            <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Поиск трека" style={{ ...peInput, paddingLeft: 38 }} />
          </div>

          <input ref={fileRef} type="file" accept="audio/*" multiple onChange={onFiles} style={{ display: 'none' }} />
          <button className="pbtn" onClick={() => fileRef.current && fileRef.current.click()} style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 12, padding: '12px 13px', cursor: 'pointer', borderRadius: 14, border: '1.5px dashed var(--p-faint)', background: 'var(--p-ctrl)', textAlign: 'left' }}>
            <span style={{ width: 38, height: 38, borderRadius: 11, flexShrink: 0, background: 'var(--p-elev)', color: 'var(--c-coral)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconUpload size={19} /></span>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)' }}>Загрузить с компьютера</div>
              <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12, color: 'var(--p-mute)' }}>MP3, WAV, M4A — свои аудиофайлы</div>
            </div>
            <span style={{ color: 'var(--p-faint)' }}><IconPlus size={18} /></span>
          </button>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {filteredPool.map((tr) => <PETrackRow key={tr.id} tr={tr} on={selIds.includes(tr.id)} onClick={() => toggleSel(tr.id)} />)}
            {filteredPool.length === 0 && <div style={{ padding: '18px 4px', textAlign: 'center', fontFamily: NS, fontWeight: 600, fontSize: 13, color: 'var(--p-mute)' }}>Ничего не найдено</div>}
          </div>
        </div>
      )}

      {/* нижняя кнопка */}
      {!(tab === 'ready' && creating) && (
        <button className="pbtn" onClick={commit} style={{ marginTop: 16, width: '100%', height: 50, borderRadius: 15, border: 'none', cursor: 'pointer', background: 'var(--c-coral)', color: '#fff', fontFamily: QS, fontWeight: 800, fontSize: 15.5, boxShadow: '0 14px 28px -14px rgba(255,111,97,0.95)' }}>
          {tab === 'own' && selIds.length > 0 ? `Готово · ${selIds.length} трек. · ${PE_sumMin(selectedItems)} мин` : 'Готово'}
        </button>
      )}
    </PESheet>
  );
}

/* ── выбор музыки на шаг цикла ───────────────────────────────── */
function MusicSheet({ current, onPick, onClose }) {
  return (
    <PESheet title="Музыка на шаг" onClose={onClose}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        <button className="pbtn" onClick={() => onPick(null)} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '11px 13px', cursor: 'pointer', textAlign: 'left', borderRadius: 14, border: '1.5px solid var(--p-border)', background: !current ? 'var(--t-coral)' : 'var(--p-bg)' }}>
          <span style={{ width: 38, height: 38, borderRadius: 11, flexShrink: 0, background: 'var(--p-ctrl)', color: 'var(--p-mute)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 17 }}>🔇</span>
          <span style={{ flex: 1, fontFamily: QS, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)' }}>Без музыки</span>
          {!current && <span style={{ color: 'var(--c-coral)' }}><IconCheck size={20} /></span>}
        </button>
        {PE_TRACKS.map((tr) => {
          const on = current === tr.id;
          return (
            <button key={tr.id} className="pbtn" onClick={() => onPick(tr.id)} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 13px', cursor: 'pointer', textAlign: 'left', borderRadius: 14, border: on ? '1.5px solid var(--c-coral)' : '1.5px solid var(--p-border)', background: on ? 'var(--t-coral)' : 'var(--p-bg)' }}>
              <span style={{ width: 38, height: 38, borderRadius: 11, flexShrink: 0, background: on ? 'var(--c-coral)' : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-mute)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconPlay size={16} /></span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{tr.name}</div>
                <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12, color: 'var(--p-mute)' }}>{tr.by} · {tr.dur}</div>
              </div>
              {on && <span style={{ color: 'var(--c-coral)' }}><IconCheck size={20} /></span>}
            </button>
          );
        })}
      </div>
    </PESheet>
  );
}

const trackName = (id) => { const t = PE_TRACKS.find((x) => x.id === id); return t ? t.name : null; };

/* ── выбор звука между повторами ─────────────────────────────── */
function PESoundPicker({ value, onChange }) {
  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
      {PE_SOUNDS.map((s) => {
        const on = s.id === value;
        return (
          <button key={s.id} className="pbtn" onClick={() => onChange(s.id)} style={{
            display: 'inline-flex', alignItems: 'center', gap: 7, padding: '9px 13px', cursor: 'pointer',
            borderRadius: 999, border: on ? '1.5px solid var(--c-grape)' : '1.5px solid var(--p-border)',
            background: on ? 'var(--t-grape)' : 'var(--p-bg)', color: on ? 'var(--c-grape)' : 'var(--p-mute)',
            fontFamily: QS, fontWeight: 800, fontSize: 13.5,
          }}><span style={{ fontSize: 15 }}>{s.e}</span>{s.label}</button>
        );
      })}
    </div>
  );
}

/* ── выбор анимации между повторами (с превью) ───────────────── */
function PEAnimPicker({ value, onChange }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 9 }}>
      {PE_ANIMS.map((a) => {
        const on = a.id === value;
        return (
          <button key={a.id} className="pbtn" onClick={() => onChange(a.id)} style={{
            cursor: 'pointer', padding: '14px 6px 10px', borderRadius: 16,
            border: on ? '2px solid var(--c-grape)' : '1.5px solid var(--p-border)',
            background: on ? 'var(--t-grape)' : 'var(--p-bg)',
            display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10,
          }}>
            <div style={{ height: 40, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <div className={a.wave ? 'peWave' : undefined} style={{ width: 30, height: 30, borderRadius: '50%', background: a.id === 'none' ? 'transparent' : (on ? 'var(--c-grape)' : 'var(--p-faint)'), color: on ? 'var(--c-grape)' : 'var(--p-faint)', border: a.id === 'none' ? '2px dashed var(--p-faint)' : 'none', animation: a.anim }} />
            </div>
            <span style={{ fontFamily: QS, fontWeight: 800, fontSize: 11.5, lineHeight: 1.2, textAlign: 'center', color: on ? 'var(--c-grape)' : 'var(--p-mute)' }}>{a.label}</span>
          </button>
        );
      })}
    </div>
  );
}

/* ── редактор: Простой ───────────────────────────────────────── */
function SimpleEditor({ part, set }) {
  return (
    <div style={{ ...peCardBox, padding: '15px 15px 17px' }}>
      <div style={{ ...peLabel, color: 'var(--c-sky)' }}><IconTimer size={15} /> Время выполнения</div>
      <PEDuration sec={part.simpleSec} onChange={(v) => set({ simpleSec: v })} accent="var(--c-sky)" />
    </div>
  );
}

/* ── строка шага цикла ───────────────────────────────────────── */
function StepRow({ step, idx, onChange, onRemove, onPickMusic, canRemove }) {
  const subLabel = { fontFamily: QS, fontWeight: 800, fontSize: 11, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.06em', margin: '0 0 8px', display: 'flex', alignItems: 'center', gap: 6 };
  return (
    <div style={{ borderRadius: 18, background: 'var(--p-bg)', border: '1.5px solid var(--p-border)', padding: '13px 13px 15px' }}>
      {/* заголовок шага */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginBottom: 13 }}>
        <span style={{ width: 26, height: 26, borderRadius: 9, flexShrink: 0, background: 'var(--c-grape)', color: '#fff', fontFamily: SG, fontWeight: 600, fontSize: 13, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>{idx + 1}</span>
        <input value={step.name} onChange={(e) => onChange({ name: e.target.value })} placeholder={`Название шага ${idx + 1}`} style={{ flex: 1, minWidth: 0, height: 40, padding: '0 12px', borderRadius: 12, border: '1.5px solid var(--p-border)', background: 'var(--p-ctrl)', outline: 'none', fontFamily: NS, fontWeight: 700, fontSize: 15, color: 'var(--p-ink)' }} />
        {canRemove && <button className="pbtn" onClick={onRemove} aria-label="Удалить шаг" style={{ width: 36, height: 36, borderRadius: 11, flexShrink: 0, border: 'none', cursor: 'pointer', background: 'var(--t-coral)', color: 'var(--c-coral)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconTrash size={16} /></button>}
      </div>

      {/* время */}
      <div style={{ marginBottom: 13 }}>
        <div style={subLabel}><IconClock size={13} style={{ color: 'var(--c-grape)' }} /><span>Длительность шага</span></div>
        <PEDuration sec={step.sec} onChange={(v) => onChange({ sec: Math.max(1, v) })} accent="var(--c-grape)" secStep={1} />
      </div>

      {/* музыка */}
      <div style={{ marginBottom: 15 }}>
        <button className="pbtn" onClick={onPickMusic} style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 8, height: 46, padding: '0 14px', cursor: 'pointer', borderRadius: 12, border: '1.5px solid var(--p-border)', background: step.track ? 'var(--t-coral)' : 'var(--p-ctrl)', textAlign: 'left' }}>
          <IconMusic size={16} style={{ color: step.track ? 'var(--c-coral)' : 'var(--p-faint)', flexShrink: 0 }} />
          <span style={{ flex: 1, minWidth: 0, fontFamily: QS, fontWeight: 700, fontSize: 14, color: step.track ? 'var(--c-coral)' : 'var(--p-mute)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{step.track ? trackName(step.track) : 'Музыка на шаг'}</span>
        </button>
      </div>

      {/* звук в начале */}
      <div style={{ marginBottom: 14 }}>
        <div style={subLabel}><span>🔔 Звук в начале</span></div>
        <PESoundPicker value={step.startSound} onChange={(v) => onChange({ startSound: v })} />
      </div>

      {/* звук в конце */}
      <div style={{ marginBottom: 14 }}>
        <div style={subLabel}><span>🔕 Звук в конце</span></div>
        <PESoundPicker value={step.endSound} onChange={(v) => onChange({ endSound: v })} />
      </div>

      {/* анимация во время */}
      <div>
        <div style={subLabel}><span>✨ Анимация во время</span></div>
        <PEAnimPicker value={step.anim} onChange={(v) => onChange({ anim: v })} />
      </div>
    </div>
  );
}

/* ── редактор: Цикл ──────────────────────────────────────────── */
function CycleEditor({ part, set, onPickMusic }) {
  const setStep = (id, patch) => set({ steps: part.steps.map((s) => s.id === id ? { ...s, ...patch } : s) });
  const addStep = () => set({ steps: [...part.steps, newStep()] });
  const rmStep = (id) => set({ steps: part.steps.filter((s) => s.id !== id) });
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      {/* шаги цикла */}
      <div style={{ ...peCardBox, padding: '15px 14px 16px' }}>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 7, marginBottom: 12 }}>
          <div style={{ ...peLabel, marginBottom: 0, color: 'var(--c-grape)' }}><IconLayers size={15} /> Шаги цикла</div>
          <span style={{ fontFamily: QS, fontWeight: 800, fontSize: 12, color: 'var(--p-faint)' }}>{part.steps.length}</span>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 9 }}>
          {part.steps.map((s, i) => (
            <StepRow key={s.id} step={s} idx={i} canRemove={part.steps.length > 1}
              onChange={(patch) => setStep(s.id, patch)} onRemove={() => rmStep(s.id)} onPickMusic={() => onPickMusic(s.id)} />
          ))}
        </div>
        <button className="pbtn" onClick={addStep} style={{ marginTop: 11, width: '100%', height: 44, cursor: 'pointer', borderRadius: 13, border: '1.5px dashed var(--c-grape)', background: 'transparent', color: 'var(--c-grape)', fontFamily: QS, fontWeight: 800, fontSize: 14, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 7 }}><IconPlus size={17} /> Добавить шаг</button>
      </div>

      {/* повтор цикла */}
      <div style={{ ...peCardBox, padding: '15px 15px 17px' }}>
        <div style={{ ...peLabel, color: 'var(--c-grape)' }}><IconRepeat size={15} /> Повтор цикла</div>
        <PESeg options={[{ id: 'count', label: 'Количество раз' }, { id: 'time', label: 'По времени' }]} value={part.repeatMode} onChange={(v) => set({ repeatMode: v })} accent="var(--c-grape)" />
        <div style={{ marginTop: 14 }}>
          {part.repeatMode === 'count'
            ? <PEStepper value={part.repeatCount} onChange={(v) => set({ repeatCount: v })} min={1} max={99} suffix="раз" accent="var(--c-grape)" />
            : <PEStepper value={part.repeatMin} onChange={(v) => set({ repeatMin: v })} min={1} max={120} suffix="мин" accent="var(--c-grape)" />}
        </div>
      </div>
    </div>
  );
}

/* ── редактор: Метроном ──────────────────────────────────────── */
const tempoName = (b) => b < 60 ? 'Ларго' : b < 76 ? 'Адажио' : b < 108 ? 'Анданте' : b < 132 ? 'Модерато' : b < 168 ? 'Аллегро' : 'Престо';
function MetroEditor({ part, set }) {
  const beatSec = 60 / part.bpm;
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <div style={{ ...peCardBox, padding: '16px 15px 18px' }}>
        <div style={{ ...peLabel, color: 'var(--c-goldink)' }}><IconMusic size={15} /> Ударов в минуту</div>
        {/* превью пульса */}
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14, padding: '4px 0 14px' }}>
          <div style={{ width: 18, height: 18, borderRadius: '50%', background: 'var(--c-sun)', animation: `pePulse ${beatSec}s ease-in-out infinite` }} />
          <div style={{ textAlign: 'center' }}>
            <div style={{ fontFamily: SG, fontWeight: 600, fontSize: 38, lineHeight: 1, color: 'var(--p-ink)', fontVariantNumeric: 'tabular-nums' }}>{part.bpm}</div>
            <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 12, color: 'var(--c-goldink)', marginTop: 3 }}>{tempoName(part.bpm)}</div>
          </div>
          <div style={{ width: 18, height: 18, borderRadius: '50%', background: 'var(--c-sun)', animation: `pePulse ${beatSec}s ease-in-out infinite`, animationDelay: `${beatSec / 2}s` }} />
        </div>
        <PEStepper value={part.bpm} onChange={(v) => set({ bpm: v })} min={40} max={208} step={2} suffix="BPM" accent="var(--c-goldink)" />
        <div style={{ display: 'flex', gap: 6, marginTop: 10 }}>
          {[50, 60, 90, 120].map((b) => (
            <button key={b} className="pbtn" onClick={() => set({ bpm: b })} style={{ flex: 1, height: 32, cursor: 'pointer', borderRadius: 9, border: 'none', background: part.bpm === b ? 'var(--c-goldink)' : 'var(--p-ctrl)', color: part.bpm === b ? '#fff' : 'var(--p-mute)', fontFamily: SG, fontWeight: 600, fontSize: 13 }}>{b}</button>
          ))}
        </div>
      </div>

      <div style={{ ...peCardBox, padding: '15px 15px 17px' }}>
        <div style={{ ...peLabel, color: 'var(--c-goldink)' }}><IconClock size={15} /> Длительность</div>
        <PESeg options={[{ id: 'beats', label: 'Удары' }, { id: 'min', label: 'Минуты' }]} value={part.endMode} onChange={(v) => set({ endMode: v })} accent="var(--c-goldink)" />
        <div style={{ marginTop: 14 }}>
          {part.endMode === 'beats'
            ? <PEStepper value={part.beats} onChange={(v) => set({ beats: v })} min={4} max={500} step={4} suffix="ударов" accent="var(--c-goldink)" />
            : <PEStepper value={part.metroMin} onChange={(v) => set({ metroMin: v })} min={1} max={90} suffix="мин" accent="var(--c-goldink)" />}
        </div>
      </div>
    </div>
  );
}

/* ── редактор части (push-экран) ─────────────────────────────── */
function PartEditor({ part, index, onChange, onDelete, onClose }) {
  const [music, setMusic] = useState(null); // stepId или null
  const set = (patch) => onChange({ ...part, ...patch });
  const tp = PE_typeById(part.type);
  const curStep = music ? part.steps.find((s) => s.id === music) : null;

  return (
    <div data-screen-label={`Часть ${index + 1}`} style={{
      position: 'absolute', inset: 0, zIndex: 30, background: 'var(--p-bg)',
      animation: 'peSlideIn .28s cubic-bezier(.2,.9,.25,1)', display: 'flex', flexDirection: 'column',
    }}>
      {/* шапка */}
      <div style={{ flexShrink: 0, paddingTop: 56, background: 'var(--p-chrome)', backdropFilter: 'blur(12px)', WebkitBackdropFilter: 'blur(12px)', borderBottom: '1px solid var(--p-border)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '4px 12px 11px' }}>
          <button className="pbtn tap" onClick={onClose} style={{ width: 40, height: 40, borderRadius: 13, flexShrink: 0, border: '1px solid var(--p-border)', background: 'var(--p-elev)', color: 'var(--p-ink)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconBack size={20} /></button>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 11.5, color: tp.c, textTransform: 'uppercase', letterSpacing: '0.05em' }}>{tp.e} {tp.label}</div>
            <h1 style={{ margin: 0, fontFamily: QS, fontWeight: 800, fontSize: 19, letterSpacing: '-0.01em', color: 'var(--p-ink)', lineHeight: 1.15, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{part.name || `Часть ${index + 1}`}</h1>
          </div>
          <button className="pbtn tap" onClick={onClose} style={{ flexShrink: 0, padding: '0 16px', height: 40, borderRadius: 13, border: 'none', cursor: 'pointer', background: 'var(--p-ink)', color: 'var(--p-bg)', fontFamily: QS, fontWeight: 800, fontSize: 14 }}>Готово</button>
        </div>
      </div>

      {/* контент */}
      <div className="pscroll" style={{ position: 'relative', flex: 1, padding: '16px 14px 40px', display: 'flex', flexDirection: 'column', gap: 18 }}>
        {/* основное */}
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <PEField label="Название части">
            <input value={part.name} onChange={(e) => set({ name: e.target.value })} placeholder={`Например, Разминка`} style={peInput} />
          </PEField>
          <PEField label="Описание" optional>
            <textarea value={part.desc} onChange={(e) => set({ desc: e.target.value })} rows={3} placeholder="Что делает практикующий на этой части…" style={{ ...peInput, height: 'auto', padding: '12px 15px', lineHeight: 1.5, resize: 'none', fontWeight: 500 }} />
          </PEField>
        </div>

        {/* медиа */}
        <div>
          <PESection><IconImage size={15} /> Медиа</PESection>
          <div style={{ marginTop: 11, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <PEPhotoSlot filled={part.photo} onToggle={() => set({ photo: !part.photo })} />
            <PEMediaRow icon={<IconVideo size={18} />} label="Видео" value={part.video ? 'flow.mp4 · 2:30' : null} placeholder="Демонстрация асаны или потока" onToggle={() => set({ video: part.video ? null : true })} accent="var(--c-coral)" />
            <PEMediaRow icon={<IconAudio size={18} />} label="Аудио" value={part.audio ? 'cue.mp3 · 1:12' : null} placeholder="Голосовая подсказка или дыхание" onToggle={() => set({ audio: part.audio ? null : true })} accent="var(--c-mint)" />
          </div>
        </div>

        {/* тип части */}
        <div>
          <PESection>Вид части</PESection>
          <div style={{ marginTop: 11, display: 'flex', flexDirection: 'column', gap: 9 }}>
            {PE_TYPES.map((t) => {
              const on = t.id === part.type;
              return (
                <button key={t.id} className="pbtn" onClick={() => set({ type: t.id })} style={{
                  display: 'flex', alignItems: 'center', gap: 13, padding: '13px 14px', cursor: 'pointer', textAlign: 'left',
                  borderRadius: 17, border: on ? `2px solid ${t.c}` : '1.5px solid var(--p-border)', background: on ? t.t : 'var(--p-elev)',
                }}>
                  <span style={{ width: 44, height: 44, borderRadius: 13, flexShrink: 0, fontSize: 22, background: on ? t.c : 'var(--p-ctrl)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>{t.e}</span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 16, color: on ? t.c : 'var(--p-ink)' }}>{t.label}</div>
                    <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12.5, color: 'var(--p-mute)' }}>{t.desc}</div>
                  </div>
                  <span style={{ width: 22, height: 22, borderRadius: '50%', flexShrink: 0, border: on ? 'none' : '2px solid var(--p-border)', background: on ? t.c : 'transparent', color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>{on && <IconCheck size={15} />}</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* конфиг по типу */}
        <div>
          <PESection>Настройка · {tp.label}</PESection>
          <div style={{ marginTop: 11 }}>
            {part.type === 'simple' && <SimpleEditor part={part} set={set} />}
            {part.type === 'cycle' && <CycleEditor part={part} set={set} onPickMusic={setMusic} />}
            {part.type === 'metro' && <MetroEditor part={part} set={set} />}
          </div>
        </div>

        {/* удалить */}
        <button className="pbtn tap" onClick={onDelete} style={{ marginTop: 4, width: '100%', padding: '15px', cursor: 'pointer', borderRadius: 18, border: '1.5px dashed var(--c-coral)', background: 'transparent', color: 'var(--c-coral)', fontFamily: QS, fontWeight: 800, fontSize: 15, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}><IconTrash size={18} /> Удалить часть</button>
      </div>

      {music !== null && curStep && (
        <MusicSheet current={curStep.track} onClose={() => setMusic(null)}
          onPick={(tid) => { onChange({ ...part, steps: part.steps.map((s) => s.id === music ? { ...s, track: tid } : s) }); setMusic(null); }} />
      )}
    </div>
  );
}

/* ══════════ ГЛАВНЫЙ ЭКРАН ══════════ */
function PracticeEditorScreen({ dark = false, onBack }) {
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [kind, setKind] = useState('hatha');
  const [tags, setTags] = useState([]);
  const [tagDraft, setTagDraft] = useState('');
  const [playlist, setPlaylist] = useState(null);
  const [customPl, setCustomPl] = useState(null); // { name, items:[{id,name,by,dur,up}] } — только для этой практики
  const [extraPls, setExtraPls] = useState([]); // созданные пользователем готовые плейлисты
  const [parts, setParts] = useState([]);
  const [editing, setEditing] = useState(null); // part id
  const [sheet, setSheet] = useState(null); // 'playlist'
  const [toast, setToast] = useState(null);

  const addTag = () => {
    const t = tagDraft.trim().replace(/^#/, '');
    if (t && !tags.includes(t)) setTags([...tags, t]);
    setTagDraft('');
  };
  const addPart = () => { const p = newPart(); setParts((prev) => [...prev, p]); setEditing(p.id); };
  const updatePart = (np) => setParts((prev) => prev.map((p) => p.id === np.id ? np : p));
  const delPart = (id) => { setParts((prev) => prev.filter((p) => p.id !== id)); setEditing(null); };

  const editingPart = parts.find((p) => p.id === editing);
  const pl = [...PE_PLAYLISTS, ...extraPls].find((x) => x.id === playlist);
  const hasCustom = customPl && customPl.items.length > 0;
  const k = PE_kindById(kind);
  const canSave = name.trim().length > 0 && parts.length > 0;

  const fireToast = (msg) => { setToast(msg); clearTimeout(fireToast._t); fireToast._t = setTimeout(() => setToast(null), 2000); };

  return (
    <div className={'ylp' + (dark ? ' dark' : '')} style={{ height: '100%' }}>
      <div data-screen-label="Новая практика" className="pscroll">
        {/* шапка */}
        <div style={{ position: 'sticky', top: 0, zIndex: 20, paddingTop: 56, background: 'var(--p-chrome)', backdropFilter: 'blur(12px)', WebkitBackdropFilter: 'blur(12px)', borderBottom: '1px solid var(--p-border)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '2px 12px 10px' }}>
            <button onClick={onBack} className="pbtn tap" aria-label="Назад" style={{ width: 40, height: 40, borderRadius: 13, flexShrink: 0, border: '1px solid var(--p-border)', background: 'var(--p-elev)', color: 'var(--p-ink)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconBack size={20} /></button>
            <h1 style={{ margin: 0, flex: 1, fontFamily: QS, fontWeight: 800, fontSize: 21, letterSpacing: '-0.02em', color: 'var(--p-ink)' }}>Новая практика</h1>
            <span style={{ padding: '5px 11px', borderRadius: 999, background: 'var(--p-ctrl)', color: 'var(--p-mute)', fontFamily: QS, fontWeight: 800, fontSize: 11.5 }}>Черновик</span>
          </div>
          {/* таб-бар */}
          <div style={{ display: 'flex', gap: 22, padding: '0 16px' }}>
            <div style={{ paddingBottom: 9, borderBottom: '2.5px solid var(--c-coral)', color: 'var(--p-ink)', fontFamily: QS, fontWeight: 800, fontSize: 15 }}>Практика</div>
          </div>
        </div>

        <div style={{ padding: '18px 14px 130px', display: 'flex', flexDirection: 'column', gap: 22 }}>
          {/* ОСНОВНОЕ */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <PEField label="Название">
              <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Например, Утренняя крийя пробуждения" style={peInput} />
            </PEField>

            <PEField label="Код" optional>
              <input value={code} onChange={(e) => setCode(e.target.value.toUpperCase())} placeholder="YL-024" style={{ ...peInput, fontFamily: SG, fontWeight: 600, letterSpacing: '0.04em' }} />
            </PEField>

            <PEField label="Вид йоги">
              <div className="hrow" style={{ display: 'flex', gap: 8, overflowX: 'auto', paddingBottom: 2, margin: '0 -14px', padding: '0 14px 2px' }}>
                {PE_KINDS.map((kk) => {
                  const on = kk.id === kind;
                  return (
                    <button key={kk.id} className="pbtn" onClick={() => setKind(kk.id)} style={{ flexShrink: 0, display: 'inline-flex', alignItems: 'center', gap: 6, padding: '9px 15px', cursor: 'pointer', whiteSpace: 'nowrap', borderRadius: 999, border: on ? `1.5px solid ${kk.c}` : '1.5px solid var(--p-border)', background: on ? kk.t : 'var(--p-bg)', color: on ? kk.c : 'var(--p-mute)', fontFamily: QS, fontWeight: 800, fontSize: 14 }}>
                      <span style={{ fontSize: 15 }}>{kk.e}</span>{kk.label}
                    </button>
                  );
                })}
              </div>
            </PEField>

            <PEField label="Теги" optional>
              <input value={tagDraft} onChange={(e) => setTagDraft(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(); } }} placeholder="Введите тег и нажмите Enter" style={peInput} />
              {tags.length > 0 && (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7, marginTop: 10 }}>
                  {tags.map((t) => (
                    <span key={t} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '6px 8px 6px 12px', borderRadius: 9, background: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: QS, fontWeight: 700, fontSize: 13 }}>
                      #{t}
                      <button className="pbtn" onClick={() => setTags(tags.filter((x) => x !== t))} aria-label="Убрать тег" style={{ width: 18, height: 18, borderRadius: '50%', border: 'none', cursor: 'pointer', background: 'var(--p-border)', color: 'var(--p-mute)', fontSize: 13, lineHeight: 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>×</button>
                    </span>
                  ))}
                </div>
              )}
            </PEField>

            <PEField label="Плейлист на практику" optional>
              {pl ? (
                <div style={{ display: 'flex', alignItems: 'center', gap: 13, padding: '12px 13px', borderRadius: 16, border: `1.5px solid ${pl.c}`, background: pl.t }}>
                  <span style={{ width: 44, height: 44, borderRadius: 13, flexShrink: 0, background: pl.c, color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconMusic size={21} /></span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 15, color: 'var(--p-ink)' }}>{pl.name}</div>
                    <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12.5, color: 'var(--p-mute)' }}>{pl.tracks} треков · {pl.min} мин</div>
                  </div>
                  <button className="pbtn" onClick={() => setSheet('playlist')} style={{ flexShrink: 0, padding: '0 13px', height: 36, borderRadius: 11, border: 'none', cursor: 'pointer', background: 'var(--p-elev)', color: pl.c, fontFamily: QS, fontWeight: 800, fontSize: 13 }}>Сменить</button>
                </div>
              ) : hasCustom ? (
                <div style={{ display: 'flex', alignItems: 'center', gap: 13, padding: '12px 13px', borderRadius: 16, border: '1.5px solid var(--c-coral)', background: 'var(--t-coral)' }}>
                  <span style={{ width: 44, height: 44, borderRadius: 13, flexShrink: 0, background: 'var(--c-coral)', color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconMusic size={21} /></span>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
                      <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 15, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{customPl.name}</div>
                      <span style={{ flexShrink: 0, padding: '2px 7px', borderRadius: 7, background: 'var(--p-elev)', color: 'var(--c-coral)', fontFamily: QS, fontWeight: 800, fontSize: 10 }}>Только тут</span>
                    </div>
                    <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 12.5, color: 'var(--p-mute)' }}>{customPl.items.length} треков · {PE_sumMin(customPl.items)} мин</div>
                  </div>
                  <button className="pbtn" onClick={() => setSheet('playlist')} style={{ flexShrink: 0, padding: '0 13px', height: 36, borderRadius: 11, border: 'none', cursor: 'pointer', background: 'var(--p-elev)', color: 'var(--c-coral)', fontFamily: QS, fontWeight: 800, fontSize: 13 }}>Править</button>
                </div>
              ) : (
                <button className="pbtn" onClick={() => setSheet('playlist')} style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 12, height: 56, padding: '0 14px', cursor: 'pointer', borderRadius: 16, border: '1.5px dashed var(--p-faint)', background: 'var(--p-ctrl)', textAlign: 'left' }}>
                  <span style={{ width: 38, height: 38, borderRadius: 11, flexShrink: 0, background: 'var(--p-elev)', color: 'var(--p-mute)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconMusic size={19} /></span>
                  <span style={{ flex: 1, fontFamily: QS, fontWeight: 800, fontSize: 15, color: 'var(--p-mute)' }}>Выбрать или собрать</span>
                  <span style={{ color: 'var(--p-faint)' }}><IconChevron size={18} /></span>
                </button>
              )}
            </PEField>
          </div>

          {/* ЧАСТИ */}
          <div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, padding: '0 4px 12px' }}>
              <div style={{ ...peLabel, marginBottom: 0, fontSize: 12 }}><IconLayers size={15} /> Части практики</div>
              <span style={{ fontFamily: QS, fontWeight: 800, fontSize: 12, color: 'var(--p-faint)' }}>{parts.length}</span>
            </div>

            {parts.length === 0 && (
              <div style={{ ...peCardBox, padding: '30px 24px', textAlign: 'center', boxShadow: 'none', borderStyle: 'dashed', background: 'transparent' }}>
                <div style={{ fontSize: 30 }}>🧱</div>
                <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 15, color: 'var(--p-soft)', marginTop: 8 }}>Соберите практику из частей</div>
                <div style={{ fontFamily: NS, fontWeight: 600, fontSize: 13, color: 'var(--p-mute)', marginTop: 4, lineHeight: 1.45 }}>Каждая часть — простой таймер, цикл с повторами или метроном</div>
              </div>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: 11 }}>
              {parts.map((p, i) => {
                const tp = PE_typeById(p.type);
                return (
                  <button key={p.id} className="pbtn tap" onClick={() => setEditing(p.id)} style={{ ...peCardBox, display: 'flex', alignItems: 'center', gap: 12, padding: '13px 13px', cursor: 'pointer', textAlign: 'left', border: '1px solid var(--p-border)' }}>
                    <span style={{ color: 'var(--p-faint)', flexShrink: 0, display: 'inline-flex' }}><IconGrip size={20} /></span>
                    <span style={{ width: 46, height: 46, borderRadius: 14, flexShrink: 0, fontSize: 22, background: tp.t, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>{tp.e}</span>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontFamily: QS, fontWeight: 800, fontSize: 15.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.name || `Часть ${i + 1}`}</div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginTop: 3 }}>
                        <span style={{ fontFamily: QS, fontWeight: 800, fontSize: 12, color: tp.c }}>{tp.label}</span>
                        <span style={{ width: 3, height: 3, borderRadius: '50%', background: 'var(--p-faint)' }} />
                        <span style={{ fontFamily: NS, fontWeight: 700, fontSize: 12.5, color: 'var(--p-mute)' }}>{partSummary(p)}</span>
                      </div>
                      {(p.photo || p.video || p.audio) && (
                        <div style={{ display: 'flex', gap: 5, marginTop: 8 }}>
                          {p.photo && <MediaDot icon={<IconImage size={13} />} c="var(--c-sun)" />}
                          {p.video && <MediaDot icon={<IconVideo size={13} />} c="var(--c-coral)" />}
                          {p.audio && <MediaDot icon={<IconAudio size={13} />} c="var(--c-mint)" />}
                        </div>
                      )}
                    </div>
                    <span style={{ flexShrink: 0, color: 'var(--p-faint)' }}><IconChevron size={18} /></span>
                  </button>
                );
              })}
            </div>

            <button className="pbtn tap" onClick={addPart} style={{ marginTop: 11, width: '100%', padding: '15px', cursor: 'pointer', borderRadius: 18, border: '1.5px dashed var(--c-coral)', background: 'transparent', color: 'var(--c-coral)', fontFamily: QS, fontWeight: 800, fontSize: 15, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}><IconPlus size={19} /> Добавить часть</button>
          </div>
        </div>
      </div>

      {/* нижняя панель сохранения */}
      <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, zIndex: 22, padding: '12px 14px calc(12px + env(safe-area-inset-bottom))', background: 'var(--p-chrome)', backdropFilter: 'blur(14px)', WebkitBackdropFilter: 'blur(14px)', borderTop: '1px solid var(--p-border)', display: 'flex', gap: 10 }}>
        <button onClick={() => fireToast('Черновик сохранён')} className="pbtn" style={{ flexShrink: 0, padding: '0 18px', height: 52, borderRadius: 16, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: QS, fontWeight: 800, fontSize: 14.5 }}>В черновик</button>
        <button onClick={() => canSave && fireToast(`«${name.trim()}» сохранена`)} disabled={!canSave} className="pbtn" style={{ flex: 1, height: 52, borderRadius: 16, border: 'none', cursor: canSave ? 'pointer' : 'default', background: canSave ? 'var(--c-coral)' : 'var(--p-ctrl)', color: canSave ? '#fff' : 'var(--p-faint)', fontFamily: QS, fontWeight: 800, fontSize: 16, boxShadow: canSave ? '0 16px 30px -14px rgba(255,111,97,0.95)' : 'none' }}>Сохранить практику</button>
      </div>

      {/* push-экран части */}
      {editingPart && <PartEditor part={editingPart} index={parts.findIndex((p) => p.id === editing)} onChange={updatePart} onDelete={() => delPart(editing)} onClose={() => setEditing(null)} />}

      {/* шторки */}
      {sheet === 'playlist' && <PlaylistSheet preset={playlist} custom={customPl} extra={extraPls} onAddPlaylist={(pl) => setExtraPls((prev) => [...prev, pl])} onClose={() => setSheet(null)} onCommit={({ preset, custom }) => { setPlaylist(preset); setCustomPl(custom); setSheet(null); }} />}

      {/* тост */}
      {toast && (
        <div className="ptoast" style={{ position: 'absolute', bottom: 84, left: '50%', transform: 'translateX(-50%)', zIndex: 60, padding: '12px 18px', borderRadius: 16, maxWidth: '82%', background: 'var(--p-ink)', color: 'var(--p-bg)', fontFamily: QS, fontWeight: 700, fontSize: 13.5, whiteSpace: 'nowrap', boxShadow: '0 18px 34px -16px rgba(0,0,0,0.6)' }}>{toast}</div>
      )}
    </div>
  );
}

Object.assign(window, { PracticeEditorScreen });
