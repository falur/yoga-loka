// builder.jsx — экран приложения «Построение практики».
// Открывается по клику на приложение из лаунчера. Показывает уже добавленные
// практики (название · время · категория · теги · кем добавлена) и даёт
// добавить новую. Кнопка «Поиск практики» пока без логики.
// Экспорт в window: BuilderScreen.

const { useState: uB } = React;
const QB = "'Quicksand', sans-serif";

/* ── категории практик (цвета из палитры .ylp) ─────────────────── */
const PCATS = [
  { id: 'kriya',      label: 'Крийя',        e: '🔥', c: 'var(--c-grape)',  t: 'var(--t-grape)'  },
  { id: 'vinyasa',    label: 'Виньяса-флоу', e: '🌊', c: 'var(--c-coral)',  t: 'var(--t-coral)'  },
  { id: 'pranayama',  label: 'Пранаяма',     e: '🌬', c: 'var(--c-sky)',    t: 'var(--t-sky)'    },
  { id: 'meditation', label: 'Медитация',    e: '🪷', c: 'var(--c-mint)',   t: 'var(--t-mint)'   },
  { id: 'hatha',      label: 'Хатха',        e: '☀️', c: 'var(--c-goldink)', t: 'var(--t-sun)'   },
];
const catById = (id) => PCATS.find((c) => c.id === id) || PCATS[0];

/* ── кто добавил ───────────────────────────────────────────────── */
const ME = { name: 'Вы', initials: 'Я', color: 'var(--c-leaf)' };

/* ── стартовые практики ────────────────────────────────────────── */
const SEED = [
  { id: 'p1', name: 'Утренняя крийя пробуждения', min: 25, cat: 'kriya',
    tags: ['Энергия', 'Утро', 'Для начинающих'],
    by: { name: 'Ананда Деви', initials: 'АД', color: 'var(--c-grape)' } },
  { id: 'p2', name: 'Виньяса для раскрытия бёдер', min: 40, cat: 'vinyasa',
    tags: ['Гибкость', 'Бёдра'],
    by: { name: 'Мария Светлова', initials: 'МС', color: 'var(--c-coral)' } },
  { id: 'p3', name: 'Дыхание Нади Шодхана', min: 12, cat: 'pranayama',
    tags: ['Баланс', 'Успокоение'],
    by: { name: 'Рам Пракаш', initials: 'РП', color: 'var(--c-sky)' } },
  { id: 'p4', name: 'Вечерняя медитация покоя', min: 18, cat: 'meditation',
    tags: ['Сон', 'Расслабление'],
    by: { name: 'Лакшми Рао', initials: 'ЛР', color: 'var(--c-mint)' } },
];

/* ── аватар-кружок с инициалами ────────────────────────────────── */
function Ava({ p, size = 26 }) {
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%', flexShrink: 0,
      background: p.color, color: '#fff',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      fontFamily: QB, fontWeight: 800, fontSize: size * 0.4, letterSpacing: '0.01em',
    }}>{p.initials}</div>
  );
}

/* ── чип категории ─────────────────────────────────────────────── */
function CatChip({ cat, small }) {
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 5,
      padding: small ? '4px 9px' : '6px 12px', borderRadius: 999,
      background: cat.t, color: cat.c,
      fontFamily: QB, fontWeight: 800, fontSize: small ? 11.5 : 12.5, whiteSpace: 'nowrap',
    }}>{cat.e} {cat.label}</span>
  );
}

/* ── карточка практики ─────────────────────────────────────────── */
function PracticeCard({ p }) {
  const cat = catById(p.cat);
  return (
    <div style={{
      borderRadius: 22, background: 'var(--p-elev)', border: '1px solid var(--p-border)',
      padding: '15px 16px 13px', boxShadow: '0 16px 34px -26px rgba(40,20,60,0.5)',
    }}>
      {/* верх: категория + время */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <CatChip cat={cat} />
        <span style={{ flex: 1 }} />
        <span style={{
          display: 'inline-flex', alignItems: 'center', gap: 5,
          color: 'var(--p-mute)', fontFamily: QB, fontWeight: 800, fontSize: 13,
        }}><IconClock size={15} style={{ color: cat.c }} /> {p.min} мин</span>
      </div>

      {/* название */}
      <h3 style={{
        margin: '11px 0 0', fontFamily: QB, fontWeight: 800, fontSize: 17.5,
        lineHeight: 1.2, letterSpacing: '-0.01em', color: 'var(--p-ink)', textWrap: 'pretty',
      }}>{p.name}</h3>

      {/* теги */}
      {p.tags.length > 0 && (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 11 }}>
          {p.tags.map((tag, i) => (
            <span key={i} style={{
              padding: '4px 10px', borderRadius: 8, background: 'var(--p-ctrl)',
              color: 'var(--p-soft)', fontFamily: QB, fontWeight: 700, fontSize: 12,
            }}>#{tag}</span>
          ))}
        </div>
      )}

      {/* кто добавил */}
      <div style={{
        display: 'flex', alignItems: 'center', gap: 8, marginTop: 13, paddingTop: 12,
        borderTop: '1px solid var(--p-border)',
      }}>
        <Ava p={p.by} />
        <span style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--p-mute)' }}>
          Добавил{p.by.name === 'Вы' ? 'и' : 'а'} <strong style={{ color: 'var(--p-soft)', fontWeight: 800 }}>{p.by.name}</strong>
        </span>
      </div>
    </div>
  );
}

