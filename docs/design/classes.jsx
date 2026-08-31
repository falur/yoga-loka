// classes.jsx — страница «Занятия»: мои занятия (создаю/меняю/удаляю)
//                и занятия, где я участвую (смотрю/не участвую).
// Шторки (редактор, аудитория, подробности) — в classes-sheets.jsx (window).
// Экспорт в window: ClassesScreen + общие примитивы (ClassCover, AudAva …).

const { useState: uC, useRef: uCr, useEffect: uCe } = React;

/* ── токены формата/тона (через CSS-переменные .ylp) ───────────── */
const C_TONE = {
  a: { tint: 'var(--t-coral)', pop: 'var(--c-coral)' },
  b: { tint: 'var(--t-sky)',   pop: 'var(--c-sky)' },
  c: { tint: 'var(--t-mint)',  pop: 'var(--c-mint)' },
};
const C_KIND = { video: ['🎬', 'Видео'], audio: ['🎧', 'Аудио'] };
const C_TEACHERS = Object.fromEntries((window.TEACHERS || []).map((t) => [t.id, t]));
const C_AUD = Object.fromEntries((window.AUDIENCE || []).map((a) => [a.id, a]));

/* кто видит занятие — сводка для бейджа */
function visSummary(v) {
  if (!v || v.mode === 'all') return { e: '🌐', t: 'Все подписчики', c: 'var(--c-sky)', tint: 'var(--t-sky)' };
  if (v.mode === 'link') return { e: '🔗', t: 'По ссылке', c: 'var(--c-grape)', tint: 'var(--t-grape)' };
  const n = (v.accounts || []).length;
  return { e: '🔒', t: `${n} ${window.plAcc(n)}`, c: 'var(--c-coral)', tint: 'var(--t-coral)' };
}

/* ── обложка-миниатюра занятия ─────────────────────────────────── */
function ClassCover({ image, size = 62, radius = 16, children }) {
  const [a, b] = (window.PHOTO_MAP[image]) || ['#FFB020', '#FF6F61'];
  return (
    <div style={{ width: size, height: size, borderRadius: radius, flexShrink: 0, position: 'relative', overflow: 'hidden', background: `linear-gradient(135deg, ${a}, ${b})` }}>
      <window.PImg src={window.REAL_IMG[image]} />
      {children}
    </div>);
}

/* ── аватар аккаунта-ученика (эмодзи в круге) ──────────────────── */
function AudAva({ a, size = 36, ring = '2px solid var(--p-elev)' }) {
  return (
    <span style={{
      width: size, height: size, borderRadius: '50%', flexShrink: 0,
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      fontSize: size * 0.5, background: (a.hue || '#999') + '24', border: ring, boxSizing: 'border-box',
    }}>{a.emoji}</span>);
}

/* стопка аватаров аудитории (перекрытие) */
function AudStack({ ids, size = 26, max = 4 }) {
  const list = (ids || []).map((id) => C_AUD[id]).filter(Boolean);
  const shown = list.slice(0, max);
  const rest = list.length - shown.length;
  return (
    <div style={{ display: 'inline-flex', alignItems: 'center' }}>
      {shown.map((a, i) =>
        <span key={a.id} style={{ marginLeft: i ? -size * 0.34 : 0, zIndex: max - i }}><AudAva a={a} size={size} /></span>)}
      {rest > 0 &&
        <span style={{
          marginLeft: -size * 0.34, width: size, height: size, borderRadius: '50%',
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          background: 'var(--p-ctrl)', border: '2px solid var(--p-elev)', boxSizing: 'border-box',
          fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: size * 0.36, color: 'var(--p-mute)',
        }}>+{rest}</span>}
    </div>);
}

