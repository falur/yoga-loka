// classes-sheets.jsx — шторки страницы «Занятия»:
//   ClassDetailsMine   — подробности моего занятия (изменить / удалить / ссылка)
//   ClassDetailsJoined — подробности чужого занятия (смотреть / не участвовать)
//   ClassEditorSheet   — создать / изменить занятие + выбор аудитории
//   AudiencePickerSheet— выбор аккаунтов, которым видно занятие
//   ConfirmSheet       — подтверждение (удалить / не участвовать)
// Общие примитивы берём из window (ClassCover, AudAva, …).

const { useState: uS2, useMemo: uM2 } = React;

const GRAB = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 4px' };
const SHEET_WRAP = { background: 'var(--p-elev)', borderRadius: 24, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' };
const Q = "'Quicksand', sans-serif";
const FIELD_LBL = { fontFamily: Q, fontWeight: 800, fontSize: 11.5, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 9 };

function metaChip(e, t) {
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '7px 12px', borderRadius: 999, backgroundColor: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: Q, fontWeight: 700, fontSize: 12.5 }}>
      <span style={{ fontSize: 14 }}>{e}</span>{t}
    </span>);
}
function infoRow(e, label, value) {
  return (
    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12, padding: '11px 0', borderTop: '1px solid var(--p-border)' }}>
      <span style={{ fontSize: 19, width: 22, textAlign: 'center', flexShrink: 0 }}>{e}</span>
      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</div>
        <div style={{ fontFamily: Q, fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)', marginTop: 2, textWrap: 'pretty' }}>{value}</div>
      </div>
    </div>);
}
function ClassHero({ p }) {
  const tone = window.C_TONE[p.tone] || window.C_TONE.a;
  const offline = p.place === 'studio';
  const heroBadge = offline ? { e: '📍', t: 'Живое занятие', c: 'var(--c-grape)' }
    : (p.live ? { e: '🔴', t: 'Прямой эфир', c: 'var(--c-coral)' }
      : (p.when && /Запись/.test(p.when) ? { e: '🎞', t: 'Запись', c: tone.pop } : { e: '📅', t: 'Скоро', c: 'var(--c-grape)' }));
  const priceLabel = p.free ? 'Бесплатно' : (p.price || null);
  const [a, b] = (window.PHOTO_MAP[p.image]) || ['#FFB020', '#FF6F61'];
  return (
    <div style={{ position: 'relative' }}>
      <div style={{ width: '100%', height: 158, background: `linear-gradient(135deg, ${a}, ${b})`, overflow: 'hidden' }}>
        <window.PImg src={window.REAL_IMG[p.image]} />
      </div>
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, rgba(20,14,30,0.30), rgba(20,14,30,0))' }} />
      <span style={{ position: 'absolute', top: 12, left: 14, display: 'inline-flex', alignItems: 'center', gap: 5, padding: '6px 11px', borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.94)', color: heroBadge.c, fontFamily: Q, fontWeight: 800, fontSize: 12 }}>{heroBadge.e} {heroBadge.t}</span>
      {priceLabel &&
        <span style={{ position: 'absolute', bottom: 12, right: 14, padding: '6px 13px', borderRadius: 999, backgroundColor: 'rgba(20,14,30,0.6)', color: '#fff', fontFamily: Q, fontWeight: 800, fontSize: 13.5, backdropFilter: 'blur(4px)', WebkitBackdropFilter: 'blur(4px)' }}>{priceLabel}</span>}
    </div>);
}
function chipsRow(p) {
  const offline = p.place === 'studio';
  const [ke, kl] = offline ? ['📍', 'Очно'] : (window.C_KIND[p.kind] || window.C_KIND.video);
  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7, marginTop: 12 }}>
      {metaChip(ke, kl)}{metaChip('⏱', p.dur)}{metaChip('🎚', p.level)}
    </div>);
}

