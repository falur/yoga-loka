// my-practice.jsx — экран «Моя практика» (раздел нижнего меню вместо «Приложения»).
// Библиотека практик сообщества: практики добавляют пользователи, другие могут
// искать их и добавлять к себе. Карточка показывает название · время · теги · кто добавил.
// Экспорт в window: MyPracticeScreen.

const { useState: uMP, useMemo: uMPm } = React;
const QBM = "'Quicksand', sans-serif";
const NSM = "'Nunito Sans', system-ui, sans-serif";

/* ── палитра-акценты для карточек (декоративная, из палитры .ylp) ── */
const MP_TONES = {
  grape:  { c: 'var(--c-grape)',  t: 'var(--t-grape)'  },
  coral:  { c: 'var(--c-coral)',  t: 'var(--t-coral)'  },
  sky:    { c: 'var(--c-sky)',    t: 'var(--t-sky)'    },
  mint:   { c: 'var(--c-mint)',   t: 'var(--t-mint)'   },
  sun:    { c: 'var(--c-goldink)', t: 'var(--t-sun)'   },
  bubble: { c: 'var(--c-bubble)', t: 'var(--t-bubble)' },
  leaf:   { c: 'var(--c-leaf)',   t: 'var(--t-leaf)'   },
};
const MP_TONE_KEYS = Object.keys(MP_TONES);

/* ── «я» как автор практики ────────────────────────────────────── */
const MP_ME = { name: 'Вы', initials: 'Я', color: 'var(--c-leaf)' };

/* ── склонение «часть» по числу ──────────────────────────────── */
const partsWord = (n) => {
  const d = n % 10, dd = n % 100;
  if (d === 1 && dd !== 11) return 'часть';
  if (d >= 2 && d <= 4 && (dd < 12 || dd > 14)) return 'части';
  return 'частей';
};

/* ── библиотека практик сообщества ─────────────────────────────── */
// by — кто добавил; saved — добавлена ли практика «к себе»; min — время, мин.
const MY_PRACTICES = [
  { id: 'm1', name: 'Утренняя крийя пробуждения', code: 'LA-714', min: 25, parts: 5, style: 'kriya', tone: 'grape', saved: true, media: ['video', 'audio', 'timer'],
    tags: ['Энергия', 'Утро', 'Новичкам'],
    by: { name: 'Ананда Деви', initials: 'АД', color: 'var(--c-grape)' } },
  { id: 'm2', name: 'Виньяса на раскрытие бёдер', code: 'VN-208', min: 40, parts: 6, style: 'vinyasa', tone: 'coral', saved: false, media: ['video'],
    tags: ['Гибкость', 'Бёдра', 'Поток'],
    by: { name: 'Мария Светлова', initials: 'МС', color: 'var(--c-coral)' } },
  { id: 'm3', name: 'Дыхание Нади Шодхана', code: 'PR-051', min: 12, parts: 2, style: 'hatha', tone: 'sky', saved: true, media: ['audio', 'timer'],
    tags: ['Баланс', 'Дыхание', 'Успокоение'],
    by: { name: 'Рам Пракаш', initials: 'РП', color: 'var(--c-sky)' } },
  { id: 'm4', name: 'Вечерняя медитация покоя', code: 'MD-330', min: 18, parts: 3, style: 'kundalini', tone: 'mint', saved: false, media: ['audio', 'timer'],
    tags: ['Сон', 'Медитация', 'Расслабление'],
    by: { name: 'Лакшми Рао', initials: 'ЛР', color: 'var(--c-mint)' } },
  { id: 'm5', name: 'Сурья Намаскар · 12 кругов', code: 'SN-112', min: 20, parts: 4, style: 'hatha', tone: 'sun', saved: false, media: ['video', 'audio', 'timer'],
    tags: ['Утро', 'Поток', 'Сила'],
    by: { name: 'Антон Рассвет', initials: 'АР', color: 'var(--c-goldink)' } },
  { id: 'm6', name: 'Инь для спины перед сном', code: 'YN-489', min: 35, parts: 5, style: 'yin', tone: 'bubble', saved: true, media: ['video', 'timer'],
    tags: ['Спина', 'Вечер', 'Восстановление'],
    by: { name: 'Мира Деви', initials: 'МД', color: 'var(--c-bubble)' } },
  { id: 'm7', name: 'Моя разминка для шеи и плеч', code: 'WU-073', min: 15, parts: 3, style: 'hatha', tone: 'leaf', saved: false, media: ['video'],
    tags: ['Шея', 'Плечи', 'Офис'],
    by: MP_ME },
  { id: 'm8', name: 'Капалабхати · дыхание огня', code: 'PR-064', min: 10, parts: 3, style: 'kundalini', tone: 'coral', saved: false, media: ['video', 'audio', 'timer'],
    tags: ['Энергия', 'Дыхание', 'Концентрация'],
    by: { name: 'Рам Пракаш', initials: 'РП', color: 'var(--c-sky)' } },
  { id: 'm9', name: 'Растяжка после пробежки', code: 'ST-256', min: 22, parts: 4, style: 'hatha', tone: 'sky', saved: false, media: ['video'],
    tags: ['Растяжка', 'Ноги', 'Восстановление'],
    by: { name: 'Антон Рассвет', initials: 'АР', color: 'var(--c-goldink)' } },
  { id: 'm10', name: 'Шавасана · глубокое расслабление', code: 'MD-097', min: 14, parts: 1, style: 'yin', tone: 'grape', saved: true, media: ['audio', 'timer'],
    tags: ['Сон', 'Расслабление', 'Покой'],
    by: { name: 'Джала Деви', initials: 'ДД', color: 'var(--c-sky)' } },
  { id: 'm11', name: 'Баланс на одной ноге · поток', code: 'BL-145', min: 28, parts: 5, style: 'vinyasa', tone: 'mint', saved: false, media: ['video', 'audio'],
    tags: ['Баланс', 'Сила', 'Концентрация'],
    by: { name: 'Мария Светлова', initials: 'МС', color: 'var(--c-coral)' } },
  { id: 'm12', name: 'Утренняя растяжка спины', code: 'ST-188', min: 16, parts: 4, style: 'hatha', tone: 'sun', saved: false, media: ['video'],
    tags: ['Спина', 'Утро', 'Гибкость'],
    by: MP_ME },
];