/* ── шторка «Новая практика» ───────────────────────────────────── */
function AddSheet({ onClose, onSave }) {
  const [name, setName] = uB('');
  const [min, setMin] = uB('');
  const [cat, setCat] = uB('kriya');
  const [tagText, setTagText] = uB('');

  const canSave = name.trim().length > 0 && Number(min) > 0;
  const tagsPreview = tagText.split(',').map((s) => s.trim()).filter(Boolean);

  const save = () => {
    if (!canSave) return;
    onSave({
      id: 'p' + Date.now(), name: name.trim(), min: Number(min), cat,
      tags: tagsPreview, by: ME,
    });
  };

  const fieldLbl = { fontFamily: QB, fontWeight: 800, fontSize: 11.5, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 8 };
  const inputCss = {
    width: '100%', boxSizing: 'border-box', height: 48, padding: '0 14px',
    borderRadius: 14, border: '1px solid var(--p-border)', background: 'var(--p-ctrl)',
    outline: 'none', fontFamily: "'Nunito Sans', system-ui, sans-serif",
    fontSize: 15.5, fontWeight: 600, color: 'var(--p-ink)',
  };

  return (
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 10px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 24, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={{ width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 2px' }} />
          <div style={{ maxHeight: 560, overflowY: 'auto', scrollbarWidth: 'none', padding: '8px 18px 4px' }}>
            <h3 style={{ margin: '4px 0 16px', fontFamily: QB, fontWeight: 800, fontSize: 20, color: 'var(--p-ink)' }}>Новая практика</h3>

            {/* название */}
            <div style={fieldLbl}>Название</div>
            <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Например, Утренняя крийя" style={inputCss} />

            {/* время */}
            <div style={{ ...fieldLbl, marginTop: 16 }}>Время выполнения, мин</div>
            <input value={min} onChange={(e) => setMin(e.target.value.replace(/[^0-9]/g, ''))} inputMode="numeric" placeholder="25" style={inputCss} />

            {/* категория */}
            <div style={{ ...fieldLbl, marginTop: 16 }}>Категория</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              {PCATS.map((c) => {
                const on = c.id === cat;
                return (
                  <button key={c.id} onClick={() => setCat(c.id)} className="pbtn" style={{
                    border: on ? `1.5px solid ${c.c}` : '1.5px solid var(--p-border)', cursor: 'pointer',
                    padding: '8px 13px', borderRadius: 999,
                    background: on ? c.t : 'var(--p-bg)', color: on ? c.c : 'var(--p-mute)',
                    fontFamily: QB, fontWeight: 800, fontSize: 13,
                  }}>{c.e} {c.label}</button>
                );
              })}
            </div>

            {/* теги */}
            <div style={{ ...fieldLbl, marginTop: 16 }}>Теги <span style={{ textTransform: 'none', letterSpacing: 0, fontWeight: 700, color: 'var(--p-faint)' }}>через запятую</span></div>
            <input value={tagText} onChange={(e) => setTagText(e.target.value)} placeholder="Энергия, Утро, Для начинающих" style={inputCss} />
            {tagsPreview.length > 0 && (
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 9 }}>
                {tagsPreview.map((t, i) => (
                  <span key={i} style={{ padding: '4px 10px', borderRadius: 8, background: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: QB, fontWeight: 700, fontSize: 12 }}>#{t}</span>
                ))}
              </div>
            )}

            {/* кем добавлена */}
            <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginTop: 18, padding: '11px 13px', borderRadius: 14, background: 'var(--p-ctrl)' }}>
              <Ava p={ME} size={30} />
              <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 11, fontWeight: 800, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Кем добавлена</div>
                <div style={{ fontFamily: QB, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', marginTop: 1 }}>Вы</div>
              </div>
            </div>
          </div>

          {/* действия */}
          <div style={{ display: 'flex', gap: 10, padding: '12px 18px 16px' }}>
            <button onClick={onClose} className="pbtn" style={{
              flex: '0 0 auto', padding: '0 20px', height: 50, border: 'none', cursor: 'pointer',
              borderRadius: 15, background: 'var(--p-ctrl)', color: 'var(--p-soft)',
              fontFamily: QB, fontWeight: 800, fontSize: 15,
            }}>Отмена</button>
            <button onClick={save} disabled={!canSave} className="pbtn" style={{
              flex: 1, height: 50, border: 'none', cursor: canSave ? 'pointer' : 'default',
              borderRadius: 15, background: canSave ? 'var(--c-coral)' : 'var(--p-ctrl)',
              color: canSave ? '#fff' : 'var(--p-faint)',
              fontFamily: QB, fontWeight: 800, fontSize: 15.5,
              boxShadow: canSave ? '0 14px 28px -14px rgba(255,111,97,0.95)' : 'none',
            }}>Добавить практику</button>
          </div>
        </div>
      </div>
    </React.Fragment>
  );
}