/* ── подробности МОЕГО занятия ─────────────────────────────────── */
function ClassDetailsMine({ host, p, onClose, onEdit, onDelete, onShare }) {
  if (!host) return null;
  const tone = window.C_TONE[p.tone] || window.C_TONE.a;
  const vis = window.visSummary(p.visibility);
  const offline = p.place === 'studio';
  const v = p.venue || {};
  const selected = p.visibility && p.visibility.mode === 'selected';
  const accs = selected ? (p.visibility.accounts || []).map((id) => window.C_AUD[id]).filter(Boolean) : [];

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={SHEET_WRAP}>
          <div style={GRAB} />
          <div style={{ maxHeight: 430, overflowY: 'auto', scrollbarWidth: 'none' }}>
            <ClassHero p={p} />
            <div style={{ padding: '15px 18px 4px' }}>
              <h3 style={{ margin: 0, fontFamily: Q, fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', lineHeight: 1.25, textWrap: 'pretty' }}>{p.title}</h3>
              {chipsRow(p)}

              {/* кто видит занятие */}
              <div style={{ marginTop: 14, padding: 13, borderRadius: 16, backgroundColor: vis.tint }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span style={{ flex: 1, fontSize: 11, fontWeight: 800, color: vis.c, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Кто видит занятие</span>
                  <button className="tap" onClick={() => { onClose(); onEdit(p); }} style={{ border: 'none', cursor: 'pointer', padding: '5px 12px', borderRadius: 999, backgroundColor: 'var(--p-elev)', color: vis.c, fontFamily: Q, fontWeight: 800, fontSize: 12 }}>✎ Доступ</button>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 10 }}>
                  <span style={{ fontSize: 18 }}>{vis.e}</span>
                  <span style={{ fontFamily: Q, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)' }}>{vis.t}</span>
                </div>
                {selected && accs.length > 0 &&
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 11 }}>
                    {accs.map((a) =>
                      <span key={a.id} style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '5px 10px 5px 5px', borderRadius: 999, background: 'var(--p-elev)' }}>
                        <window.AudAva a={a} size={22} ring="none" />
                        <span style={{ fontFamily: Q, fontWeight: 700, fontSize: 12, color: 'var(--p-soft)' }}>{a.name}</span>
                      </span>)}
                  </div>}
                {p.visibility && p.visibility.mode === 'link' &&
                  <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--p-soft)', marginTop: 8, lineHeight: 1.45 }}>Занятие видно только тем, у кого есть прямая ссылка.</div>}
                {(!p.visibility || p.visibility.mode === 'all') &&
                  <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--p-soft)', marginTop: 8, lineHeight: 1.45 }}>Видно всем вашим подписчикам в Yoga Loka.</div>}
              </div>

              {/* ведущие (вы + соведущие) + участники */}
              {(() => {
                const leads = window.myLeads(p);
                const co = leads.filter((t) => t.id !== 'self');
                return (
                  <div style={{ marginTop: 13, padding: 13, borderRadius: 16, backgroundColor: 'var(--p-ctrl)' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <span style={{ flex: 1, fontSize: 11, fontWeight: 800, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{co.length ? 'Ведущие' : 'Ведущий'}</span>
                      <span style={{ display: 'inline-flex', alignItems: 'baseline', gap: 5 }}>
                        <span style={{ fontFamily: Q, fontWeight: 800, fontSize: 16, color: 'var(--p-ink)' }}>{p.participants}</span>
                        <span style={{ fontSize: 10.5, fontWeight: 700, color: 'var(--p-faint)' }}>участников</span>
                      </span>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 11, marginTop: 12 }}>
                      {leads.map((t) =>
                        <div key={t.id} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                          <span style={{ width: 36, height: 36, borderRadius: '50%', overflow: 'hidden', flexShrink: 0, background: 'var(--t-coral)', border: '2px solid var(--p-elev)', display: 'inline-flex' }}><window.PImg src={t.id === 'self' ? window.AVA_SELF : window.teacherPhoto(t)} /></span>
                          <div style={{ minWidth: 0 }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontFamily: Q, fontWeight: 800, fontSize: 14, color: 'var(--p-ink)' }}>
                              {t.id === 'self' ? 'Вы' : t.spiritual}
                              {t.id === 'self' && <span style={{ fontSize: 10, fontWeight: 800, color: tone.pop, backgroundColor: tone.tint, padding: '2px 7px', borderRadius: 999 }}>ВЫ</span>}
                            </div>
                            <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-mute)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.id === 'self' ? ((window.PROFILE || {}).role || '') : `${t.name} · ${t.role}`}</div>
                          </div>
                        </div>)}
                    </div>
                  </div>);
              })()}

              {p.desc && <p style={{ margin: '14px 0 0', fontSize: 14, lineHeight: 1.55, color: 'var(--p-soft)', textWrap: 'pretty' }}>{p.desc}</p>}

              <div style={{ marginTop: 14 }}>
                {p.when && infoRow('📅', 'Расписание', p.when)}
                {offline && v.studio && infoRow('🏠', 'Где', v.studio)}
                {offline && v.address && infoRow('🧭', 'Адрес', v.address + (v.metro ? ` · м. ${v.metro}` : ''))}
                {infoRow(p.free ? '🎁' : '💳', 'Доступ', p.free ? 'Бесплатное занятие' : `Платное · ${p.price || '—'}`)}
              </div>
            </div>
          </div>

          {/* действия */}
          <div style={{ padding: '12px 16px 22px', borderTop: '1px solid var(--p-border)', background: 'var(--p-elev)', display: 'flex', gap: 9 }}>
            <button className="pbtn" onClick={() => { onClose(); onDelete(p); }} title="Удалить" style={{ width: 52, height: 52, flexShrink: 0, borderRadius: 16, border: '1.5px solid var(--p-border)', background: 'transparent', cursor: 'pointer', color: 'var(--c-coral)', fontSize: 20 }}>🗑</button>
            <button className="pbtn" onClick={() => { onClose(); onEdit(p); }} style={{ flex: 1, height: 52, borderRadius: 16, border: 'none', cursor: 'pointer', background: 'var(--c-coral)', color: '#fff', fontFamily: Q, fontWeight: 800, fontSize: 15, boxShadow: '0 8px 20px -8px rgba(255,111,97,0.9)' }}>✎  Изменить занятие</button>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