/* ── сегменты списка ───────────────────────────────────────────── */
// «У меня»  — всё, что уже добавлено к себе (свои практики ИЛИ сохранённые чужие).
// «Сангат» — практики сообщества, которые ещё можно добавить себе.
const MP_SEGS = [
  { id: 'mine',   label: 'У меня' },
  { id: 'sangat', label: 'Сангат' },
];
const isMine   = (p) => p.by.name === 'Вы';
const isAdded  = (p) => isMine(p) || p.saved;   // «у меня»
const isSangat = (p) => !isAdded(p);            // сообщество — можно добавить

/* ── стили йоги (фильтр над поиском) ────────────────────────────── */
const MP_STYLES = [
  { id: 'kundalini', label: 'Кундалини', emoji: '🌀' },
  { id: 'hatha',     label: 'Хатха',     emoji: '🧘' },
  { id: 'kriya',     label: 'Крийя',     emoji: '🔥' },
  { id: 'vinyasa',   label: 'Виньяса',   emoji: '🌊' },
  { id: 'yin',       label: 'Инь',       emoji: '🌙' },
];
const MP_STYLE_LABEL = MP_STYLES.reduce((m, s) => { m[s.id] = s.label; return m; }, {});

/* ── аватар-кружок с инициалами ────────────────────────────────── */
function MPAva({ p, size = 26 }) {
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%', flexShrink: 0,
      background: p.color, color: '#fff',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      fontFamily: QBM, fontWeight: 800, fontSize: size * 0.4, letterSpacing: '0.01em',
    }}>{p.initials}</div>
  );
}

/* ── иконки доступных форматов (видео / аудио / таймеры) ────── */
function MPMedia({ media }) {
  if (!media || media.length === 0) return null;
  const items = [];
  if (media.includes('video')) items.push({ k: 'video', Icon: IconVideo, label: 'Видео' });
  if (media.includes('audio')) items.push({ k: 'audio', Icon: IconAudio, label: 'Аудио' });
  if (media.includes('timer')) items.push({ k: 'timer', Icon: IconTimer, label: 'Таймеры' });
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}>
      {items.map(({ k, Icon, label }) => (
        <span key={k} aria-label={label} title={label} style={{
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          width: 26, height: 26, borderRadius: 8, background: 'var(--p-ctrl)', color: 'var(--p-mute)',
        }}><Icon size={15} /></span>
      ))}
    </span>
  );
}