/* мелкие чипы формата/длительности/уровня */
function KindChips({ p }) {
  const tone = C_TONE[p.tone] || C_TONE.a;
  const offline = p.place === 'studio';
  const [ke, kl] = offline ? ['📍', 'Очно'] : (C_KIND[p.kind] || C_KIND.video);
  return (
    <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '5px 7px', marginTop: 6 }}>
      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 700, color: tone.pop, backgroundColor: tone.tint, padding: '3px 8px', borderRadius: 999, whiteSpace: 'nowrap' }}>{ke} {kl}</span>
      <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-mute)', whiteSpace: 'nowrap' }}>⏱ {p.dur}</span>
      <span style={{ fontSize: 11, fontWeight: 600, color: 'var(--p-faint)' }}>{p.level}</span>
    </div>);
}

function whenLine(p) {
  const offline = p.place === 'studio';
  const c = offline ? 'var(--c-grape)' : (p.live ? 'var(--c-coral)' : 'var(--c-grape)');
  const e = offline ? '📅' : (p.live ? '🔴' : (/* запись */ p.when && /Запись/.test(p.when) ? '🎞' : '📅'));
  return { c, e };
}

/* запись (доступна всегда) или предстоящее (по расписанию / эфир) */
function isRecording(p) {
  return p.recording === true || (p.when && /Запис/i.test(p.when));
}
/* делим список на предстоящие и записи (порядок внутри групп сохраняем) */
function splitWhen(list) {
  const up = [], rec = [];
  (list || []).forEach((p) => (isRecording(p) ? rec : up).push(p));
  return { up, rec };
}

/* ── заголовок группы (Предстоящие / Записи) ───────────────────── */
function GroupLabel({ emoji, text, n, pop = 'var(--p-mute)', tint = 'var(--p-ctrl)' }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '8px 4px 0' }}>
      <span style={{ fontSize: 13, lineHeight: 1 }}>{emoji}</span>
      <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12, letterSpacing: '0.05em', textTransform: 'uppercase', color: 'var(--p-soft)' }}>{text}</span>
      <span style={{ fontSize: 10.5, fontWeight: 800, minWidth: 17, textAlign: 'center', padding: '1px 6px', borderRadius: 999, color: pop, backgroundColor: tint }}>{n}</span>
      <span style={{ flex: 1, height: 1, background: 'var(--p-border)' }} />
    </div>);
}

/* ведущие моего занятия: вы (self) + соведущие (p.co) */
function myLeads(p) {
  return ['self', ...((p.co) || [])].map((id) => C_TEACHERS[id]).filter(Boolean);
}
/* стопка фото-аватаров ведущих (перекрытие) */
function TeacherStack({ leads, size = 24 }) {
  return (
    <div style={{ display: 'inline-flex', flexShrink: 0 }}>
      {leads.map((t, i) =>
        <span key={t.id} style={{ width: size, height: size, borderRadius: '50%', overflow: 'hidden', flexShrink: 0, background: 'var(--t-coral)', border: '2px solid var(--p-elev)', boxSizing: 'border-box', marginLeft: i ? -size * 0.36 : 0, zIndex: 5 - i, display: 'inline-flex' }}>
          <window.PImg src={t.id === 'self' ? window.AVA_SELF : window.teacherPhoto(t)} />
        </span>)}
    </div>);
}