/* ── подробности ЧУЖОГО занятия (я участвую) ──────────────────── */
function ClassDetailsJoined({ host, p, onClose, onLeave }) {
  if (!host) return null;
  const tone = window.C_TONE[p.tone] || window.C_TONE.a;
  const t = window.C_TEACHERS[p.teacher];
  const offline = p.place === 'studio';
  const v = p.venue || {};
  const watchLabel = p.live ? '▶  Подключиться к эфиру' : (p.kind === 'audio' ? '▶  Слушать' : '▶  Смотреть');

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={SHEET_WRAP}>
          <div style={GRAB} />
          <div style={{ maxHeight: 430, overflowY: 'auto', scrollbarWidth: 'none' }}>
            <ClassHero p={p} />
            <div style={{ padding: '15px 18px 4px' }}>
              <h3 style={{ margin: 0, fontFamily: Q, fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', lineHeight: 1.25, textWrap: 'pretty' }}>{p.title}</h3>
              {chipsRow(p)}

              {/* ведущий */}
              {t &&
                <div style={{ marginTop: 14, padding: 13, borderRadius: 16, backgroundColor: tone.tint, display: 'flex', alignItems: 'center', gap: 11 }}>
                  <span style={{ width: 40, height: 40, borderRadius: '50%', overflow: 'hidden', flexShrink: 0, background: 'var(--p-elev)', border: '2px solid var(--p-elev)', display: 'inline-flex' }}><window.PImg src={window.teacherPhoto(t)} /></span>
                  <div style={{ minWidth: 0 }}>
                    <div style={{ fontSize: 10.5, fontWeight: 800, color: tone.pop, textTransform: 'uppercase', letterSpacing: '0.05em' }}>Ведущий</div>
                    <div style={{ fontFamily: Q, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', marginTop: 1 }}>{t.spiritual}</div>
                    <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-mute)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.name} · {t.role}</div>
                  </div>
                </div>}

              {/* статус участия */}
              <div style={{ marginTop: 12, display: 'flex', alignItems: 'center', gap: 8, padding: '10px 13px', borderRadius: 14, background: 'var(--t-leaf)' }}>
                <span style={{ fontSize: 16 }}>✓</span>
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--p-soft)' }}>Вы участвуете в этом занятии</span>
              </div>

              {p.desc && <p style={{ margin: '14px 0 0', fontSize: 14, lineHeight: 1.55, color: 'var(--p-soft)', textWrap: 'pretty' }}>{p.desc}</p>}

              <div style={{ marginTop: 14 }}>
                {p.when && infoRow('📅', 'Когда', p.when)}
                {offline && v.studio && infoRow('🏠', 'Где', v.studio)}
                {offline && v.address && infoRow('🧭', 'Адрес', v.address + (v.metro ? ` · м. ${v.metro}` : ''))}
                {infoRow(p.free ? '🎁' : '💳', 'Доступ', p.free ? 'Бесплатно' : `Оплачено · ${p.price || '—'}`)}
              </div>
            </div>
          </div>

          {/* действия: смотреть + не участвовать */}
          <div style={{ padding: '12px 16px 22px', borderTop: '1px solid var(--p-border)', background: 'var(--p-elev)' }}>
            <button className="pbtn" style={{ width: '100%', height: 52, borderRadius: 16, border: 'none', cursor: 'pointer', background: tone.pop, color: '#fff', fontFamily: Q, fontWeight: 800, fontSize: 15, boxShadow: `0 8px 20px -8px ${tone.pop}` }}>{watchLabel}</button>
            <button className="pbtn" onClick={() => { onClose(); onLeave(p); }} style={{ width: '100%', height: 48, marginTop: 9, borderRadius: 16, border: '1.5px solid var(--p-border)', cursor: 'pointer', background: 'transparent', color: 'var(--c-coral)', fontFamily: Q, fontWeight: 800, fontSize: 14.5 }}>Не участвовать</button>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

/* ── редактор: создать / изменить занятие ──────────────────────── */
const FORMATS = [['video', '🎬', 'Видео'], ['audio', '🎧', 'Аудио'], ['studio', '📍', 'Очно']];
const LEVELS = ['Начало', 'Любой', 'Продвинуто'];
const VIS_MODES = [['all', '🌐', 'Все подписчики'], ['selected', '🔒', 'Выбранные'], ['link', '🔗', 'По ссылке']];

function ClassEditorSheet({ host, mode, draft, onClose, onSave }) {
  const [d, setD] = uS2(() => ({ ...draft, visibility: { mode: 'all', accounts: [], ...(draft.visibility || {}) } }));
  const [pick, setPick] = uS2(false);
  if (!host) return null;

  const set = (patch) => setD((s) => ({ ...s, ...patch }));
  const fmt = d.place === 'studio' ? 'studio' : d.kind;
  const setFormat = (f) => {
    if (f === 'studio') set({ place: 'studio', kind: 'video', tone: 'a' });
    else set({ place: 'online', kind: f, tone: f === 'audio' ? 'c' : 'a' });
  };
  const setVisMode = (m) => set({ visibility: { ...d.visibility, mode: m } });
  const setAccounts = (ids) => set({ visibility: { ...d.visibility, mode: 'selected', accounts: ids } });
  const co = d.co || [];
  const toggleCo = (id) => set({ co: co.includes(id) ? co.filter((x) => x !== id) : [...co, id] });
  const coTeachers = (window.TEACHERS || []).filter((t) => t.id !== 'self');

  const inp = {
    width: '100%', boxSizing: 'border-box', height: 46, borderRadius: 13, border: '1.5px solid var(--p-border)',
    background: 'var(--p-bg)', color: 'var(--p-ink)', padding: '0 14px', fontFamily: "'Nunito Sans', sans-serif",
    fontWeight: 600, fontSize: 14.5, outline: 'none',
  };
  const seg = (on, c) => ({
    flex: 1, height: 42, border: 'none', cursor: 'pointer', borderRadius: 11, fontFamily: Q, fontWeight: 800, fontSize: 13,
    backgroundColor: on ? 'var(--p-elev)' : 'transparent', color: on ? (c || 'var(--p-ink)') : 'var(--p-mute)',
    boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 5, transition: 'all .14s ease',
  });

  const vis = window.visSummary(d.visibility);
  const canSave = d.title.trim().length > 0;

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={SHEET_WRAP}>
          <div style={GRAB} />
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '2px 18px 12px' }}>
            <span style={{ fontFamily: Q, fontWeight: 800, fontSize: 19, color: 'var(--p-ink)' }}>{mode === 'create' ? 'Новое занятие' : 'Изменить занятие'}</span>
            <button className="tap" onClick={onClose} style={{ border: 'none', background: 'var(--p-ctrl)', cursor: 'pointer', width: 32, height: 32, borderRadius: '50%', color: 'var(--p-mute)', fontSize: 16, fontWeight: 800 }}>✕</button>
          </div>

          <div style={{ maxHeight: 440, overflowY: 'auto', scrollbarWidth: 'none', padding: '0 18px' }}>
            {/* название */}
            <div style={FIELD_LBL}>Название</div>
            <input autoFocus value={d.title} onChange={(e) => set({ title: e.target.value })} placeholder="Например, Мягкое утро · Хатха" style={inp} />

            {/* формат */}
            <div style={{ ...FIELD_LBL, marginTop: 18 }}>Формат</div>
            <div style={{ display: 'flex', gap: 6, backgroundColor: 'var(--p-ctrl)', borderRadius: 14, padding: 4 }}>
              {FORMATS.map(([id, e, l]) => <button key={id} onClick={() => setFormat(id)} style={seg(fmt === id)}><span style={{ fontSize: 15 }}>{e}</span>{l}</button>)}
            </div>

            {/* уровень + длительность */}
            <div style={{ display: 'flex', gap: 12, marginTop: 18 }}>
              <div style={{ flex: 1 }}>
                <div style={FIELD_LBL}>Уровень</div>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
                  {LEVELS.map((l) => {
                    const on = d.level === l;
                    return <button key={l} onClick={() => set({ level: l })} style={{ border: 'none', cursor: 'pointer', padding: '8px 12px', borderRadius: 999, fontFamily: Q, fontWeight: 700, fontSize: 12.5, backgroundColor: on ? 'var(--ctrl-accent)' : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-soft)', transition: 'all .14s ease' }}>{l}</button>;
                  })}
                </div>
              </div>
              <div style={{ width: 104 }}>
                <div style={FIELD_LBL}>Длит.</div>
                <input value={d.dur} onChange={(e) => set({ dur: e.target.value })} placeholder="30 мин" style={inp} />
              </div>
            </div>

            {/* расписание */}
            <div style={{ ...FIELD_LBL, marginTop: 18 }}>Расписание</div>
            <input value={d.when} onChange={(e) => set({ when: e.target.value })} placeholder="Например, Чт · 20:00" style={inp} />

            {/* очно — студия */}
            {d.place === 'studio' &&
              <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 8 }}>
                <input value={(d.venue && d.venue.studio) || ''} onChange={(e) => set({ venue: { ...(d.venue || {}), studio: e.target.value } })} placeholder="Название студии" style={inp} />
                <input value={(d.venue && d.venue.address) || ''} onChange={(e) => set({ venue: { ...(d.venue || {}), address: e.target.value } })} placeholder="Адрес" style={inp} />
              </div>}

            {/* доступ / цена */}
            <div style={{ ...FIELD_LBL, marginTop: 18 }}>Доступ</div>
            <div style={{ display: 'flex', gap: 6, backgroundColor: 'var(--p-ctrl)', borderRadius: 14, padding: 4 }}>
              <button onClick={() => set({ free: true })} style={seg(d.free, 'var(--c-leaf)')}>🎁 Бесплатно</button>
              <button onClick={() => set({ free: false })} style={seg(!d.free, 'var(--c-coral)')}>💳 Платно</button>
            </div>
            {!d.free &&
              <input value={d.price} onChange={(e) => set({ price: e.target.value })} placeholder="Цена, например 500 ₽" style={{ ...inp, marginTop: 9 }} />}

            {/* описание */}
            <div style={{ ...FIELD_LBL, marginTop: 18 }}>Описание</div>
            <textarea value={d.desc} onChange={(e) => set({ desc: e.target.value })} placeholder="О чём занятие, для кого, что взять с собой…" rows={3} style={{ ...inp, height: 'auto', padding: '12px 14px', lineHeight: 1.5, resize: 'none' }} />

            {/* соведущие — вы ведёте всегда; можно добавить второго учителя */}
            <div style={{ ...FIELD_LBL, marginTop: 20 }}>Соведущие <span style={{ textTransform: 'none', letterSpacing: 0, color: 'var(--p-faint)', fontWeight: 700 }}>· необязательно</span></div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 9 }}>
              <span style={{ width: 30, height: 30, borderRadius: '50%', overflow: 'hidden', flexShrink: 0, background: 'var(--t-coral)', border: '2px solid var(--p-elev)', display: 'inline-flex' }}><window.PImg src={window.AVA_SELF} /></span>
              <span style={{ fontFamily: Q, fontWeight: 700, fontSize: 13, color: 'var(--p-soft)' }}>Вы ведёте это занятие</span>
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
              {coTeachers.map((t) => {
                const on = co.includes(t.id);
                return (
                  <button key={t.id} className="tap pbtn" onClick={() => toggleCo(t.id)} style={{
                    display: 'inline-flex', alignItems: 'center', gap: 7, border: 'none', cursor: 'pointer',
                    padding: '6px 12px 6px 6px', borderRadius: 999,
                    backgroundColor: on ? 'var(--c-coral)' : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-soft)',
                    fontFamily: Q, fontWeight: 700, fontSize: 12.5,
                    boxShadow: on ? '0 5px 14px -7px rgba(255,111,97,0.9)' : 'none', transition: 'all .14s ease',
                  }}>
                    <span style={{ width: 24, height: 24, borderRadius: '50%', flexShrink: 0, overflow: 'hidden', background: 'var(--t-sky)', display: 'inline-flex' }}><window.PImg src={window.teacherPhoto(t)} /></span>
                    {t.spiritual}
                    <span style={{ fontSize: 12, marginLeft: 1 }}>{on ? '✓' : '+'}</span>
                  </button>);
              })}
            </div>

            {/* ── КТО ВИДИТ ЗАНЯТИЕ ── */}
            <div style={{ ...FIELD_LBL, marginTop: 20 }}>Кто видит занятие</div>
            <div style={{ display: 'flex', gap: 6, backgroundColor: 'var(--p-ctrl)', borderRadius: 14, padding: 4 }}>
              {VIS_MODES.map(([id, e, l]) => <button key={id} onClick={() => setVisMode(id)} style={seg(d.visibility.mode === id, vis.c)}><span style={{ fontSize: 14 }}>{e}</span><span style={{ fontSize: 12 }}>{l}</span></button>)}
            </div>

            {d.visibility.mode === 'all' &&
              <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--p-faint)', marginTop: 9, lineHeight: 1.45 }}>Занятие увидят все ваши подписчики в Yoga Loka.</div>}
            {d.visibility.mode === 'link' &&
              <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--p-faint)', marginTop: 9, lineHeight: 1.45 }}>Занятие скрыто из ленты — откроется только по прямой ссылке, которую вы пришлёте.</div>}
            {d.visibility.mode === 'selected' &&
              <button className="tap pbtn" onClick={() => setPick(true)} style={{ width: '100%', marginTop: 10, padding: '12px 14px', borderRadius: 16, border: '1.5px solid var(--c-coral)', background: 'var(--t-coral)', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 11, textAlign: 'left' }}>
                {d.visibility.accounts.length > 0
                  ? <window.AudStack ids={d.visibility.accounts} size={30} max={5} />
                  : <span style={{ width: 36, height: 36, borderRadius: '50%', background: 'var(--p-elev)', color: 'var(--c-coral)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 18, flexShrink: 0 }}>＋</span>}
                <span style={{ flex: 1, minWidth: 0 }}>
                  <span style={{ display: 'block', fontFamily: Q, fontWeight: 800, fontSize: 14, color: 'var(--p-ink)' }}>{d.visibility.accounts.length > 0 ? `${d.visibility.accounts.length} ${window.plAcc(d.visibility.accounts.length)}` : 'Выбрать аккаунты'}</span>
                  <span style={{ display: 'block', fontSize: 11.5, fontWeight: 600, color: 'var(--c-coral)' }}>Нажмите, чтобы изменить список</span>
                </span>
                <span style={{ fontSize: 20, color: 'var(--c-coral)', flexShrink: 0 }}>›</span>
              </button>}

            <div style={{ height: 8 }} />
          </div>

          {/* сохранить */}
          <div style={{ padding: '12px 16px 22px', borderTop: '1px solid var(--p-border)', background: 'var(--p-elev)' }}>
            <button className="pbtn" disabled={!canSave} onClick={() => onSave(d)} style={{
              width: '100%', height: 52, borderRadius: 16, border: 'none', cursor: canSave ? 'pointer' : 'default',
              background: canSave ? 'var(--c-coral)' : 'var(--p-ctrl)', color: canSave ? '#fff' : 'var(--p-faint)',
              fontFamily: Q, fontWeight: 800, fontSize: 15, boxShadow: canSave ? '0 8px 20px -8px rgba(255,111,97,0.9)' : 'none',
            }}>{mode === 'create' ? 'Создать занятие' : 'Сохранить изменения'}</button>
          </div>
        </div>
      </div>

      {pick && <AudiencePickerSheet host={host} selected={d.visibility.accounts} onClose={() => setPick(false)} onDone={(ids) => { setAccounts(ids); setPick(false); }} />}
    </React.Fragment>, host);
}

/* ── выбор аккаунтов аудитории ─────────────────────────────────── */
function AudiencePickerSheet({ host, selected, onClose, onDone }) {
  const [sel, setSel] = uS2(() => new Set(selected || []));
  const [q, setQ] = uS2('');
  if (!host) return null;
  const all = window.AUDIENCE || [];
  const list = uM2(() => {
    const s = q.trim().toLowerCase();
    return s ? all.filter((a) => (a.name + ' ' + a.handle).toLowerCase().includes(s)) : all;
  }, [q]);
  const toggle = (id) => setSel((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const allSelected = sel.size === all.length;
  const toggleAll = () => setSel(allSelected ? new Set() : new Set(all.map((a) => a.id)));
  const n = sel.size;

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" style={{ zIndex: 44 }} onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px', zIndex: 45 }}>
        <div style={SHEET_WRAP}>
          <div style={GRAB} />
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '2px 18px 12px' }}>
            <div>
              <div style={{ fontFamily: Q, fontWeight: 800, fontSize: 18, color: 'var(--p-ink)' }}>Кто видит занятие</div>
              <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--p-faint)', marginTop: 1 }}>Отметьте аккаунты, которым оно доступно</div>
            </div>
            <button className="tap" onClick={toggleAll} style={{ border: 'none', background: 'var(--p-ctrl)', cursor: 'pointer', padding: '7px 12px', borderRadius: 999, color: 'var(--ctrl-accent)', fontFamily: Q, fontWeight: 800, fontSize: 12.5 }}>{allSelected ? 'Снять всё' : 'Выбрать всех'}</button>
          </div>

          {/* поиск */}
          <div style={{ padding: '0 18px 10px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 44, borderRadius: 13, background: 'var(--p-ctrl)', padding: '0 14px' }}>
              <span style={{ fontSize: 15, opacity: 0.6 }}>🔍</span>
              <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Поиск по имени или @нику" style={{ flex: 1, border: 'none', background: 'transparent', outline: 'none', fontFamily: "'Nunito Sans', sans-serif", fontWeight: 600, fontSize: 14, color: 'var(--p-ink)' }} />
              {q && <button className="tap" onClick={() => setQ('')} style={{ border: 'none', background: 'none', cursor: 'pointer', color: 'var(--p-faint)', fontSize: 15 }}>✕</button>}
            </div>
          </div>

          {/* список */}
          <div style={{ maxHeight: 320, overflowY: 'auto', scrollbarWidth: 'none', padding: '0 10px' }}>
            {list.map((a) => {
              const on = sel.has(a.id);
              return (
                <button key={a.id} className="tap" onClick={() => toggle(a.id)} style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', border: 'none', background: on ? 'var(--p-ctrl)' : 'transparent', cursor: 'pointer', padding: '10px 12px', borderRadius: 14, textAlign: 'left', transition: 'background .12s ease', marginBottom: 2 }}>
                  <window.AudAva a={a} size={42} ring="none" />
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontFamily: Q, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{a.name}</div>
                    <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-faint)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{a.handle} · {a.note}</div>
                  </div>
                  <span style={{ width: 26, height: 26, flexShrink: 0, borderRadius: '50%', border: on ? 'none' : '2px solid var(--p-border)', background: on ? 'var(--c-coral)' : 'transparent', color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 14, fontWeight: 900, transition: 'all .14s ease' }}>{on ? '✓' : ''}</span>
                </button>);
            })}
            {list.length === 0 && <div style={{ textAlign: 'center', padding: '30px', color: 'var(--p-faint)', fontSize: 13, fontWeight: 600 }}>Никого не нашлось</div>}
          </div>

          {/* готово */}
          <div style={{ padding: '12px 16px 22px', borderTop: '1px solid var(--p-border)', background: 'var(--p-elev)' }}>
            <button className="pbtn" onClick={() => onDone([...sel])} style={{ width: '100%', height: 52, borderRadius: 16, border: 'none', cursor: 'pointer', background: 'var(--c-coral)', color: '#fff', fontFamily: Q, fontWeight: 800, fontSize: 15, boxShadow: '0 8px 20px -8px rgba(255,111,97,0.9)' }}>Готово{n > 0 ? ` · ${n}` : ''}</button>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

/* ── подтверждение действия ────────────────────────────────────── */
function ConfirmSheet({ host, title, body, confirmLabel, danger, onCancel, onConfirm }) {
  if (!host) return null;
  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" style={{ zIndex: 46 }} onClick={onCancel} />
      <div className="psheet" style={{ padding: '0 8px 12px', zIndex: 47 }}>
        <div style={{ ...SHEET_WRAP, padding: '8px 18px 20px' }}>
          <div style={GRAB} />
          <div style={{ textAlign: 'center', padding: '12px 4px 0' }}>
            <div style={{ width: 54, height: 54, margin: '0 auto', borderRadius: '50%', background: danger ? 'var(--t-coral)' : 'var(--p-ctrl)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 26 }}>{danger ? '🗑' : '👋'}</div>
            <div style={{ fontFamily: Q, fontWeight: 800, fontSize: 18.5, color: 'var(--p-ink)', marginTop: 13 }}>{title}</div>
            <div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--p-mute)', marginTop: 7, lineHeight: 1.5, textWrap: 'pretty' }}>{body}</div>
          </div>
          <div style={{ display: 'flex', gap: 9, marginTop: 18 }}>
            <button className="pbtn" onClick={onCancel} style={{ flex: 1, height: 50, borderRadius: 16, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-ink)', fontFamily: Q, fontWeight: 800, fontSize: 15 }}>Отмена</button>
            <button className="pbtn" onClick={onConfirm} style={{ flex: 1, height: 50, borderRadius: 16, border: 'none', cursor: 'pointer', background: danger ? 'var(--c-coral)' : 'var(--ctrl-accent)', color: '#fff', fontFamily: Q, fontWeight: 800, fontSize: 15, boxShadow: danger ? '0 8px 20px -8px rgba(255,111,97,0.9)' : 'none' }}>{confirmLabel}</button>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

Object.assign(window, { ClassDetailsMine, ClassDetailsJoined, ClassEditorSheet, AudiencePickerSheet, ConfirmSheet });