/* ── карточка практики ─────────────────────────────────────────── */
function MPracticeCard({ p, onToggleSave, hideAction, onOpen }) {
  const tone = MP_TONES[p.tone] || MP_TONES.grape;
  const own = isMine(p);
  return (
    <div onClick={onOpen ? () => onOpen(p) : undefined} className={onOpen ? 'pbtn tap' : undefined} style={{
      borderRadius: 22, background: 'var(--p-elev)', border: '1px solid var(--p-border)',
      padding: '14px 16px 13px', boxShadow: '0 16px 34px -26px rgba(40,20,60,0.5)',
    }}>
      {/* верх: код + время + форматы (одна строка) */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}>
        {p.code && (
          <span style={{
            display: 'inline-flex', alignItems: 'center',
            padding: '4px 9px', borderRadius: 7,
            background: tone.t, color: tone.c, border: '1px solid currentColor',
            fontFamily: 'Space Grotesk, monospace', fontWeight: 700, fontSize: 11.5,
            letterSpacing: '0.06em', whiteSpace: 'nowrap',
          }}>{p.code}</span>
        )}
        <span style={{
          display: 'inline-flex', alignItems: 'center', gap: 6,
          padding: '5px 11px', borderRadius: 999, background: tone.t, color: tone.c,
          fontFamily: QBM, fontWeight: 800, fontSize: 12.5, whiteSpace: 'nowrap',
        }}><IconClock size={14} style={{ color: tone.c }} /> {p.min} мин</span>
        <span style={{ flex: 1 }} />
        <MPMedia media={p.media} />
      </div>

      {/* вторая строка: количество частей */}
      {p.parts != null && (
        <div style={{ marginTop: 8 }}>
          <span style={{
            display: 'inline-flex', alignItems: 'center', gap: 6,
            padding: '5px 11px', borderRadius: 999, background: 'var(--p-ctrl)', color: 'var(--p-soft)',
            fontFamily: QBM, fontWeight: 800, fontSize: 12.5, whiteSpace: 'nowrap',
          }}><IconLayers size={14} style={{ color: 'var(--p-mute)' }} /> {p.parts} {partsWord(p.parts)}</span>
        </div>
      )}

      {/* стиль йоги */}
      {p.style && MP_STYLE_LABEL[p.style] && (
        <div style={{
          marginTop: 11, fontFamily: QBM, fontWeight: 800, fontSize: 11,
          letterSpacing: '0.08em', textTransform: 'uppercase', color: tone.c,
        }}>{MP_STYLE_LABEL[p.style]}</div>
      )}

      {/* название */}
      <h3 style={{
        margin: '4px 0 0', fontFamily: QBM, fontWeight: 800, fontSize: 17,
        lineHeight: 1.2, letterSpacing: '-0.01em', color: 'var(--p-ink)', textWrap: 'pretty',
      }}>{p.name}</h3>

      {/* теги */}
      {p.tags.length > 0 && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 10 }}>
          {p.tags.map((tag, i) => (
            <span key={i} style={{
              padding: '4px 10px', borderRadius: 8, background: 'var(--p-ctrl)',
              color: 'var(--p-soft)', fontFamily: QBM, fontWeight: 700, fontSize: 12,
            }}>#{tag}</span>
          ))}
        </div>
      )}

      {/* кто добавил + действие */}
      <div style={{
        display: 'flex', alignItems: 'center', gap: 8, marginTop: 12, paddingTop: 11,
        borderTop: '1px solid var(--p-border)',
      }}>
        <MPAva p={p.by} />
        <span style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--p-mute)' }}>
          Добавил{own ? 'и' : 'а'} <strong style={{ color: 'var(--p-soft)', fontWeight: 800 }}>{p.by.name}</strong>
        </span>
        <span style={{ flex: 1 }} />
        {hideAction ? null : own ? (
          <span style={{
            flexShrink: 0, display: 'inline-flex', alignItems: 'center', gap: 5,
            padding: '6px 12px', borderRadius: 999,
            background: 'var(--t-leaf)', color: 'var(--c-leaf)',
            fontFamily: QBM, fontWeight: 800, fontSize: 12.5, whiteSpace: 'nowrap',
          }}><IconLotus size={15} /> Ваша</span>
        ) : (
          <button onClick={(e) => { e.stopPropagation(); onToggleSave(p); }} className="pbtn tap" style={{
            flexShrink: 0, display: 'inline-flex', alignItems: 'center', gap: 5, cursor: 'pointer',
            padding: '8px 14px', borderRadius: 999, whiteSpace: 'nowrap',
            border: p.saved ? 'none' : '1.5px solid var(--p-border)',
            background: p.saved ? 'var(--c-leaf)' : 'var(--p-bg)',
            color: p.saved ? '#fff' : 'var(--p-soft)',
            fontFamily: QBM, fontWeight: 800, fontSize: 12.5,
            boxShadow: p.saved ? '0 8px 18px -10px rgba(93,187,99,0.95)' : 'none',
          }}>
            {p.saved
              ? <React.Fragment><IconCheck size={15} /> У меня</React.Fragment>
              : <React.Fragment><IconPlus size={15} /> К себе</React.Fragment>}
          </button>
        )}
      </div>
    </div>
  );
}