/* ── главный экран приложения ──────────────────────────────────── */
function BuilderScreen({ dark = false, onBack }) {
  const [items, setItems] = uB(SEED);
  const [adding, setAdding] = uB(false);
  const [toast, setToast] = uB(null);
  const [fCat, setFCat] = uB(null);   // выбранная категория-фильтр
  const [fTag, setFTag] = uB(null);   // выбранный хэштег-фильтр

  // уникальные хэштеги из всех практик (в порядке появления)
  const allTags = [];
  items.forEach((p) => p.tags.forEach((t) => { if (!allTags.includes(t)) allTags.push(t); }));

  // применяем фильтры
  const shown = items.filter((p) =>
    (!fCat || p.cat === fCat) && (!fTag || p.tags.includes(fTag)));

  const add = (p) => {
    setItems((prev) => [p, ...prev]);
    setAdding(false);
    setToast(`«${p.name}» добавлена`);
    clearTimeout(add._t);
    add._t = setTimeout(() => setToast(null), 1900);
  };

  return (
    <div className={'ylp' + (dark ? ' dark' : '')} style={{ height: '100%' }}>
      <div className="pscroll" style={{ paddingBottom: 96 }}>
        {/* отступ под Dynamic Island */}
        <div style={{ height: 58 }} />

        {/* шапка с «назад» */}
        <div style={{ padding: '2px 14px 0', display: 'flex', alignItems: 'center', gap: 10 }}>
          <button onClick={onBack} className="pbtn tap" aria-label="Назад" style={{
            width: 40, height: 40, borderRadius: 13, flexShrink: 0, border: '1px solid var(--p-border)',
            background: 'var(--p-elev)', color: 'var(--p-ink)', cursor: 'pointer',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          }}><IconBack size={20} /></button>
          <div style={{ minWidth: 0 }}>
            <h1 style={{ margin: 0, fontFamily: QB, fontWeight: 800, fontSize: 21, letterSpacing: '-0.02em', color: 'var(--p-ink)', lineHeight: 1.1 }}>Моя практика</h1>
          </div>
        </div>

        {/* поиск практики (пока без логики) */}
        <div style={{ padding: '16px 14px 4px' }}>
          <button className="tap" style={{
            display: 'flex', alignItems: 'center', gap: 10, width: '100%', height: 48, padding: '0 14px',
            borderRadius: 16, background: 'var(--p-ctrl)', border: '1px solid transparent', cursor: 'pointer',
            color: 'var(--p-faint)', textAlign: 'left',
          }}>
            <IconSearch size={19} style={{ flexShrink: 0 }} />
            <span style={{ fontFamily: "'Nunito Sans', system-ui, sans-serif", fontSize: 15.5, fontWeight: 600 }}>Поиск практики</span>
          </button>
        </div>

        {/* фильтр по категориям */}
        <div className="hrow" style={{ display: 'flex', gap: 8, overflowX: 'auto', padding: '12px 14px 2px' }}>
          <button onClick={() => setFCat(null)} className="pbtn tap" style={{
            flexShrink: 0, border: fCat ? '1.5px solid var(--p-border)' : '1.5px solid var(--p-ink)', cursor: 'pointer',
            padding: '7px 14px', borderRadius: 999,
            background: fCat ? 'var(--p-bg)' : 'var(--p-ink)', color: fCat ? 'var(--p-mute)' : 'var(--p-bg)',
            fontFamily: QB, fontWeight: 800, fontSize: 13,
          }}>Все</button>
          {PCATS.map((c) => {
            const on = c.id === fCat;
            return (
              <button key={c.id} onClick={() => setFCat(on ? null : c.id)} className="pbtn tap" style={{
                flexShrink: 0, border: on ? `1.5px solid ${c.c}` : '1.5px solid var(--p-border)', cursor: 'pointer',
                padding: '7px 13px', borderRadius: 999, whiteSpace: 'nowrap',
                background: on ? c.t : 'var(--p-bg)', color: on ? c.c : 'var(--p-mute)',
                fontFamily: QB, fontWeight: 800, fontSize: 13,
              }}>{c.e} {c.label}</button>
            );
          })}
        </div>

        {/* быстрый поиск по хэштегам */}
        {allTags.length > 0 && (
          <div className="hrow" style={{ display: 'flex', gap: 7, overflowX: 'auto', padding: '9px 14px 2px' }}>
            {allTags.map((t, i) => {
              const on = t === fTag;
              return (
                <button key={i} onClick={() => setFTag(on ? null : t)} className="pbtn tap" style={{
                  flexShrink: 0, border: 'none', cursor: 'pointer', whiteSpace: 'nowrap',
                  padding: '6px 12px', borderRadius: 9,
                  background: on ? 'var(--c-sky)' : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-soft)',
                  fontFamily: QB, fontWeight: 700, fontSize: 12.5,
                }}>#{t}</button>
              );
            })}
          </div>
        )}

        {/* заголовок списка + добавить */}
        <div style={{ padding: '14px 16px 8px', display: 'flex', alignItems: 'baseline', gap: 8 }}>
          <h2 style={{ margin: 0, fontFamily: QB, fontWeight: 800, fontSize: 16, color: 'var(--p-ink)' }}>Практики</h2>
          <span style={{ fontFamily: QB, fontWeight: 800, fontSize: 13, color: 'var(--p-faint)' }}>{shown.length}</span>
          <span style={{ flex: 1 }} />
          <button onClick={() => setAdding(true)} className="tap" style={{
            border: 'none', background: 'transparent', cursor: 'pointer', padding: 0,
            color: 'var(--c-coral)', fontFamily: QB, fontWeight: 800, fontSize: 13.5,
            display: 'inline-flex', alignItems: 'center', gap: 4,
          }}><IconPlus size={16} /> Добавить</button>
        </div>

        {/* список практик */}
        <div style={{ padding: '0 14px', display: 'flex', flexDirection: 'column', gap: 12 }}>
          {shown.map((p) => <PracticeCard key={p.id} p={p} />)}

          {shown.length === 0 && (
            <div style={{ textAlign: 'center', padding: '40px 24px 8px', color: 'var(--p-faint)' }}>
              <div style={{ fontSize: 30 }}>🧘</div>
              <div style={{ fontFamily: QB, fontWeight: 800, fontSize: 14.5, color: 'var(--p-mute)', marginTop: 8 }}>Нет практик по фильтру</div>
              <button onClick={() => { setFCat(null); setFTag(null); }} className="tap" style={{
                marginTop: 12, border: 'none', cursor: 'pointer', padding: '8px 16px', borderRadius: 999,
                background: 'var(--p-ctrl)', color: 'var(--c-coral)', fontFamily: QB, fontWeight: 800, fontSize: 13,
              }}>Сбросить фильтры</button>
            </div>
          )}

          {/* пунктирная плитка «добавить» */}
          {shown.length > 0 && (
            <button onClick={() => setAdding(true)} className="pbtn tap" style={{
              border: '1.5px dashed var(--p-faint)', background: 'transparent', cursor: 'pointer',
              borderRadius: 22, padding: '16px', color: 'var(--p-mute)',
              display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
              fontFamily: QB, fontWeight: 800, fontSize: 14.5,
            }}><IconPlus size={18} style={{ color: 'var(--c-coral)' }} /> Добавить практику</button>
          )}
        </div>
      </div>

      {/* нижняя навигация (как на остальных страницах) */}
      <window.AppsBottomNav />

      {/* шторка добавления */}
      {adding && <AddSheet onClose={() => setAdding(false)} onSave={add} />}

      {/* тост */}
      {toast && (
        <div className="ptoast" style={{
          position: 'absolute', bottom: 96, left: '50%', transform: 'translateX(-50%)', zIndex: 60,
          padding: '12px 18px', borderRadius: 16, maxWidth: '80%',
          background: 'var(--p-ink)', color: 'var(--p-bg)',
          fontFamily: QB, fontWeight: 700, fontSize: 13.5, whiteSpace: 'nowrap',
          boxShadow: '0 18px 34px -16px rgba(0,0,0,0.6)',
        }}>{toast}</div>
      )}
    </div>
  );
}

Object.assign(window, { BuilderScreen });