/* ── карточка: МОЁ занятие ─────────────────────────────────────── */
function MyClassCard({ p, onOpen, onMenu }) {
  const vis = visSummary(p.visibility);
  const wl = whenLine(p);
  const leads = myLeads(p);
  const co = leads.filter((t) => t.id !== 'self');
  return (
    <div className="tap pbtn" onClick={() => onOpen(p)} style={{ cursor: 'pointer', padding: 12, borderRadius: 20, backgroundColor: 'var(--p-elev)', border: '1px solid var(--p-border)', boxShadow: '0 8px 22px -16px rgba(40,20,60,0.5)' }}>
      <div style={{ display: 'flex', gap: 12 }}>
        <ClassCover image={p.image} size={62} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
            <div style={{ flex: 1, minWidth: 0, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)', lineHeight: 1.25, textWrap: 'pretty' }}>{p.title}</div>
            <button className="tap" onClick={(e) => { e.stopPropagation(); onMenu(p); }} style={{
              flexShrink: 0, marginTop: -4, marginRight: -4, width: 30, height: 30, borderRadius: 10, border: 'none',
              background: 'transparent', cursor: 'pointer', color: 'var(--p-faint)', fontSize: 18, lineHeight: 1, fontWeight: 800,
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            }}>⋯</button>
          </div>
          <KindChips p={p} />
          {p.when &&
            <div style={{ marginTop: 5, display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 700, color: wl.c }}>
              {wl.e} {p.when}{p.place === 'studio' && p.venue ? ` · ${p.venue.studio}` : ''}
            </div>}
          {/* соведущие: вы + второй учитель */}
          {co.length > 0 &&
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 7 }}>
              <TeacherStack leads={leads} size={22} />
              <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-mute)', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                Ведёте <span style={{ color: 'var(--p-soft)' }}>вы и {co.map((t) => t.spiritual).join(' · ')}</span>
              </span>
            </div>}
        </div>
      </div>

      {/* нижняя строка: кто видит + участники */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 11, paddingTop: 11, borderTop: '1px solid var(--p-border)' }}>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 11.5, fontWeight: 800, color: vis.c, backgroundColor: vis.tint, padding: '4px 10px', borderRadius: 999, whiteSpace: 'nowrap' }}>
          <span style={{ fontSize: 12 }}>{vis.e}</span>{vis.t}
        </span>
        {p.visibility && p.visibility.mode === 'selected' && p.visibility.accounts.length > 0 &&
          <AudStack ids={p.visibility.accounts} size={24} max={4} />}
        <span style={{ marginLeft: 'auto', display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 12, fontWeight: 800, color: 'var(--p-mute)', whiteSpace: 'nowrap' }}><IconUsers size={15} style={{ opacity: 0.85 }} /> {p.participants}</span>
      </div>
    </div>);
}

/* ── карточка: занятие, где Я УЧАСТВУЮ ─────────────────────────── */
function JoinedClassCard({ p, onOpen, onLeave }) {
  const wl = whenLine(p);
  const t = C_TEACHERS[p.teacher];
  return (
    <div className="tap pbtn" onClick={() => onOpen(p)} style={{ cursor: 'pointer', padding: 12, borderRadius: 20, backgroundColor: 'var(--p-elev)', border: '1px solid var(--p-border)', boxShadow: '0 8px 22px -16px rgba(40,20,60,0.5)' }}>
      <div style={{ display: 'flex', gap: 12 }}>
        <ClassCover image={p.image} size={62} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)', lineHeight: 1.25, textWrap: 'pretty' }}>{p.title}</div>
          <KindChips p={p} />
          {p.when &&
            <div style={{ marginTop: 5, display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 700, color: wl.c }}>
              {wl.e} {p.when}{p.place === 'studio' && p.venue ? ` · ${p.venue.studio}` : ''}
            </div>}
        </div>
      </div>

      {/* нижняя строка: ведущий + статус «вы участвуете» */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginTop: 11, paddingTop: 11, borderTop: '1px solid var(--p-border)' }}>
        {t &&
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 7, minWidth: 0 }}>
            <span style={{ width: 24, height: 24, borderRadius: '50%', flexShrink: 0, overflow: 'hidden', background: 'var(--t-coral)', display: 'inline-flex' }}><window.PImg src={window.teacherPhoto(t)} /></span>
            <span style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--p-mute)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>Ведёт <span style={{ color: 'var(--p-soft)' }}>{t.spiritual}</span></span>
          </span>}
        <span style={{ marginLeft: 'auto', display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 11.5, fontWeight: 800, color: 'var(--c-leaf)', whiteSpace: 'nowrap' }}>✓ Участвую</span>
      </div>
    </div>);
}