/* ── шторка «Новая практика» ───────────────────────────────────── */
function MPAddSheet({ onClose, onSave }) {
  const [name, setName] = uMP('');
  const [min, setMin] = uMP('');
  const [parts, setParts] = uMP('');
  const [tagText, setTagText] = uMP('');
  const [media, setMedia] = uMP(['video']);

  const canSave = name.trim().length > 0 && Number(min) > 0;
  const tagsPreview = tagText.split(',').map((s) => s.trim()).filter(Boolean);

  const toggleMedia = (k) => setMedia((prev) => prev.includes(k) ? prev.filter((x) => x !== k) : [...prev, k]);

  const save = () => {
    if (!canSave) return;
    const tone = MP_TONE_KEYS[Math.floor(Math.random() * MP_TONE_KEYS.length)];
    const code = 'LK-' + String(Math.floor(100 + Math.random() * 900));
    onSave({
      id: 'm' + Date.now(), name: name.trim(), code, min: Number(min),
      parts: Number(parts) > 0 ? Number(parts) : 1, tone, saved: false,
      tags: tagsPreview, media, by: MP_ME,
    });
  };

  const fieldLbl = { fontFamily: QBM, fontWeight: 800, fontSize: 11.5, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 8 };
  const inputCss = {
    width: '100%', boxSizing: 'border-box', height: 48, padding: '0 14px',
    borderRadius: 14, border: '1px solid var(--p-border)', background: 'var(--p-ctrl)',
    outline: 'none', fontFamily: NSM, fontSize: 15.5, fontWeight: 600, color: 'var(--p-ink)',
  };

  return (
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 10px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 24, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={{ width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 2px' }} />
          <div style={{ maxHeight: 520, overflowY: 'auto', scrollbarWidth: 'none', padding: '8px 18px 4px' }}>
            <h3 style={{ margin: '4px 0 16px', fontFamily: QBM, fontWeight: 800, fontSize: 20, color: 'var(--p-ink)' }}>Новая практика</h3>

            <div style={fieldLbl}>Название</div>
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Например, Утренняя крийя" style={inputCss} />

            <div style={{ ...fieldLbl, marginTop: 16 }}>Время выполнения, мин</div>
            <input value={min} onChange={(e) => setMin(e.target.value.replace(/[^0-9]/g, ''))} inputMode="numeric" placeholder="25" style={inputCss} />

            <div style={{ ...fieldLbl, marginTop: 16 }}>Частей в практике</div>
            <input value={parts} onChange={(e) => setParts(e.target.value.replace(/[^0-9]/g, ''))} inputMode="numeric" placeholder="5" style={inputCss} />

            <div style={{ ...fieldLbl, marginTop: 16 }}>Теги <span style={{ textTransform: 'none', letterSpacing: 0, fontWeight: 700, color: 'var(--p-faint)' }}>через запятую</span></div>
            <input value={tagText} onChange={(e) => setTagText(e.target.value)} placeholder="Энергия, Утро, Новичкам" style={inputCss} />
            {tagsPreview.length > 0 && (
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 9 }}>
                {tagsPreview.map((t, i) => (
                  <span key={i} style={{ padding: '4px 10px', borderRadius: 8, background: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: QBM, fontWeight: 700, fontSize: 12 }}>#{t}</span>
                ))}
              </div>
            )}

            <div style={{ ...fieldLbl, marginTop: 16 }}>Форматы</div>
            <div style={{ display: 'flex', gap: 8 }}>
              {[{ k: 'video', Icon: IconVideo, label: 'Видео' }, { k: 'audio', Icon: IconAudio, label: 'Аудио' }, { k: 'timer', Icon: IconTimer, label: 'Таймеры' }].map(({ k, Icon, label }) => {
                const on = media.includes(k);
                return (
                  <button key={k} onClick={() => toggleMedia(k)} className="pbtn tap" style={{
                    flex: 1, height: 46, cursor: 'pointer', borderRadius: 14,
                    border: on ? 'none' : '1.5px solid var(--p-border)',
                    background: on ? 'var(--ctrl-accent)' : 'var(--p-ctrl)',
                    color: on ? '#fff' : 'var(--p-soft)',
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                    fontFamily: QBM, fontWeight: 800, fontSize: 13,
                  }}><Icon size={16} /> {label}</button>
                );
              })}
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginTop: 18, padding: '11px 13px', borderRadius: 14, background: 'var(--p-ctrl)' }}>
              <MPAva p={MP_ME} size={30} />
              <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 11, fontWeight: 800, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Кто добавляет</div>
                <div style={{ fontFamily: QBM, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', marginTop: 1 }}>Вы</div>
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', gap: 10, padding: '12px 18px 16px' }}>
            <button onClick={onClose} className="pbtn" style={{
              flex: '0 0 auto', padding: '0 20px', height: 50, border: 'none', cursor: 'pointer',
              borderRadius: 15, background: 'var(--p-ctrl)', color: 'var(--p-soft)',
              fontFamily: QBM, fontWeight: 800, fontSize: 15,
            }}>Отмена</button>
            <button onClick={save} disabled={!canSave} className="pbtn" style={{
              flex: 1, height: 50, border: 'none', cursor: canSave ? 'pointer' : 'default',
              borderRadius: 15, background: canSave ? 'var(--c-coral)' : 'var(--p-ctrl)',
              color: canSave ? '#fff' : 'var(--p-faint)',
              fontFamily: QBM, fontWeight: 800, fontSize: 15.5,
              boxShadow: canSave ? '0 14px 28px -14px rgba(255,111,97,0.95)' : 'none',
            }}>Добавить практику</button>
          </div>
        </div>
      </div>
    </React.Fragment>
  );
}

/* ── нижняя навигация (раздел «Моя практика» вместо «Приложения») ── */
const MP_NAV = [
  { key: 'practice', label: 'Моя практика', emoji: '🌀', pop: '#E8615A', soft: 'rgba(232,97,90,0.16)' },
  { key: 'classes', label: 'Занятия', emoji: '🧘', pop: '#E8902F', soft: 'rgba(232,144,47,0.16)' },
  { key: 'calendar', label: 'Календарь', emoji: '📅', pop: '#4FA85B', soft: 'rgba(79,168,91,0.16)' },
  { key: 'sangat', label: 'Сангат', emoji: '❤️', pop: '#3E92D8', soft: 'rgba(62,146,216,0.16)' },
  { key: 'ahamkara', label: 'Ахамкара', emoji: '🪬', pop: '#8E55D8', soft: 'rgba(142,85,216,0.16)' },
];
function MPBottomNav({ active: activeProp, onNav } = {}) {
  const [activeState, setActiveState] = uMP('practice');
  const active = activeProp != null ? activeProp : activeState;
  const setActive = onNav || setActiveState;
  return (
    <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderTop: '1px solid var(--p-border)', display: 'flex', alignItems: 'stretch', justifyContent: 'space-around', padding: '8px 4px 24px' }}>
      {MP_NAV.map(({ key, label, emoji, pop, soft }) => {
        const on = active === key;
        return (
          <button key={key} className="tap pbtn" onClick={() => setActive(key)} style={{ flex: 1, minWidth: 0, border: 'none', background: 'transparent', cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 5, padding: '2px 1px' }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 36, height: 36, borderRadius: '50%', backgroundColor: on ? soft : 'transparent', transform: on ? 'translateY(-1px)' : 'none', transition: 'background .18s ease, transform .18s ease' }}>
              <span style={{ fontSize: 19, lineHeight: 1, filter: on ? 'none' : 'saturate(0.8) opacity(0.6)' }}>{emoji}</span>
            </span>
            <span style={{ fontFamily: NSM, fontSize: 9.5, lineHeight: 1, letterSpacing: '-0.2px', fontWeight: on ? 800 : 600, whiteSpace: 'nowrap', color: on ? pop : 'var(--p-mute)', transition: 'color .18s ease' }}>{label}</span>
          </button>
        );
      })}
    </div>
  );
}