/* ── контекстное меню «моего» занятия (нижняя шторка) ──────────── */
function ClassMenuSheet({ host, p, onClose, onEdit, onDelete, onShare }) {
  if (!host || !p) return null;
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 6px' };
  const row = (e, label, danger) => (
    <button className="tap" onClick={(ev) => { ev.stopPropagation(); }} style={{
      display: 'flex', alignItems: 'center', gap: 13, width: '100%', border: 'none', background: 'none', cursor: 'pointer',
      padding: '15px 18px', fontFamily: "'Nunito Sans', sans-serif", fontWeight: 700, fontSize: 15.5,
      color: danger ? 'var(--c-coral)' : 'var(--p-ink)', textAlign: 'left',
    }}><span style={{ fontSize: 19, width: 24, textAlign: 'center' }}>{e}</span>{label}</button>);
  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 24, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={grabber} />
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '6px 18px 12px' }}>
            <ClassCover image={p.image} size={42} radius={12} />
            <div style={{ minWidth: 0 }}>
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.title}</div>
              <div style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--p-faint)' }}>{p.when}</div>
            </div>
          </div>
          <div style={{ borderTop: '1px solid var(--p-border)' }} onClick={onClose}>
            <div onClick={() => { onClose(); onEdit(p); }}>{row('✎', 'Изменить занятие')}</div>
            <div style={{ borderTop: '1px solid var(--p-border)' }} onClick={() => { onClose(); onShare(p); }}>{row('🔗', 'Скопировать ссылку')}</div>
            <div style={{ borderTop: '1px solid var(--p-border)' }} onClick={() => { onClose(); onDelete(p); }}>{row('🗑', 'Удалить занятие', true)}</div>
          </div>
          <div style={{ padding: '8px' }}>
            <button className="pbtn" onClick={onClose} style={{ width: '100%', height: 50, borderRadius: 16, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-ink)', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15 }}>Отмена</button>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

/* ── нижняя навигация (единая для всех экранов; активны «Занятия») ─ */
const CLS_NAV = [
  { key: 'practice', label: 'Моя практика', emoji: '🌀', pop: '#E8615A', soft: 'rgba(232,97,90,0.16)' },
  { key: 'classes', label: 'Занятия', emoji: '🧘', pop: '#E8902F', soft: 'rgba(232,144,47,0.16)' },
  { key: 'calendar', label: 'Календарь', emoji: '📅', pop: '#4FA85B', soft: 'rgba(79,168,91,0.16)' },
  { key: 'sangat', label: 'Сангат', emoji: '❤️', pop: '#3E92D8', soft: 'rgba(62,146,216,0.16)' },
  { key: 'ahamkara', label: 'Ахамкара', emoji: '🪬', pop: '#8E55D8', soft: 'rgba(142,85,216,0.16)' },
];
function ClassesBottomNav({ active: activeProp, onNav } = {}) {
  const [activeState, setActiveState] = uC('classes');
  const active = activeProp != null ? activeProp : activeState;
  const setActive = onNav || setActiveState;
  return (
    <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderTop: '1px solid var(--p-border)', display: 'flex', alignItems: 'stretch', justifyContent: 'space-around', padding: '8px 4px 24px' }}>
      {CLS_NAV.map(({ key, label, emoji, pop, soft }) => {
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

/* ── главный экран ─────────────────────────────────────────────── */
function ClassesScreen({ dark = false, nav }) {
  const [tab, setTab] = uC('mine');
  const [sub, setSub] = uC('up');           // предстоящие | записи
  const [mine, setMine] = uC(() => JSON.parse(JSON.stringify(window.MY_CLASSES)));
  const [joined, setJoined] = uC(() => JSON.parse(JSON.stringify(window.JOINED_CLASSES)));

  const [menuId, setMenuId] = uC(null);     // ⋯ меню моего занятия
  const [detailMine, setDetailMine] = uC(null);
  const [detailJoined, setDetailJoined] = uC(null);
  const [editor, setEditor] = uC(null);     // { mode:'create'|'edit', draft }
  const [confirmDel, setConfirmDel] = uC(null);
  const [toast, setToast] = uC(null);

  const rootRef = uCr(null);
  const tref = uCr(0);
  const [, forceMount] = uC(0);
  uCe(() => { forceMount(1); }, []);   // ref доступен после монтирования → шторки находят host
  const ping = (msg) => { setToast(msg); clearTimeout(tref.current); tref.current = setTimeout(() => setToast(null), 2000); };
  uCe(() => () => clearTimeout(tref.current), []);
  const host = rootRef.current && rootRef.current.closest('.ylp');

  const { ClassEditorSheet, ClassDetailsMine, ClassDetailsJoined, ConfirmSheet } = window;

  // ── действия ──
  const openCreate = () => setEditor({ mode: 'create', draft: blankClass() });
  const openEdit = (p) => setEditor({ mode: 'edit', draft: JSON.parse(JSON.stringify(p)) });
  const saveClass = (draft) => {
    setMine((xs) => {
      const i = xs.findIndex((x) => x.id === draft.id);
      if (i === -1) return [{ ...draft }, ...xs];
      const next = xs.slice(); next[i] = { ...draft }; return next;
    });
    setEditor(null);
    setDetailMine(null);
    ping(mineHas(draft.id) ? '✓ Изменения сохранены' : '✓ Занятие создано');
  };
  const mineHas = (id) => mine.some((x) => x.id === id);
  const doDelete = (p) => { setMine((xs) => xs.filter((x) => x.id !== p.id)); setConfirmDel(null); setDetailMine(null); ping('Занятие удалено'); };
  const leaveClass = (p) => { setJoined((xs) => xs.filter((x) => x.id !== p.id)); setDetailJoined(null); ping('Вы больше не участвуете'); };

  const counts = { mine: mine.length, joined: joined.length };
  const TABS = [['mine', 'Мои занятия', '🧘'], ['joined', 'Я участвую', '✋']];
  const SUBS = [['up', 'Предстоящие', '📅'], ['rec', 'Записи', '🎞']];

  const activeList = tab === 'mine' ? mine : joined;
  const grouped = splitWhen(activeList);
  const subCounts = { up: grouped.up.length, rec: grouped.rec.length };
  const shown = sub === 'rec' ? grouped.rec : grouped.up;

  return (
    <div ref={rootRef} className={'ylp' + (dark ? ' dark' : '')} style={{ height: '100%' }}>
      {/* top bar */}
      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)', padding: '48px 14px 11px', display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 7, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 20, color: 'var(--p-ink)' }}>🧘 Занятия</span>
        <button className="picon pbtn" onClick={openCreate} style={{ backgroundColor: 'var(--t-coral)' }}>
          <IconPlus size={20} style={{ color: 'var(--c-coral)' }} />
        </button>
      </div>

      <div className="pscroll" style={{ paddingBottom: 96 }}>
        {/* распорка под фиксированную шапку */}
        <div style={{ height: 96 }} />

        {/* сегмент-таб */}
        <div style={{ padding: '8px 14px 6px', position: 'sticky', top: 96, zIndex: 5, background: 'var(--p-bg)' }}>
          <div style={{ display: 'flex', gap: 6, backgroundColor: 'var(--p-ctrl)', borderRadius: 16, padding: 4 }}>
            {TABS.map(([id, label, emo]) => {
              const on = tab === id;
              return (
                <button key={id} onClick={() => setTab(id)} style={{
                  flex: 1, height: 42, border: 'none', cursor: 'pointer', borderRadius: 12,
                  fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13.5,
                  backgroundColor: on ? 'var(--p-elev)' : 'transparent',
                  color: on ? 'var(--p-ink)' : 'var(--p-mute)',
                  boxShadow: on ? '0 5px 14px -7px rgba(40,20,60,0.4)' : 'none',
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6, transition: 'all .15s ease',
                }}>
                  <span style={{ fontSize: 15, filter: on ? 'none' : 'saturate(0.7) opacity(0.6)' }}>{emo}</span>{label}
                  <span style={{ fontSize: 11, fontWeight: 800, minWidth: 18, padding: '1px 6px', borderRadius: 999, backgroundColor: on ? 'var(--ctrl-accent-soft)' : 'var(--p-bg)', color: on ? 'var(--ctrl-accent)' : 'var(--p-faint)' }}>{counts[id]}</span>
                </button>);
            })}
          </div>
        </div>

        {/* под-таб: Предстоящие / Записи */}
        <div style={{ padding: '10px 14px 2px' }}>
          <div style={{ display: 'flex', gap: 4, backgroundColor: 'var(--p-ctrl)', borderRadius: 12, padding: 3 }}>
            {SUBS.map(([id, label, emo]) => {
              const on = sub === id;
              const pop = id === 'rec' ? 'var(--c-grape)' : 'var(--c-coral)';
              return (
                <button key={id} onClick={() => setSub(id)} style={{
                  flex: 1, border: 'none', cursor: 'pointer', borderRadius: 9, padding: '8px 13px',
                  fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12.5,
                  backgroundColor: on ? 'var(--p-elev)' : 'transparent',
                  color: on ? 'var(--p-ink)' : 'var(--p-mute)',
                  boxShadow: on ? '0 4px 12px -7px rgba(40,20,60,0.45)' : 'none',
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6, transition: 'all .15s ease',
                }}>
                  {label}
                  <span style={{ fontSize: 10.5, fontWeight: 800, minWidth: 16, textAlign: 'center', padding: '1px 5px', borderRadius: 999, backgroundColor: on ? (id === 'rec' ? 'var(--t-grape)' : 'var(--t-coral)') : 'var(--p-bg)', color: on ? pop : 'var(--p-faint)' }}>{subCounts[id]}</span>
                </button>);
            })}
          </div>
        </div>

        {/* список */}
        <div style={{ padding: '10px 14px 0', display: 'flex', flexDirection: 'column', gap: 10 }}>
          {tab === 'mine' && <>
            {shown.map((p) => <MyClassCard key={p.id} p={p} onOpen={(x) => setDetailMine(x.id)} onMenu={(x) => setMenuId(x.id)} />)}
            {shown.length === 0 &&
              <div style={{ textAlign: 'center', padding: '40px 24px', color: 'var(--p-faint)' }}>
                <div style={{ fontSize: 32 }}>{sub === 'rec' ? '🎞' : '📅'}</div>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-mute)', marginTop: 8 }}>{sub === 'rec' ? 'Пока нет записей' : 'Пока нет предстоящих'}</div>
                <div style={{ fontSize: 12.5, fontWeight: 600, marginTop: 4 }}>{sub === 'rec' ? 'Сохраните занятие как запись — оно появится здесь.' : 'Создайте занятие с расписанием или эфиром.'}</div>
              </div>}
            {/* плитка создания */}
            <button className="tap pbtn" onClick={openCreate} style={{
              marginTop: 4,
              border: '2px dashed var(--p-border)', background: 'transparent', cursor: 'pointer', borderRadius: 20,
              padding: '18px 14px', display: 'flex', alignItems: 'center', gap: 12, color: 'var(--p-mute)',
            }}>
              <span style={{ width: 44, height: 44, borderRadius: 14, background: 'var(--t-coral)', color: 'var(--c-coral)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 24, flexShrink: 0 }}>＋</span>
              <span style={{ textAlign: 'left' }}>
                <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)' }}>Создать занятие</span>
                <span style={{ display: 'block', fontSize: 12, fontWeight: 600, color: 'var(--p-faint)', marginTop: 1 }}>Видео, аудио или живая встреча</span>
              </span>
            </button>
          </>}

          {tab === 'joined' && <>
            {joined.length === 0 &&
              <div style={{ textAlign: 'center', padding: '46px 24px', color: 'var(--p-faint)' }}>
                <div style={{ fontSize: 34 }}>🌿</div>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15, color: 'var(--p-mute)', marginTop: 8 }}>Пока никуда не записаны</div>
                <div style={{ fontSize: 12.5, fontWeight: 600, marginTop: 4 }}>Занятия, куда вы присоединитесь, появятся здесь.</div>
              </div>}
            {joined.length > 0 && shown.map((p) => <JoinedClassCard key={p.id} p={p} onOpen={(x) => setDetailJoined(x.id)} onLeave={leaveClass} />)}
            {joined.length > 0 && shown.length === 0 &&
              <div style={{ textAlign: 'center', padding: '40px 24px', color: 'var(--p-faint)' }}>
                <div style={{ fontSize: 32 }}>{sub === 'rec' ? '🎞' : '📅'}</div>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-mute)', marginTop: 8 }}>{sub === 'rec' ? 'Нет записей' : 'Нет предстоящих'}</div>
                <div style={{ fontSize: 12.5, fontWeight: 600, marginTop: 4 }}>{sub === 'rec' ? 'Записи преподавателей появятся здесь.' : 'Предстоящие занятия появятся здесь.'}</div>
              </div>}
          </>}
        </div>
      </div>

      <ClassesBottomNav {...nav} />

      {/* ── шторки ── */}
      {host && menuId && (() => { const p = mine.find((x) => x.id === menuId); return p ?
        <ClassMenuSheet host={host} p={p} onClose={() => setMenuId(null)} onEdit={openEdit} onDelete={(x) => setConfirmDel(x.id)} onShare={() => ping('🔗 Ссылка скопирована')} /> : null; })()}

      {host && detailMine && ClassDetailsMine && (() => { const p = mine.find((x) => x.id === detailMine); return p ?
        <ClassDetailsMine host={host} p={p} onClose={() => setDetailMine(null)} onEdit={openEdit} onDelete={(x) => setConfirmDel(x.id)} onShare={() => ping('🔗 Ссылка скопирована')} /> : null; })()}

      {host && detailJoined && ClassDetailsJoined && (() => { const p = joined.find((x) => x.id === detailJoined); return p ?
        <ClassDetailsJoined host={host} p={p} onClose={() => setDetailJoined(null)} onLeave={leaveClass} /> : null; })()}

      {host && editor && ClassEditorSheet &&
        <ClassEditorSheet host={host} mode={editor.mode} draft={editor.draft} onClose={() => setEditor(null)} onSave={saveClass} />}

      {host && confirmDel && ConfirmSheet && (() => { const p = mine.find((x) => x.id === confirmDel); return p ?
        <ConfirmSheet host={host} title="Удалить занятие?" body={`«${p.title}» исчезнет у всех участников. Это действие нельзя отменить.`} confirmLabel="Удалить" danger onCancel={() => setConfirmDel(null)} onConfirm={() => doDelete(p)} /> : null; })()}

      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 40, zIndex: 60, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)',
        }}>{toast}</div>, host)}
    </div>);
}

/* пустой черновик нового занятия */
let _newId = 1;
function blankClass() {
  return {
    id: 'new' + (_newId++) + '_' + Date.now(),
    title: '', kind: 'video', dur: '30 мин', level: 'Любой', tone: 'a',
    when: '', live: false, free: true, price: '', place: 'online', image: 'mat',
    participants: 0, desc: '',
    visibility: { mode: 'all', accounts: [] },
  };
}

Object.assign(window, {
  ClassesScreen, ClassesBottomNav, ClassCover, AudAva, AudStack, KindChips, visSummary, myLeads, TeacherStack,
  C_TONE, C_KIND, C_TEACHERS, C_AUD,
});