/* ── главный экран «Моя практика» ──────────────────────────────── */
function MyPracticeScreen({ dark = false, nav }) {
  const [items, setItems] = uMP(MY_PRACTICES);
  const [seg, setSeg] = uMP('mine');
  const [q, setQ] = uMP('');
  const [styleFilter, setStyleFilter] = uMP(null);
  const [activeTag, setActiveTag] = uMP(null);
  const [adding, setAdding] = uMP(false);
  const [playing, setPlaying] = uMP(null);   // практика, открытая в плеере
  const [toast, setToast] = uMP(null);

  const ping = (msg) => {
    setToast(msg);
    clearTimeout(ping._t);
    ping._t = setTimeout(() => setToast(null), 1900);
  };

  const counts = {
    mine: items.filter(isAdded).length,
    sangat: items.filter(isSangat).length,
  };

  // практики текущего сегмента
  const bySeg = items.filter((p) => seg === 'mine' ? isAdded(p) : isSangat(p));

  // теги для быстрого фильтра — уникальные, в порядке появления внутри сегмента
  const segTags = uMPm(() => {
    const out = [];
    bySeg.forEach((p) => p.tags.forEach((t) => { if (!out.includes(t)) out.push(t); }));
    return out;
  }, [items, seg]);

  // поиск фильтрует по названию / тегам / автору; плюс выбранный тег
  const qn = q.trim().toLowerCase();
  const shown = bySeg.filter((p) => {
    if (styleFilter && p.style !== styleFilter) return false;
    if (activeTag && !p.tags.includes(activeTag)) return false;
    if (!qn) return true;
    return p.name.toLowerCase().includes(qn) ||
      p.by.name.toLowerCase().includes(qn) ||
      p.tags.some((t) => t.toLowerCase().includes(qn));
  });

  const toggleSave = (p) => {
    setItems((prev) => prev.map((x) => x.id === p.id ? { ...x, saved: !x.saved } : x));
    ping(p.saved ? `«${p.name}» убрана из ваших` : `«${p.name}» добавлена к вам ✓`);
  };
  const add = (p) => {
    setItems((prev) => [p, ...prev]);
    setAdding(false);
    setSeg('mine');
    ping(`«${p.name}» добавлена ✓`);
  };
  const resetFilters = () => { setQ(''); setActiveTag(null); setStyleFilter(null); };

  const segBtn = (s) => {
    const on = seg === s.id;
    return (
      <button key={s.id} onClick={() => { setSeg(s.id); setActiveTag(null); }} className="pbtn tap" style={{
        flex: 1, height: 38, border: 'none', cursor: 'pointer', borderRadius: 11,
        fontFamily: QBM, fontWeight: 800, fontSize: 13,
        background: on ? 'var(--p-elev)' : 'transparent',
        color: on ? 'var(--p-ink)' : 'var(--p-mute)',
        boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none',
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6,
        transition: 'all .15s ease',
      }}>
        {s.label}
        <span style={{
          fontSize: 11, fontWeight: 800, minWidth: 18, padding: '1px 6px', borderRadius: 999,
          background: on ? 'var(--c-coral)' : 'var(--p-bg)', color: on ? '#fff' : 'var(--p-faint)',
        }}>{counts[s.id]}</span>
      </button>
    );
  };

  return (
    <div className={'ylp' + (dark ? ' dark' : '')} style={{ height: '100%' }}>
      {/* верхняя панель — стекло + размытие, как на остальных страницах */}
      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)', padding: '48px 14px 11px', display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{ flex: 1, minWidth: 0, display: 'flex', alignItems: 'center', gap: 7, fontFamily: QBM, fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', whiteSpace: 'nowrap' }}>🌀 Моя практика</span>
        <button onClick={() => setAdding(true)} className="picon pbtn" aria-label="Добавить практику" style={{ backgroundColor: 'var(--t-coral)' }}>
          <IconPlus size={20} style={{ color: 'var(--c-coral)' }} />
        </button>
      </div>

      <div className="pscroll" style={{ paddingBottom: 96 }}>
        {/* распорка под фиксированную шапку */}
        <div style={{ height: 102 }} />

        {/* подзаголовок */}
        <div style={{ padding: '6px 16px 0', fontSize: 13, fontWeight: 600, color: 'var(--p-mute)', lineHeight: 1.45 }}>
          Библиотека практик сообщества — добавляйте к себе и делитесь своими 🌱
        </div>

        {/* сегменты: У меня / Сангат */}
        <div style={{ display: 'flex', gap: 6, margin: '13px 14px 0', backgroundColor: 'var(--p-ctrl)', borderRadius: 14, padding: 4 }}>
          {MP_SEGS.map(segBtn)}
        </div>

        {/* фильтр по стилю йоги (над поиском) */}
        <div className="hrow" style={{ display: 'flex', gap: 7, overflowX: 'auto', scrollbarWidth: 'none', padding: '13px 14px 1px' }}>
          {[{ id: null, label: 'Все' }, ...MP_STYLES].map((s) => {
            const on = styleFilter === s.id;
            return (
              <button key={s.id || 'all'} onClick={() => setStyleFilter(s.id)} className="pbtn tap" style={{
                flexShrink: 0, border: '1.5px solid ' + (on ? 'var(--c-grape)' : 'var(--p-border)'),
                cursor: 'pointer', whiteSpace: 'nowrap',
                padding: '7px 14px', borderRadius: 999,
                background: on ? 'var(--c-grape)' : 'var(--p-elev)', color: on ? '#fff' : 'var(--p-soft)',
                fontFamily: QBM, fontWeight: 800, fontSize: 12.5,
                boxShadow: on ? '0 6px 15px -7px rgba(155,93,229,0.95)' : 'none', transition: 'all .15s ease',
              }}>
                {s.label}
              </button>
            );
          })}
        </div>

        {/* поиск (фильтрует список) */}
        <div style={{ padding: '12px 14px 0' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, height: 48, padding: '0 14px', borderRadius: 16, background: 'var(--p-ctrl)', border: '1px solid transparent' }}>
            <IconSearch size={19} style={{ color: 'var(--p-faint)', flexShrink: 0 }} />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Поиск по практикам, тегам, авторам"
              style={{ flex: 1, minWidth: 0, border: 'none', background: 'none', outline: 'none', fontFamily: NSM, fontSize: 15, fontWeight: 600, color: 'var(--p-ink)' }} />
            {q && (
              <button onClick={() => setQ('')} className="tap" aria-label="Очистить" style={{ border: 'none', background: 'var(--p-bg)', cursor: 'pointer', width: 24, height: 24, borderRadius: '50%', color: 'var(--p-mute)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 800, flexShrink: 0 }}>✕</button>
            )}
          </div>
        </div>

        {/* быстрые теги под поиском */}
        {segTags.length > 0 && (
          <div className="hrow" style={{ display: 'flex', gap: 7, overflowX: 'auto', scrollbarWidth: 'none', padding: '11px 14px 2px' }}>
            {segTags.map((t, i) => {
              const on = t === activeTag;
              return (
                <button key={i} onClick={() => setActiveTag(on ? null : t)} className="pbtn tap" style={{
                  flexShrink: 0, border: 'none', cursor: 'pointer', whiteSpace: 'nowrap',
                  padding: '7px 13px', borderRadius: 999,
                  background: on ? 'var(--c-sky)' : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-soft)',
                  fontFamily: QBM, fontWeight: 700, fontSize: 12.5,
                  boxShadow: on ? '0 6px 14px -7px rgba(77,157,224,0.95)' : 'none', transition: 'all .15s ease',
                }}>#{t}</button>
              );
            })}
          </div>
        )}

        {/* заголовок списка */}
        <div style={{ padding: '15px 16px 9px', display: 'flex', alignItems: 'baseline', gap: 8 }}>
          <h2 style={{ margin: 0, fontFamily: QBM, fontWeight: 800, fontSize: 16, color: 'var(--p-ink)' }}>Практики</h2>
          <span style={{ fontFamily: QBM, fontWeight: 800, fontSize: 13, color: 'var(--p-faint)' }}>{shown.length}</span>
          <span style={{ flex: 1 }} />
          <button onClick={() => setAdding(true)} className="tap" style={{
            border: 'none', background: 'transparent', cursor: 'pointer', padding: 0,
            color: 'var(--c-coral)', fontFamily: QBM, fontWeight: 800, fontSize: 13.5,
            display: 'inline-flex', alignItems: 'center', gap: 4,
          }}><IconPlus size={16} /> Добавить</button>
        </div>

        {/* список практик */}
        <div style={{ padding: '0 14px', display: 'flex', flexDirection: 'column', gap: 12 }}>
          {shown.map((p) => <MPracticeCard key={p.id} p={p} onToggleSave={toggleSave} hideAction={seg === 'mine'} onOpen={setPlaying} />)}

          {shown.length === 0 && (
            <div style={{ textAlign: 'center', padding: '38px 24px 8px', color: 'var(--p-faint)' }}>
              <div style={{ fontSize: 32 }}>🧘</div>
              <div style={{ fontFamily: QBM, fontWeight: 800, fontSize: 15, color: 'var(--p-mute)', marginTop: 9 }}>
                {(qn || activeTag || styleFilter) ? 'Ничего не найдено' : (seg === 'mine' ? 'Пока ничего не добавлено' : seg === 'sangat' ? 'Вы добавили все практики сообщества 🌱' : 'Здесь пока пусто')}
              </div>
              {(qn || activeTag || styleFilter) ? (
                <button onClick={resetFilters} className="tap" style={{ marginTop: 13, border: 'none', cursor: 'pointer', padding: '9px 18px', borderRadius: 999, background: 'var(--p-ctrl)', color: 'var(--c-coral)', fontFamily: QBM, fontWeight: 800, fontSize: 13 }}>Сбросить поиск</button>
              ) : (
                <button onClick={() => setAdding(true)} className="tap" style={{ marginTop: 13, border: 'none', cursor: 'pointer', padding: '9px 18px', borderRadius: 999, background: 'var(--c-coral)', color: '#fff', fontFamily: QBM, fontWeight: 800, fontSize: 13 }}>Добавить практику</button>
              )}
            </div>
          )}

          {/* пунктирная плитка «добавить» */}
          {shown.length > 0 && (
            <button onClick={() => setAdding(true)} className="pbtn tap" style={{
              border: '1.5px dashed var(--p-faint)', background: 'transparent', cursor: 'pointer',
              borderRadius: 22, padding: '15px', color: 'var(--p-mute)',
              display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
              fontFamily: QBM, fontWeight: 800, fontSize: 14.5,
            }}><IconPlus size={18} style={{ color: 'var(--c-coral)' }} /> Добавить практику</button>
          )}
        </div>
      </div>

      <MPBottomNav {...nav} />

      {adding && (
        <div style={{ position: 'absolute', inset: 0, zIndex: 50, animation: 'peSlideIn .28s cubic-bezier(.2,.9,.25,1)' }}>
          <PracticeEditorScreen dark={dark} onBack={() => setAdding(false)} />
        </div>
      )}

      {playing && <PracticePlayerScreen practice={playing} dark={dark} onClose={() => setPlaying(null)} />}

      {toast && (
        <div className="ptoast" style={{
          position: 'absolute', bottom: 96, left: '50%', transform: 'translateX(-50%)', zIndex: 60,
          padding: '12px 18px', borderRadius: 16, maxWidth: '84%', textAlign: 'center',
          background: 'var(--p-ink)', color: 'var(--p-bg)',
          fontFamily: QBM, fontWeight: 700, fontSize: 13.5, lineHeight: 1.35,
          boxShadow: '0 18px 34px -16px rgba(0,0,0,0.6)',
        }}>{toast}</div>
      )}
    </div>
  );
}

Object.assign(window, { MyPracticeScreen, MPracticeCard, MPAva, MY_PRACTICES, isMine, isAdded, isSangat });
