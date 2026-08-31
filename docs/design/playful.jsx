// playful.jsx — «Цветной» вариант: эмодзи-подход, поп-цвета, светлая/тёмная тема
// Экспорт в window: PlayfulProfile (проп dark переключает тему через класс .ylp.dark)

const { useState: uSp } = React;

/* ── палитра через CSS-переменные (значения в .ylp / .ylp.dark) ──── */
const POP = {
  coral: 'var(--c-coral)', sun: 'var(--c-sun)', mint: 'var(--c-mint)',
  sky: 'var(--c-sky)', grape: 'var(--c-grape)', bubble: 'var(--c-bubble)', leaf: 'var(--c-leaf)'
};
const TINT = {
  coral: 'var(--t-coral)', sun: 'var(--t-sun)', mint: 'var(--t-mint)',
  sky: 'var(--t-sky)', grape: 'var(--t-grape)', bubble: 'var(--t-bubble)', leaf: 'var(--t-leaf)'
};

/* ── система кнопок и контролов (держимся этих цветов) ──────────── */
//   Кнопки: коралл = главное действие, виноград = второстепенное
//   Контролы: нейтральная поверхность + один акцент (виноград)
const BTN = { primary: 'var(--c-coral)', secondary: 'var(--c-grape)' };
const CTRL = { surface: 'var(--p-ctrl)', accent: 'var(--ctrl-accent)', accentSoft: 'var(--ctrl-accent-soft)', text: 'var(--p-mute)' };
const GOLD_INK = 'var(--c-goldink)';

/* реальные фото-обложки (Unsplash); градиент остаётся подложкой-фолбэком */
const U = (id, w = 800, h) => `https://images.unsplash.com/photo-${id}?q=80&auto=format&fit=crop&w=${w}${h ? `&h=${h}` : ''}`;
const UA = (id) => `https://images.unsplash.com/photo-${id}?q=80&auto=format&fit=crop&crop=faces&w=240&h=240`;

const PHOTO_MAP = {
  morning: ['#FFC371', '#FF5F6D'],
  studio: ['#43C6AC', '#4D9DE0'],
  asana: ['#2EC4B6', '#4D9DE0'],
  breath: ['#A18CD1', '#FBC2EB'],
  meadow: ['#9BE15D', '#00C3A5'],
  candle: ['#F6C453', '#FF7E5F'],
  mat: ['#4D9DE0', '#22C2B0'],
  temple: ['#9B5DE5', '#4D9DE0'],
  forest: ['#5DBB63', '#22C2B0']
};
const REAL_IMG = {
  morning: U('1506126613408-eca07ce68773'),
  studio: U('1599901860904-17e6ed7083a0'),
  asana: U('1544367567-0f2fcb009e0b'),
  breath: U('1552196563-55cd4e45efb3'),
  meadow: U('1602192509154-0b900ee1f851'),
  candle: U('1593811167562-9cef47bfc4d7'),
  mat: U('1588286840104-8957b019727f'),
  temple: U('1545389336-cf090694435e'),
  forest: U('1441974231531-c6227db76b6e')
};

/* портреты для аватаров (кадрируем по лицам) */
const AVA_SELF = UA('1494790108377-be9c29b29330');
const AVA_POOL = [
  '1438761681033-6461ffad8d80', '1500648767791-00dcc994a43e', '1534528741775-53994a69daeb',
  '1507003211169-0a1dd7228f2d', '1544005313-94ddf0286df2', '1502685104226-ee32379fefbe',
  '1517841905240-472988babdf9', '1463453091185-61582044d556', '1488426862026-3ee34a7d66df'
].map(UA);
const avaFor = (h = '') => {
  if (h === 'you' || h === 'self') return AVA_SELF;
  let s = 0; for (const ch of h) s += ch.charCodeAt(0);
  return AVA_POOL[s % AVA_POOL.length];
};
/* обложка занятия: берём p.image, иначе подбираем фото по тону (a/b/c) детерминированно */
const PR_COVER = {
  a: ['asana', 'morning', 'mat'],
  b: ['forest', 'meadow', 'breath'],
  c: ['candle', 'temple', 'studio']
};
const practiceCover = (p) => {
  if (p.image) return p.image;
  let s = 0; for (const ch of (p.id || '')) s += ch.charCodeAt(0);
  const list = PR_COVER[p.tone] || PR_COVER.a;
  return list[s % list.length];
};

const TEACHER_PHOTO = {
  self: AVA_SELF,
  surya: UA('1500648767791-00dcc994a43e'),
  mira: UA('1534528741775-53994a69daeb'),
  kira: UA('1517841905240-472988babdf9')
};
const teacherPhoto = (t) => TEACHER_PHOTO[t.id] || avaFor(t.id);

/* ── чей это профиль: по умолчанию — свой (PROFILE), на чужом профиле
   провайдер подменяет автора записей и подпись ведущего «вы» ── */
const PAuthorCtx = React.createContext(null);
const SELF_AUTHOR = { name: PROFILE.name, spiritual: PROFILE.spiritual, avatar: AVA_SELF, verified: true, selfLabel: 'вы', selfLabelCap: 'Вы' };
const usePAuthor = () => React.useContext(PAuthorCtx) || SELF_AUTHOR;
const leadPhoto = (t, a) => t.id === 'self' ? a.avatar : teacherPhoto(t);
const leadName = (t, a) => t.id === 'self' ? a.selfLabel : t.spiritual;

/* <img> с мягким фолбэком: если фото не загрузилось (офлайн) — остаётся градиент-подложка */
function PImg({ src, style }) {
  const [err, setErr] = uSp(false);
  if (!src || err) return null;
  return <img src={src} alt="" loading="lazy" onError={() => setErr(true)}
    style={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block', ...style }} />;
}

function PPhoto({ image, height = 300, radius = 0 }) {
  const [a, b] = PHOTO_MAP[image] || ['#FFB020', '#FF6F61'];
  return (
    <div style={{
      position: 'relative', height, width: '100%', borderRadius: radius, overflow: 'hidden',
      background: `linear-gradient(135deg, ${a}, ${b})`
    }}>
      <PImg src={REAL_IMG[image]} />
    </div>);

}

/* ── playful avatar — радужное кольцо + эмодзи ──────────────────── */
/* ── audio-плеер в записи ── */
const WAVE = [9, 16, 22, 13, 26, 18, 30, 20, 12, 24, 17, 28, 14, 21, 9, 19, 27, 15, 23, 11, 18, 25, 13, 20, 16];
function PAudio({ audio }) {
  const [playing, setPlaying] = uSp(false);
  const [pos, setPos] = uSp(0.32);
  return (
    <div style={{
      marginTop: 12, display: 'flex', alignItems: 'center', gap: 12, padding: 12,
      borderRadius: 16, background: 'linear-gradient(135deg, var(--t-grape), var(--t-sky))'
    }}>
      <button className="pbtn" onClick={() => setPlaying((v) => !v)} style={{
        width: 46, height: 46, borderRadius: '50%', flexShrink: 0, border: 'none', cursor: 'pointer',
        backgroundColor: POP.grape, color: '#fff', fontSize: 15,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        boxShadow: '0 6px 16px -7px rgba(155,93,229,0.9)'
      }}>{playing ? '❚❚' : '▶'}</button>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13, color: 'var(--p-ink)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>🎧 {audio.title}</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 1.5, height: 26, marginTop: 5 }}>
          {WAVE.map((h, i) => {
            const active = i / WAVE.length <= pos;
            return <div key={i} className="tap" onClick={() => setPos((i + 0.5) / WAVE.length)} style={{
              width: 3, height: h, borderRadius: 2, flexShrink: 0,
              backgroundColor: active ? POP.grape : 'var(--p-border)'
            }} />;
          })}
          <span style={{ marginLeft: 'auto', paddingLeft: 8, fontSize: 11.5, fontWeight: 700, color: 'var(--p-mute)' }}>{audio.dur}</span>
        </div>
      </div>
    </div>);

}

/* ── video-блок в записи ── */
function PVideo({ image, dur }) {
  return (
    <div style={{ marginTop: 12, position: 'relative', borderRadius: 16, overflow: 'hidden' }}>
      <PPhoto image={image} height={260} radius={0} />
      <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(20,14,30,0.10)' }}>
        <div className="pbtn tap" style={{
          width: 62, height: 62, borderRadius: '50%', backgroundColor: 'rgba(255,255,255,0.92)',
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 8px 22px -8px rgba(0,0,0,0.45)'
        }}>
          <span style={{ marginLeft: 4, width: 0, height: 0, borderTop: '11px solid transparent', borderBottom: '11px solid transparent', borderLeft: '18px solid #241b30' }} />
        </div>
      </div>
      <span style={{ position: 'absolute', right: 10, bottom: 10, padding: '3px 9px', borderRadius: 999, backgroundColor: 'rgba(20,14,30,0.62)', color: '#fff', fontSize: 11.5, fontWeight: 700 }}>🎬 {dur}</span>
    </div>);

}

/* ── иконка фильтра (ползунки) ── */
const IconFilter = ({ size = 20, style }) =>
<svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" style={style}>
    <path d="M4 7h9M19 7h1M4 17h1M11 17h9" />
    <circle cx="16" cy="7" r="2.4" /><circle cx="8" cy="17" r="2.4" />
  </svg>;

const CONTENT_TYPES = [['text', '📝', 'Текст'], ['photo', '📷', 'Фото'], ['audio', '🎧', 'Аудио'], ['video', '🎬', 'Видео']];

function PAvatar({ size = 80, ring = true, dot = false, src = AVA_SELF }) {
  const inner = size - (ring ? 8 : 0);
  return (
    <div style={{ position: 'relative', width: size, height: size, flexShrink: 0 }}>
      <div style={{
        width: size, height: size, borderRadius: '50%',
        background: ring ?
        'conic-gradient(from 210deg, #FF6F61, #FFB020, #5DBB63, #22C2B0, #4D9DE0, #9B5DE5, #F15BB5, #FF6F61)' :
        'transparent',
        padding: ring ? 4 : 0, boxSizing: 'border-box'
      }}>
        <div style={{
          width: inner, height: inner, borderRadius: '50%', overflow: 'hidden',
          background: 'var(--t-coral)',
          border: ring ? '2.5px solid var(--p-elev)' : 'none'
        }}><PImg src={src} /></div>
      </div>
      {dot && <span style={{
        position: 'absolute', right: 2, bottom: 2, width: 16, height: 16, borderRadius: '50%',
        backgroundColor: POP.leaf, border: '3px solid var(--p-bg)'
      }} />}
    </div>);

}

/* ── top bar ───────────────────────────────────────────────────── */
function PTopBar({ onMenu }) {
  return (
    <div style={{
      position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6,
      backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)',
      borderBottom: '1px solid var(--p-border)', padding: '48px 14px 11px',
      display: 'flex', alignItems: 'center', gap: 8
    }}>
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 6, minWidth: 0 }}>
        <span style={{ fontWeight: 700, fontSize: 16, color: 'var(--p-ink)' }}>{PROFILE.username}</span>
        <span style={{ fontSize: 14 }}>✨</span>
      </div>
      <button className="picon tap" onClick={onMenu} aria-label="Меню" style={{ backgroundColor: CTRL.surface }}>
        <IconMenu size={20} style={{ color: CTRL.accent }} />
      </button>
    </div>);

}

/* ── боковое меню (выезжает справа) ─────────────────────────────── */
function PSideMenu({ host, onClose, nav }) {
  const [toast, setToast] = uSp(null);
  const tref = React.useRef(0);
  const ping = (msg) => { setToast(msg); clearTimeout(tref.current); tref.current = setTimeout(() => setToast(null), 1900); };
  React.useEffect(() => () => clearTimeout(tref.current), []);
  if (!host) return null;
  const go = (key) => { onClose(); if (nav && nav.onNav) nav.onNav(key); };
  const items = [
    { id: 'sessions',label: 'Мои сессии',                  Icon: IconClock,    pop: POP.sky,   tint: TINT.sky,   onClick: () => ping('Раздел «Мои сессии» — скоро') },
    { id: 'privacy', label: 'Политика конфиденциальности', Icon: IconLock,     pop: POP.leaf,  tint: TINT.leaf,  onClick: () => ping('Открываем политику…') },
    { id: 'offer',   label: 'Оферта',                      Icon: IconList,     pop: POP.sun,   tint: TINT.sun,   onClick: () => ping('Открываем оферту…') }];

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="pdrawer" style={{ width: '82%', maxWidth: 330, background: 'var(--p-bg)', display: 'flex', flexDirection: 'column', boxShadow: '-18px 0 55px -22px rgba(20,14,30,0.55)' }}>
        <div style={{ padding: '48px 14px 14px', display: 'flex', alignItems: 'center', gap: 11, borderBottom: '1px solid var(--p-border)' }}>
          <PAvatar size={44} ring />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, color: 'var(--p-ink)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{PROFILE.name}</div>
            <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--p-mute)' }}>{PROFILE.username}</div>
          </div>
          <button className="picon tap" onClick={onClose} aria-label="Закрыть" style={{ color: 'var(--p-soft)' }}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
          </button>
        </div>
        <div style={{ padding: '12px 10px', display: 'flex', flexDirection: 'column', gap: 2 }}>
          {items.map((it) => (
            <button key={it.id} className="tap pbtn" onClick={it.onClick} style={{
              display: 'flex', alignItems: 'center', gap: 13, width: '100%', textAlign: 'left',
              border: 'none', cursor: 'pointer', background: 'transparent', padding: '10px 6px', borderRadius: 14
            }}>
              <span style={{ flexShrink: 0, width: 40, height: 40, borderRadius: 13, background: it.tint, color: it.pop, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
                <it.Icon size={21} />
              </span>
              <span style={{ flex: 1, minWidth: 0, fontFamily: "'Nunito Sans', sans-serif", fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)' }}>{it.label}</span>
              <IconChevron size={17} style={{ color: 'var(--p-faint)', flexShrink: 0 }} />
            </button>))}
        </div>
      </div>
      {toast &&
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontWeight: 700, fontSize: 13.5, whiteSpace: 'nowrap',
          boxShadow: '0 12px 30px -10px rgba(20,14,30,0.5)'
        }}>{toast}</div>}
    </React.Fragment>, host);
}

/* ── stat card — цветная цифра без подложки, равные колонки ─────── */
const PStat = ({ n, l, hue }) =>
<div style={{ flex: 1, minWidth: 0, textAlign: 'center', padding: '2px 4px' }}>
    <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 20, color: hue, lineHeight: 1 }}>{n}</div>
    <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-mute)', marginTop: 5 }}>{l}</div>
  </div>;


/* ── colourful chip ────────────────────────────────────────────── */
const PChip = ({ emoji, label, tint, pop }) =>
<span style={{
  display: 'inline-flex', alignItems: 'center', gap: 6, whiteSpace: 'nowrap',
  padding: '7px 13px', borderRadius: 999, fontSize: 12.5, fontWeight: 700,
  backgroundColor: tint, color: pop
}}>
    <span style={{ fontSize: 13 }}>{emoji}</span>{label}
  </span>;


/* ── story highlight ───────────────────────────────────────────── */
const HILITES = [
['Асаны', 'asana', POP.coral, TINT.coral],
['Дыхание', 'breath', POP.sky, TINT.sky],
['Ретриты', 'temple', POP.mint, TINT.mint],
['Цитаты', 'candle', POP.grape, TINT.grape],
['Студия', 'studio', POP.bubble, TINT.bubble],
['Поток', 'meadow', POP.leaf, TINT.leaf]];

function PHilite({ label, emoji, pop, tint }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 7, width: 62, flexShrink: 0 }}>
      <div style={{
        width: 60, height: 60, borderRadius: '50%', backgroundColor: tint, overflow: 'hidden',
        border: `2.5px solid ${pop}`
      }}><PImg src={REAL_IMG[emoji]} /></div>
      <span style={{ fontSize: 11, fontWeight: 600, color: 'var(--p-mute)', maxWidth: 62, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{label}</span>
    </div>);

}

/* ── follow / message buttons ──────────────────────────────────── */
function PActions({ nav }) {
  const [following, setFollowing] = uSp(false);
  const [notify, setNotify] = uSp(false);
  const [overlay, setOverlay] = uSp(null); // 'menu' | 'message' | null
  const [draft, setDraft] = uSp('');
  const [reportText, setReportText] = uSp('');
  const [toast, setToast] = uSp(null);
  const rootRef = React.useRef(null);
  const tref = React.useRef(0);

  const ping = (msg) => {
    setToast(msg);
    clearTimeout(tref.current);
    tref.current = setTimeout(() => setToast(null), 1900);
  };
  React.useEffect(() => () => clearTimeout(tref.current), []);

  const toggleFollow = () =>
    setFollowing((v) => { ping(v ? 'Вы отписались 👋' : '✓ Теперь вы вместе'); return !v; });

  const send = () => { setOverlay(null); setDraft(''); ping('Сообщение отправлено ✓'); };
  const sendReport = () => { setOverlay(null); setReportText(''); ping('Жалоба отправлена · спасибо'); };

  const host = rootRef.current && rootRef.current.closest('.ylp');

  const base = {
    flex: 1, height: 40, borderRadius: 13, cursor: 'pointer',
    fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14,
    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6
  };

  /* overlay style atoms */
  const sheetCard = { background: 'var(--p-elev)', borderRadius: 22, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' };
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 4px' };
  const sheetHead = { fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12, color: 'var(--p-faint)', textAlign: 'center', padding: '2px 0 10px', textTransform: 'uppercase', letterSpacing: '0.07em' };
  const cancelBtn = { width: '100%', marginTop: 8, height: 50, borderRadius: 18, border: 'none', cursor: 'pointer', background: 'var(--p-elev)', color: 'var(--p-ink)', fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 15, boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.32)' };

  const quick = ['Намасте 🙏', 'Когда ближайший класс?', 'Спасибо за поток ✨'];

  const portal = (children) =>
    ReactDOM.createPortal(
      <React.Fragment>
        <div className="pscrim" onClick={() => setOverlay(null)} />
        {children}
      </React.Fragment>, host);

  return (
    <div ref={rootRef} style={{ display: 'flex', gap: 9 }}>
      <button className="pbtn" onClick={toggleFollow} style={following ? {
        ...base, border: `2px solid ${BTN.primary}`, backgroundColor: 'var(--p-elev)', color: BTN.primary
      } : {
        ...base, border: 'none', color: '#fff', backgroundColor: BTN.primary,
        boxShadow: '0 6px 16px -7px rgba(255,111,97,0.9)'
      }}>{following ? 'Отписаться' : 'Подписаться'}</button>

      <button className="pbtn" onClick={() => { if (nav && nav.onNav) { window.__ylChatIntent = 'alina'; nav.onNav('sangat'); } else { setOverlay('message'); } }} style={{
        ...base, border: 'none', color: '#fff', backgroundColor: 'var(--msg-accent, var(--ctrl-accent))',
        boxShadow: '0 6px 16px -8px rgba(40,20,60,0.55)'
      }}>Сообщение</button>

      <button className="picon" onClick={() => ping('Ссылка скопирована 🔗')} aria-label="Поделиться" style={{ width: 40, height: 40, borderRadius: 13, backgroundColor: CTRL.surface, flexShrink: 0 }}>
        <IconShare size={18} style={{ color: CTRL.accent }} />
      </button>

      <button className="picon" onClick={() => setOverlay('report')} aria-label="Пожаловаться" style={{ width: 40, height: 40, borderRadius: 13, backgroundColor: CTRL.surface, flexShrink: 0 }}>
        <IconFlag size={18} style={{ color: POP.coral }} />
      </button>

      {host && overlay === 'report' &&
      portal(
          <div className="psheet" style={{ padding: '0 8px 12px' }}>
            <div style={{ ...sheetCard, padding: 16 }}>
              <div style={grabber} />
              <div style={sheetHead}>Пожаловаться</div>
              <textarea autoFocus value={reportText} onChange={(e) => setReportText(e.target.value)}
                placeholder="Опишите, что не так…" rows={4} style={{
                  width: '100%', boxSizing: 'border-box', borderRadius: 14, border: '1.5px solid var(--p-border)',
                  background: 'var(--p-ctrl)', padding: '12px 14px', fontFamily: "'Nunito Sans', sans-serif",
                  fontSize: 14, lineHeight: 1.5, color: 'var(--p-ink)', outline: 'none', resize: 'none'
                }} />
              <button onClick={sendReport} disabled={!reportText.trim()} style={{
                width: '100%', marginTop: 12, height: 46, borderRadius: 14, border: 'none',
                cursor: reportText.trim() ? 'pointer' : 'default', opacity: reportText.trim() ? 1 : 0.5,
                backgroundColor: POP.coral, color: '#fff', fontFamily: "'Quicksand', sans-serif",
                fontWeight: 700, fontSize: 15, boxShadow: '0 6px 16px -7px rgba(255,111,97,0.9)'
              }}>Отправить жалобу</button>
            </div>
            <button onClick={() => setOverlay(null)} style={cancelBtn}>Отмена</button>
          </div>
        )}

      {host && overlay === 'message' &&
      portal(
          <div className="psheet" style={{ padding: '0 8px 12px' }}>
            <div style={{ ...sheetCard, padding: 16 }}>
              <div style={grabber} />
              <div style={{ display: 'flex', alignItems: 'center', gap: 11, margin: '6px 0 14px' }}>
                <PAvatar size={42} ring dot />
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 15, color: 'var(--p-ink)' }}>{PROFILE.name}</div>
                  <div style={{ fontSize: 12, fontWeight: 700, color: POP.leaf }}>● онлайн</div>
                </div>
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7, marginBottom: 13 }}>
                {quick.map((q) =>
              <button key={q} className="tap" onClick={() => setDraft(q)} style={{
                border: 'none', cursor: 'pointer', padding: '8px 13px', borderRadius: 999,
                backgroundColor: TINT.grape, color: POP.grape, fontFamily: "'Nunito Sans', sans-serif", fontSize: 13, fontWeight: 700
              }}>{q}</button>
              )}
              </div>
              <div style={{ display: 'flex', gap: 9, alignItems: 'center' }}>
                <input autoFocus value={draft} onChange={(e) => setDraft(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') send(); }}
                placeholder="Напишите сообщение…" style={{
                  flex: 1, height: 44, borderRadius: 14, border: '1.5px solid var(--p-border)',
                  background: 'var(--p-ctrl)', padding: '0 15px', fontFamily: "'Nunito Sans', sans-serif",
                  fontSize: 14, color: 'var(--p-ink)', outline: 'none', minWidth: 0
                }} />
                <button onClick={send} style={{
                  width: 44, height: 44, borderRadius: 14, border: 'none', cursor: 'pointer',
                  backgroundColor: BTN.primary, color: '#fff', fontSize: 17, flexShrink: 0,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  boxShadow: '0 6px 16px -7px rgba(255,111,97,0.9)'
                }}>➤</button>
              </div>
            </div>
          </div>
        )}

      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)'
        }}>{toast}</div>, host)}
    </div>);

}

/* ── own-profile actions ───────────────────────────────────────── */
function PSelfActions() {
  const [toast, setToast] = uSp(null);
  const rootRef = React.useRef(null);
  const tref = React.useRef(0);
  const ping = (msg) => { setToast(msg); clearTimeout(tref.current); tref.current = setTimeout(() => setToast(null), 1900); };
  React.useEffect(() => () => clearTimeout(tref.current), []);
  const host = rootRef.current && rootRef.current.closest('.ylp');
  const base = {
    flex: 1, height: 40, borderRadius: 13, cursor: 'pointer',
    fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14,
    display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6
  };
  return (
    <div ref={rootRef} style={{ display: 'flex', gap: 9 }}>
      <button className="pbtn" onClick={() => ping('Редактирование профиля ✎')} style={{
        ...base, border: '2px solid var(--p-border)', backgroundColor: 'var(--p-elev)', color: 'var(--p-ink)'
      }}>Редактировать профиль</button>
      <button className="picon" onClick={() => ping('Ссылка скопирована 🔗')} aria-label="Поделиться профилем" style={{ width: 40, height: 40, borderRadius: 13, backgroundColor: CTRL.surface, flexShrink: 0 }}>
        <IconShare size={18} style={{ color: CTRL.accent }} />
      </button>
      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)'
        }}>{toast}</div>, host)}
    </div>);

}

/* ── header ────────────────────────────────────────────────────── */
function PHeader({ nav }) {
  const s = PROFILE.stats;
  return (
    <div style={{ padding: '106px 16px 16px', display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
        <PAvatar size={86} dot />
        <div style={{ flex: 1, display: 'flex', gap: 4 }}>
          <PStat n={s.posts} l="записей" hue={POP.coral} />
          <PStat n={s.practices} l="занятий" hue={POP.mint} />
          <PStat n={s.followers} l="подписчиков" hue={POP.grape} />
        </div>
      </div>

      {/* bio */}
      <div>
        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 18, color: 'var(--p-ink)', display: 'flex', alignItems: 'center', gap: 6, whiteSpace: 'nowrap' }}>
          {PROFILE.name}
          <span style={{ color: POP.sky, display: 'inline-flex' }}><IconVerified size={16} /></span>
          <span style={{ color: POP.bubble, fontSize: 15 }}>· {PROFILE.spiritual}</span>
        </div>
        <div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--p-mute)', marginTop: 4 }}>🌿 {PROFILE.role}</div>
        <p style={{ margin: '7px 0 0', fontSize: 14, lineHeight: 1.55, color: 'var(--p-soft)', textWrap: 'pretty' }}>{PROFILE.bio}</p>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 8, fontSize: 13, fontWeight: 700 }}>
          <span style={{ color: POP.sky }}>🔗 {PROFILE.link}</span>
          <span style={{ color: 'var(--p-faint)', fontWeight: 600 }}>📍 {PROFILE.location}</span>
        </div>
      </div>

      <PSelfActions />

      {/* highlights */}
      <div style={{ display: 'flex', gap: 14, overflowX: 'auto', scrollbarWidth: 'none', paddingTop: 2 }}>
        {HILITES.map(([l, e, p, t]) => <PHilite key={l} label={l} emoji={e} pop={p} tint={t} />)}
      </div>
    </div>);

}

/* ── tabs ──────────────────────────────────────────────────────── */
const PTABS = [['feed', 'Лента', IconList], ['grid', 'Сетка', IconGrid], ['practices', 'Занятия', IconFlame], ['saved', 'Практики', IconLotus]];
function PTabs({ tab, setTab, onCreate, createRef, createOpen }) {
  return (
    <div style={{ position: 'sticky', top: 94, zIndex: 5, backgroundColor: 'var(--p-bg)', padding: '6px 14px 10px' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 9 }}>
        <button ref={createRef} onClick={onCreate} aria-label="Создать" aria-expanded={!!createOpen} className="pbtn" style={{
          width: 46, height: 46, flexShrink: 0, borderRadius: 15, border: 'none', cursor: 'pointer',
          backgroundColor: BTN.primary, color: '#fff',
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 6px 16px -7px rgba(255,111,97,0.9)',
          transform: createOpen ? 'rotate(45deg)' : 'none',
          transition: 'transform .22s cubic-bezier(.2,.9,.25,1)'
        }}>
          <IconPlus size={24} />
        </button>
        <div style={{ flex: 1, display: 'flex', gap: 6, backgroundColor: CTRL.surface, borderRadius: 16, padding: 4 }}>
        {PTABS.map(([id, label, Ic]) => {
          const on = tab === id;
          return (
            <button key={id} onClick={() => setTab(id)} style={{
              flex: 1, height: 38, border: 'none', cursor: 'pointer', borderRadius: 13,
              fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13,
              backgroundColor: on ? 'var(--p-elev)' : 'transparent',
              color: on ? CTRL.accent : CTRL.text,
              boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none',
              display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
              transition: 'all .15s ease'
            }}>
              <Ic size={20} style={{ opacity: on ? 1 : 0.72, display: 'none' }} />{label}
            </button>);

        })}
        </div>
      </div>
    </div>);

}

/* ── аватар комментатора — эмодзи в кружке (как в профиле) ──────── */
const C_EMOJI = ['🌸', '🪷', '🍃', '🌿', '💫', '🦋', '🌙', '🕉️', '🧘', '🌻'];
const cEmoji = (h = '') => { let s = 0; for (const ch of h) s += ch.charCodeAt(0); return C_EMOJI[s % C_EMOJI.length]; };

function PCommentAvatar({ handle, src, size = 34 }) {
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%', flexShrink: 0, overflow: 'hidden',
      background: 'var(--t-coral)'
    }}><PImg src={src || avaFor(handle)} /></div>);

}

/* ── одна строка комментария (с лайком) ── */
function PCommentRow({ c }) {
  const [liked, setLiked] = uSp(false);
  const likes = (c.likes || 0) + (liked ? 1 : 0);
  return (
    <div style={{ display: 'flex', gap: 11, padding: '11px 0' }}>
      <PCommentAvatar handle={c.handle} />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13.5, lineHeight: 1.45, color: 'var(--p-soft)', textWrap: 'pretty' }}>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, color: 'var(--p-ink)' }}>{c.name}</span>
          {c.time && <span style={{ color: 'var(--p-faint)', fontWeight: 600 }}> · {c.time}</span>}
        </div>
        <div style={{ fontSize: 13.5, lineHeight: 1.45, color: 'var(--p-soft)', marginTop: 2, textWrap: 'pretty' }}>{c.text}</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginTop: 7 }}>
          {likes > 0 && <span style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--p-faint)', whiteSpace: 'nowrap' }}>{likes} нрав.</span>}
          <button className="tap" style={{ border: 'none', background: 'none', cursor: 'pointer', padding: 0, fontFamily: "'Quicksand', sans-serif", fontSize: 11.5, fontWeight: 700, color: 'var(--p-mute)' }}>Ответить</button>
        </div>
      </div>
      <button className="picon tap" onClick={() => setLiked((v) => !v)} style={{ width: 26, height: 26, alignSelf: 'flex-start', color: liked ? POP.coral : 'var(--p-faint)' }}>
        <IconHeart size={15} filled={liked} />
      </button>
    </div>);

}

/* ── шторка комментариев — как в Instagram ── */
function PCommentSheet({ host, comments, onAdd, onClose }) {
  const [draft, setDraft] = uSp('');
  if (!host) return null;
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 6px' };
  const send = () => { const t = draft.trim(); if (!t) return; onAdd(t); setDraft(''); };

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ height: '82%', display: 'flex', flexDirection: 'column', justifyContent: 'flex-end' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: '24px 24px 0 0', display: 'flex', flexDirection: 'column', height: '100%', overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={grabber} />
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, color: 'var(--p-ink)', textAlign: 'center', padding: '2px 0 12px', borderBottom: '1px solid var(--p-border)' }}>
            Комментарии{comments.length > 0 && <span style={{ color: 'var(--p-faint)' }}> · {comments.length}</span>}
          </div>

          <div style={{ flex: 1, overflowY: 'auto', padding: '2px 18px 8px', scrollbarWidth: 'none', WebkitOverflowScrolling: 'touch' }}>
            {comments.length === 0 ?
            <div style={{ textAlign: 'center', padding: '56px 24px' }}>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, color: 'var(--p-ink)' }}>Пока нет комментариев</div>
                <div style={{ fontSize: 13.5, color: 'var(--p-mute)', marginTop: 6, lineHeight: 1.5 }}>Будьте первым, кто оставит отклик.</div>
              </div> :
            comments.map((c) => <PCommentRow key={c.id} c={c} />)}
          </div>

          {/* поле ввода */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px 26px', borderTop: '1px solid var(--p-border)', background: 'var(--p-elev)' }}>
            <PCommentAvatar src={AVA_SELF} size={34} />
            <input autoFocus value={draft} onChange={(e) => setDraft(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') send(); }}
              placeholder="Добавьте комментарий…" style={{
                flex: 1, height: 42, borderRadius: 999, border: '1.5px solid var(--p-border)',
                background: 'var(--p-ctrl)', padding: '0 16px', fontFamily: "'Nunito Sans', sans-serif",
                fontSize: 14, color: 'var(--p-ink)', outline: 'none', minWidth: 0
              }} />
            <button className="pbtn" onClick={send} disabled={!draft.trim()} style={{
              width: 42, height: 42, borderRadius: '50%', border: 'none', flexShrink: 0,
              cursor: draft.trim() ? 'pointer' : 'default', opacity: draft.trim() ? 1 : 0.45,
              backgroundColor: BTN.primary, color: '#fff',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: '0 6px 16px -7px rgba(255,111,97,0.9)'
            }}><IconSend size={18} /></button>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

/* ── post card ─────────────────────────────────────────────────── */
function PPostCard({ post, onLike, onTag, activeTag }) {
  const author = usePAuthor();
  const [open, setOpen] = uSp(false);
  const [comments, setComments] = uSp(() => post.comments.map((c) => ({ ...c })));
  const [toast, setToast] = uSp(null);
  const rootRef = React.useRef(null);
  const tref = React.useRef(0);
  const host = rootRef.current && rootRef.current.closest('.ylp');

  const ping = (msg) => { setToast(msg); clearTimeout(tref.current); tref.current = setTimeout(() => setToast(null), 1900); };
  React.useEffect(() => () => clearTimeout(tref.current), []);

  const addComment = (text) =>
    setComments((cs) => [...cs, { id: 'cu' + Date.now(), name: 'Вы', handle: 'you', glyph: '🧘‍♀️', text, likes: 0, time: 'только что' }]);

  const pill = {
    display: 'inline-flex', alignItems: 'center', gap: 7, border: 'none', cursor: 'pointer',
    padding: '8px 14px', borderRadius: 999, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5,
    transition: 'all .15s ease'
  };

  return (
    <article ref={rootRef} style={{ padding: '4px 14px 14px' }}>
      <div style={{ backgroundColor: 'var(--p-elev)', borderRadius: 22, padding: 14, boxShadow: '0 8px 24px -16px rgba(40,20,60,0.4)', border: '1px solid var(--p-border)' }}>
        {/* header */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <PAvatar size={40} ring={false} src={author.avatar} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-ink)' }}>
              {author.name}{author.verified && <span style={{ color: POP.sky, display: 'inline-flex' }}><IconVerified size={13} /></span>}
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 4, fontSize: 11.5, color: 'var(--p-faint)', fontWeight: 600 }}>
              {post.pinned && <IconPin size={11} style={{ flexShrink: 0 }} />}
              <span>{author.spiritual} · {post.time}</span>
            </div>
          </div>
          <button className="picon" style={{ width: 30, height: 30, color: 'var(--p-faint)' }}><IconMenu size={18} /></button>
        </div>

        {/* media */}
        {post.media.includes('photo') &&
        <div style={{ marginTop: 12 }}><PPhoto image={post.image} height={260} radius={16} /></div>
        }
        {post.media.includes('video') &&
        <PVideo image={post.image} dur={post.video.dur} />
        }
        {post.media.includes('audio') &&
        <PAudio audio={post.audio} />
        }

        {/* text */}
        {post.text &&
        <p style={{ margin: `${post.media.length ? 11 : 9}px 0 0`, fontSize: 14, lineHeight: 1.5, color: 'var(--p-soft)', textWrap: 'pretty' }}>{post.text}</p>
        }

        {/* hashtags */}
        {post.tags && post.tags.length > 0 &&
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 10 }}>
            {post.tags.map((t) => {
            const on = activeTag === t;
            return (
              <button key={t} className="tap" onClick={() => onTag && onTag(t)} style={{
                border: 'none', cursor: 'pointer', padding: '4px 11px', borderRadius: 999,
                backgroundColor: on ? POP.sky : CTRL.surface, color: on ? '#fff' : POP.sky,
                fontFamily: "'Nunito Sans', sans-serif", fontSize: 12, fontWeight: 700,
                transition: 'all .15s ease'
              }}>#{t}</button>);

          })}
          </div>
        }

        {/* actions — лайк, комментарий и поделиться справа */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 12 }}>
          <button onClick={() => onLike(post.id)} style={{
            ...pill,
            backgroundColor: post.liked ? TINT.coral : CTRL.surface,
            color: post.liked ? POP.coral : CTRL.text
          }}>
            <IconHeart size={17} filled={post.liked} />{post.likes}
          </button>
          <button onClick={() => setOpen(true)} style={{ ...pill, backgroundColor: CTRL.surface, color: CTRL.text }}>
            <IconComment size={16} />{comments.length}
          </button>
          <div style={{ flex: 1 }} />
          <button onClick={() => ping('Ссылка скопирована 🔗')} aria-label="Поделиться" style={{ ...pill, padding: '8px 12px', backgroundColor: CTRL.surface, color: CTRL.text }}>
            <IconShareVK size={17} />
          </button>
        </div>
      </div>

      {open &&
      <PCommentSheet host={host} comments={comments} onAdd={addComment} onClose={() => setOpen(false)} />
      }
      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)'
        }}>{toast}</div>, host)}
    </article>);

}

/* ── карточка анонса занятия в ленте ───────────────────────────────
   Появляется в Ленте, когда занятие выкладывают. Ссылается на practice
   через post.practiceId; тап по обложке открывает ту же шторку занятия. */
function PClassAnnounceCard({ post, practice, onLike, onTag, activeTag, onDelete, onEdit }) {
  const author = usePAuthor();
  const [p, setP] = uSp(() => JSON.parse(JSON.stringify(practice)));
  const [open, setOpen] = uSp(false);
  const [comments, setComments] = uSp(() => post.comments.map((c) => ({ ...c })));
  const [toast, setToast] = uSp(null);
  const [sheetOpen, setSheetOpen] = uSp(false);
  const [menu, setMenu] = uSp(false);
  const [editing, setEditing] = uSp(false);
  const [draft, setDraft] = uSp(post.text || '');
  const rootRef = React.useRef(null);
  const tref = React.useRef(0);
  const host = rootRef.current && rootRef.current.closest('.ylp');

  const ping = (msg) => { setToast(msg); clearTimeout(tref.current); tref.current = setTimeout(() => setToast(null), 1900); };
  React.useEffect(() => () => clearTimeout(tref.current), []);

  const addComment = (text) =>
    setComments((cs) => [...cs, { id: 'cu' + Date.now(), name: 'Вы', handle: 'you', glyph: '🧘‍♀️', text, likes: 0, time: 'только что' }]);

  // запись / покупка — та же логика, что в разделе «Занятия»
  const onCTA = (x) => {
    if (x.status === 'upcoming') {
      if (x.enrolled) { if (x.live) ping('Подключаемся к эфиру…'); return; }
      setP((s) => ({ ...s, enrolled: true }));
      ping(x.place === 'studio' ? '✓ Место забронировано — ждём в студии' : (x.free ? '✓ Вы записаны' : '✓ Оплачено — вы записаны'));
    } else {
      if (x.free || x.purchased) { ping('Открываем запись…'); return; }
      setP((s) => ({ ...s, purchased: true }));
      ping('✓ Покупка оформлена — доступ открыт');
    }
  };
  const setTeachers = (id, ids) => setP((s) => ({ ...s, teachers: ids }));

  const [tint, pop, e0] = PR_TINT[p.tone] || PR_TINT.a;
  const offline = p.place === 'studio';
  const upcoming = p.status === 'upcoming';
  const [kEmoji, kLabel] = offline ? ['📍', 'Очно'] : (PR_KIND[p.kind] || PR_KIND.video);
  const leads = leadsOf(p, author);
  const priceLabel = p.free ? 'Бесплатно' : (p.price || null);

  const heroBadge = upcoming
    ? (offline ? { e: '📍', t: 'Живое занятие', c: POP.grape }
      : (p.live ? { e: '🔴', t: 'Прямой эфир', c: POP.coral } : { e: '📅', t: 'Скоро', c: POP.grape }))
    : { e: '🎬', t: 'Запись', c: pop };

  // конфиг закреплённой кнопки (как в шторке, но компактно)
  let cta;
  if (upcoming) {
    if (p.enrolled) cta = p.live ? { label: '▶  Подключиться к эфиру', tone: 'go' } : { label: '✓ Вы записаны', tone: 'done' };
    else cta = { label: p.free ? 'Записаться бесплатно' : `Записаться · ${p.price}`, tone: 'pay' };
  } else {
    cta = (p.free || p.purchased) ? { label: '▶  Смотреть запись', tone: 'go' } : { label: `Купить · ${p.price}`, tone: 'pay' };
  }
  const ctaStyle = {
    pay: { backgroundColor: BTN.primary, color: '#fff', boxShadow: '0 6px 16px -8px rgba(255,111,97,0.9)', cursor: 'pointer' },
    go: { backgroundColor: pop, color: '#fff', boxShadow: `0 6px 16px -8px ${pop}`, cursor: 'pointer' },
    done: { backgroundColor: 'var(--p-ctrl)', color: POP.leaf, boxShadow: 'none', cursor: 'default' }
  }[cta.tone];

  const pill = {
    display: 'inline-flex', alignItems: 'center', gap: 7, border: 'none', cursor: 'pointer',
    padding: '8px 14px', borderRadius: 999, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5,
    transition: 'all .15s ease'
  };

  const metaChip = (e, t, c) =>
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 700, color: c || 'var(--p-mute)', backgroundColor: 'var(--p-ctrl)', padding: '4px 9px', borderRadius: 999, whiteSpace: 'nowrap' }}>
      <span style={{ fontSize: 12 }}>{e}</span>{t}
    </span>;

  /* атомы шторок меню/редактирования */
  const sheetCard = { background: 'var(--p-elev)', borderRadius: 22, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' };
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 4px' };
  const sheetHead = { fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12, color: 'var(--p-faint)', textAlign: 'center', padding: '2px 0 12px', textTransform: 'uppercase', letterSpacing: '0.07em' };
  const cancelBtn = { width: '100%', marginTop: 8, height: 50, borderRadius: 18, border: 'none', cursor: 'pointer', background: 'var(--p-elev)', color: 'var(--p-ink)', fontFamily: "'Nunito Sans', sans-serif", fontWeight: 600, fontSize: 15, boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.32)' };
  const menuRow = (danger) => ({ display: 'flex', alignItems: 'center', gap: 12, width: '100%', border: 'none', background: 'none', cursor: 'pointer', padding: '15px 18px', fontFamily: "'Nunito Sans', sans-serif", fontWeight: 500, fontSize: 15.5, color: danger ? POP.coral : 'var(--p-ink)', textAlign: 'left' });

  const startEdit = () => { setMenu(false); setDraft(post.text || ''); setEditing(true); };
  const saveEdit = () => { onEdit && onEdit(post.id, draft.trim()); setEditing(false); };
  const doDelete = () => { setMenu(false); onDelete && onDelete(post.id); };

  return (
    <article ref={rootRef} style={{ padding: '4px 14px 14px' }}>
      <div style={{ backgroundColor: 'var(--p-elev)', borderRadius: 22, padding: 14, boxShadow: '0 8px 24px -16px rgba(40,20,60,0.4)', border: '1px solid var(--p-border)' }}>
        {/* header */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <PAvatar size={40} ring={false} src={author.avatar} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-ink)' }}>
              {author.name}{author.verified && <span style={{ color: POP.sky, display: 'inline-flex' }}><IconVerified size={13} /></span>}
            </div>
            <div style={{ fontSize: 11.5, color: 'var(--p-faint)', fontWeight: 600 }}>{author.spiritual} · {post.time}</div>
          </div>
          <button className="picon" onClick={() => setMenu(true)} style={{ width: 30, height: 30, color: 'var(--p-faint)', flexShrink: 0 }}><IconMenu size={18} /></button>
        </div>

        {/* ярлык анонса + подпись */}
        <div style={{ marginTop: 11 }}>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, padding: '3px 9px 3px 7px', borderRadius: 999, backgroundColor: tint, color: pop, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 11.5, letterSpacing: '0.01em' }}>
            <span style={{ fontSize: 11 }}>✨</span>Анонс занятия
          </span>
        </div>
        {post.text &&
        <p style={{ margin: '9px 0 0', fontSize: 14, lineHeight: 1.5, color: 'var(--p-soft)', textWrap: 'pretty' }}>{post.text}</p>}

        {/* встроенная карточка занятия — тап открывает шторку */}
        <div className="tap pbtn" onClick={() => setSheetOpen(true)} style={{ marginTop: 12, borderRadius: 18, overflow: 'hidden', border: '1px solid var(--p-border)', backgroundColor: 'var(--p-bg)' }}>
          <div style={{ position: 'relative' }}>
            <PPhoto image={practiceCover(p)} height={146} radius={0} />
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, rgba(20,14,30,0.62), rgba(20,14,30,0) 62%)' }} />
            <span style={{ position: 'absolute', top: 11, left: 11, display: 'inline-flex', alignItems: 'center', gap: 5, padding: '5px 10px', borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.94)', color: heroBadge.c, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11.5 }}>{heroBadge.e} {heroBadge.t}</span>
            {priceLabel &&
            <span style={{ position: 'absolute', top: 11, right: 11, padding: '5px 11px', borderRadius: 999, backgroundColor: 'rgba(20,14,30,0.58)', color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12, backdropFilter: 'blur(4px)', WebkitBackdropFilter: 'blur(4px)' }}>{priceLabel}</span>}
            <h3 style={{ position: 'absolute', left: 13, right: 13, bottom: 11, margin: 0, color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16.5, lineHeight: 1.22, textWrap: 'pretty', textShadow: '0 1px 8px rgba(0,0,0,0.4)' }}>{p.title}</h3>
          </div>

          <div style={{ padding: '12px 13px 13px' }}>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
              {metaChip(kEmoji, kLabel, pop)}
              {metaChip('⏱', p.dur)}
              {p.when && metaChip(upcoming ? '📅' : '🕓', p.when, upcoming ? (offline ? POP.grape : (p.live ? POP.coral : POP.grape)) : 'var(--p-mute)')}
            </div>
            {offline && p.venue && p.venue.studio &&
            <div style={{ display: 'flex', alignItems: 'center', gap: 5, marginTop: 9, fontSize: 12, fontWeight: 700, color: 'var(--p-mute)' }}>🏠 {p.venue.studio}{p.venue.metro ? ` · м. ${p.venue.metro}` : ''}</div>}
            <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginTop: 10 }}>
              <PTeacherStack leads={leads} size={22} />
              <span style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--p-mute)', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {leads.length > 1 ? 'Ведут ' : 'Ведёт '}
                <span style={{ color: 'var(--p-soft)' }}>{leads.map((t) => leadName(t, author)).join(' · ')}</span>
              </span>
            </div>
          </div>
        </div>

        {/* CTA + соц-действия */}
        <button className="pbtn" onClick={() => cta.tone !== 'done' && onCTA(p)} disabled={cta.tone === 'done'} style={{
          width: '100%', marginTop: 12, height: 48, borderRadius: 15, border: 'none',
          fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5,
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 7, ...ctaStyle
        }}>{cta.label}</button>

        {/* hashtags — отдельной строкой, как в остальных типах постов */}
        {post.tags && post.tags.length > 0 &&
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 12 }}>
          {post.tags.map((t) => {
            const on = activeTag === t;
            return (
              <button key={t} className="tap" onClick={() => onTag && onTag(t)} style={{
                border: 'none', cursor: 'pointer', padding: '4px 11px', borderRadius: 999,
                backgroundColor: on ? POP.sky : CTRL.surface, color: on ? '#fff' : POP.sky,
                fontFamily: "'Nunito Sans', sans-serif", fontSize: 12, fontWeight: 700, transition: 'all .15s ease'
              }}>#{t}</button>);
          })}
        </div>}

        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 12 }}>
          <button onClick={() => onLike(post.id)} style={{ ...pill, backgroundColor: post.liked ? TINT.coral : CTRL.surface, color: post.liked ? POP.coral : CTRL.text }}>
            <IconHeart size={17} filled={post.liked} />{post.likes}
          </button>
          <button onClick={() => setOpen(true)} style={{ ...pill, backgroundColor: CTRL.surface, color: CTRL.text }}>
            <IconComment size={16} />{comments.length}
          </button>
          <div style={{ flex: 1 }} />
          <button onClick={() => ping('Ссылка скопирована 🔗')} aria-label="Поделиться" style={{ ...pill, padding: '8px 12px', backgroundColor: CTRL.surface, color: CTRL.text }}>
            <IconShareVK size={17} />
          </button>
        </div>
      </div>

      {open &&
      <PCommentSheet host={host} comments={comments} onAdd={addComment} onClose={() => setOpen(false)} />}
      {sheetOpen && host &&
      <PClassSheet host={host} p={p} onClose={() => setSheetOpen(false)} onCTA={onCTA} onSetTeachers={setTeachers} />}

      {/* меню записи: изменить / удалить */}
      {menu && host && ReactDOM.createPortal(
        <React.Fragment>
          <div className="pscrim" onClick={() => setMenu(false)} />
          <div className="psheet" style={{ padding: '0 8px 12px' }}>
            <div style={sheetCard}>
              <div style={grabber} />
              <button className="tap" onClick={startEdit} style={menuRow(false)}>
                <span style={{ width: 24, display: 'inline-flex', justifyContent: 'center', color: 'var(--p-mute)' }}><IconEdit size={20} /></span>Изменить анонс
              </button>
              <div style={{ height: 1, background: 'var(--p-border)' }} />
              <button className="tap" onClick={doDelete} style={menuRow(true)}>
                <span style={{ width: 24, display: 'inline-flex', justifyContent: 'center' }}><IconTrash size={20} /></span>Удалить
              </button>
            </div>
            <button className="tap" onClick={() => setMenu(false)} style={cancelBtn}>Отмена</button>
          </div>
        </React.Fragment>, host)}

      {/* шторка редактирования подписи */}
      {editing && host && ReactDOM.createPortal(
        <React.Fragment>
          <div className="pscrim" onClick={() => setEditing(false)} />
          <div className="psheet" style={{ padding: '0 8px 12px' }}>
            <div style={{ ...sheetCard, padding: 16 }}>
              <div style={grabber} />
              <div style={sheetHead}>Изменить анонс</div>
              <textarea autoFocus value={draft} onChange={(e) => setDraft(e.target.value)}
                placeholder="Текст анонса…" rows={4} style={{
                  width: '100%', boxSizing: 'border-box', borderRadius: 14, border: '1.5px solid var(--p-border)',
                  background: 'var(--p-ctrl)', padding: '12px 14px', fontFamily: "'Nunito Sans', sans-serif",
                  fontSize: 14, lineHeight: 1.5, color: 'var(--p-ink)', outline: 'none', resize: 'none'
                }} />
              <button className="pbtn" onClick={saveEdit} style={{
                width: '100%', marginTop: 12, height: 48, borderRadius: 14, border: 'none', cursor: 'pointer',
                backgroundColor: BTN.primary, color: '#fff', fontFamily: "'Quicksand', sans-serif",
                fontWeight: 800, fontSize: 15, boxShadow: '0 6px 16px -7px rgba(255,111,97,0.9)'
              }}>Сохранить</button>
            </div>
            <button className="tap" onClick={() => setEditing(false)} style={cancelBtn}>Отмена</button>
          </div>
        </React.Fragment>, host)}

      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)'
        }}>{toast}</div>, host)}
    </article>);

}

/* ── grid ──────────────────────────────────────────────────────── */
function pKfmt(n) {
  if (n >= 1000) {
    const k = n / 1000;
    return (k >= 10 ? Math.round(k) : k.toFixed(1).replace(/\.0$/, '')).toString().replace('.', ',') + 'К';
  }
  return String(n);
}
/* вид записи: иконка + подпись + плитка-подложка для не-фото */
const G_KIND = {
  class: { label: 'Занятие', Icon: IconFlame, tint: ['var(--t-coral)', 'var(--t-sun)'], pop: POP.coral },
  video: { label: 'Видео', Icon: IconVideo, tint: ['var(--t-sky)', 'var(--t-grape)'], pop: POP.sky },
  audio: { label: 'Аудио', Icon: IconAudio, tint: ['var(--t-grape)', 'var(--t-sky)'], pop: POP.grape },
  photo: { label: 'Фото', Icon: IconGrid, tint: ['var(--t-mint)', 'var(--t-sky)'], pop: POP.mint },
  text: { label: 'Текст', Icon: IconList, tint: ['var(--t-sun)', 'var(--t-mint)'], pop: POP.leaf }
};
const gKindOf = (p) => {
  if (p.type === 'class') return 'class';
  const m = p.media || [];
  if (m.includes('video')) return 'video';
  if (m.includes('audio')) return 'audio';
  if (m.includes('photo') || p.image) return 'photo';
  return 'text';
};

/* метка вида — верхний левый угол; на фото — тёмное стекло, на плитке — цветная */
function PGridBadge({ kind, onPhoto }) {
  const k = G_KIND[kind];
  const I = k.Icon;
  return (
    <div style={{
      position: 'absolute', top: 6, left: 6, padding: '3px 7px', borderRadius: 999, zIndex: 2,
      display: 'inline-flex', alignItems: 'center', gap: 3.5,
      fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, letterSpacing: '.2px',
      color: onPhoto ? '#fff' : '#fff', backgroundColor: onPhoto ? 'rgba(20,14,30,0.42)' : k.pop,
      backdropFilter: onPhoto ? 'blur(3px)' : undefined, WebkitBackdropFilter: onPhoto ? 'blur(3px)' : undefined
    }}><I size={11} style={{ strokeWidth: 2 }} />{k.label}</div>
  );
}

/* мини-волна — для аудио-записей без текста */
const G_WAVE = [7, 13, 20, 11, 24, 16, 27, 19, 12, 22, 15, 25, 10, 18, 8, 21, 14];
function PGridWave({ color }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 2, height: 30 }}>
      {G_WAVE.map((h, i) => <span key={i} style={{ width: 2, height: h, borderRadius: 999, backgroundColor: color, opacity: 0.55 }} />)}
    </div>
  );
}

function PGridCell({ post, practice, onOpen }) {
  const kind = gKindOf(post);
  const k = G_KIND[kind];
  const cover = kind === 'photo' || kind === 'video' ? post.image || 'morning' : kind === 'class' && practice ? practiceCover(practice) : null;
  const onPhoto = !!cover;
  const likes = post.likes || 0;
  const nComments = (post.comments || []).length;
  const title = kind === 'class' && practice ? practice.title : null;
  const text = (post.text || '').trim();
  const glyph = kind === 'audio' ? '🎧' : '💬';

  return (
    <div className="tap" onClick={() => onOpen && onOpen(post.id)} style={{ position: 'relative', aspectRatio: '1 / 1', overflow: 'hidden', backgroundColor: 'var(--p-bg)', cursor: 'pointer' }}>
      {cover ?
        <PPhoto image={cover} height="100%" radius={0} /> :
        <div style={{ position: 'absolute', inset: 0, padding: '26px 9px 26px', background: `linear-gradient(140deg, ${k.tint[0]}, ${k.tint[1]})`, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
          {text ?
            <div style={{
              fontFamily: "'Nunito Sans', sans-serif", fontSize: 10.5, lineHeight: 1.42, color: 'var(--p-ink)', width: '100%',
              display: '-webkit-box', WebkitLineClamp: 5, WebkitBoxOrient: 'vertical', overflow: 'hidden', textWrap: 'pretty'
            }}>{text}</div> :
            <React.Fragment>
              <div style={{ fontSize: 22, lineHeight: 1 }}>{glyph}</div>
              {kind === 'audio' && <PGridWave color={k.pop} />}
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 9.5, color: 'var(--p-mute)', textAlign: 'center', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                {kind === 'audio' ? post.audio && post.audio.title || 'аудио без подписи' : 'без текста'}
              </div>
            </React.Fragment>
          }
        </div>
      }
      <PGridBadge kind={kind} onPhoto={onPhoto} />
      {/* дата — верхний правый угол */}
      <div style={{
        position: 'absolute', top: 6, right: 6, padding: '3px 6px', borderRadius: 999, zIndex: 2,
        display: 'inline-flex', alignItems: 'center', gap: 3,
        fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 9.5, letterSpacing: '.2px',
        color: onPhoto ? '#fff' : 'var(--p-mute)',
        background: onPhoto ? 'rgba(20,14,30,0.32)' : 'rgba(255,255,255,0.62)',
        backdropFilter: 'blur(3px)', WebkitBackdropFilter: 'blur(3px)'
      }}>{post.time}</div>
      {/* название занятия — поверх обложки */}
      {title &&
        <div style={{
          position: 'absolute', left: 6, right: 6, bottom: 24, zIndex: 2,
          fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11.5, lineHeight: 1.25, color: '#fff',
          display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden'
        }}>{title}</div>
      }
      {onPhoto &&
        <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, height: '58%', background: 'linear-gradient(to top, rgba(20,14,30,0.66), rgba(20,14,30,0))' }} />
      }
      {/* метрики снизу */}
      <div style={{
        position: 'absolute', left: 0, right: 0, bottom: 0, padding: '0 7px 6px', zIndex: 2,
        display: 'flex', alignItems: 'center', gap: 9,
        color: onPhoto ? '#fff' : 'var(--p-ink)', fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 10.5
      }}>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3 }}>
          <IconHeart size={12} style={{ strokeWidth: 2 }} filled={post.liked} />{pKfmt(likes)}
        </span>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3 }}>
          <IconComment size={12} style={{ strokeWidth: 2 }} />{nComments}
        </span>
      </div>
    </div>);

}

function PGrid({ feed = FEED, practiceById = {}, onOpen }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 0, marginTop: 4 }}>
      {feed.map((p) => <PGridCell key={p.id} post={p} practice={practiceById[p.practiceId]} onOpen={onOpen} />)}
    </div>);

}

/* ── practices ─────────────────────────────────────────────────── */
const PR_TINT = { a: [TINT.coral, POP.coral, '🔥'], b: [TINT.sky, POP.sky, '🌙'], c: [TINT.mint, POP.mint, '🌬️'] };
const PR_KIND = { video: ['🎬', 'Видео'], audio: ['🎧', 'Аудио'] };
const PR_SUBTABS = [['upcoming', 'Предстоящие'], ['recording', 'Записи']];

/* ── ведущие занятия ────────────────────────────────────────────
   teachers пуст / не указан → ведёт аккаунт (self).
   Иначе ведут отмеченные учителя (один или несколько). */
const TEACHER_BY_ID = Object.fromEntries((window.TEACHERS || []).map((t) => [t.id, t]));
const PRACTICE_BY_ID = Object.fromEntries((window.PRACTICES || []).map((p) => [p.id, p]));
function leadsOf(p, author) {
  const ids = (p.teachers && p.teachers.length) ? p.teachers : ['self'];
  const list = ids.map((id) => TEACHER_BY_ID[id]).filter(Boolean);
  // на чужом профиле 'self' = владелец профиля: убираем дубль, если он же есть отдельным учителем
  if (author && author !== SELF_AUTHOR) {
    const seen = new Set();
    return list.filter((t) => {
      const key = t.id === 'self' ? author.spiritual : t.spiritual;
      if (seen.has(key)) return false;
      seen.add(key); return true;
    });
  }
  return list;
}

/* стопка эмодзи-аватаров ведущих (перекрытие) */
function PTeacherStack({ leads, size = 24 }) {
  const author = usePAuthor();
  return (
    <div style={{ display: 'inline-flex', flexShrink: 0 }}>
      {leads.map((t, i) =>
      <span key={t.id} style={{
        width: size, height: size, borderRadius: '50%', flexShrink: 0, overflow: 'hidden',
        marginLeft: i === 0 ? 0 : -size * 0.34,
        background: 'var(--t-coral)',
        border: '2px solid var(--p-elev)', boxShadow: '0 1px 3px -1px rgba(20,14,30,0.3)',
        display: 'inline-flex', zIndex: leads.length - i
      }}><PImg src={leadPhoto(t, author)} /></span>
      )}
    </div>);

}

function PPracticeRow({ p, onOpen }) {
  const author = usePAuthor();
  const [tint, pop, e0] = PR_TINT[p.tone] || PR_TINT.a;
  const offline = p.place === 'studio';           // живое занятие в студии
  const e = offline ? '🏠' : e0;
  const [kEmoji, kLabel] = offline ? ['📍', 'Очно'] : (PR_KIND[p.kind] || PR_KIND.video);
  const upcoming = p.status === 'upcoming';
  const owned = p.enrolled || p.purchased;
  const leads = leadsOf(p, author);
  const ownLed = leads.length === 1 && leads[0].id === 'self';

  // ярлык статуса доступа (нижний-левый)
  let access = null;
  if (owned) access = { e: '✓', t: p.purchased ? 'Куплено' : 'Вы записаны', c: POP.leaf };
  else if (p.free) access = { e: '🎁', t: 'Бесплатно', c: POP.leaf };
  else if (offline && p.venue && p.venue.spots != null) access = { e: '🔥', t: `Мест: ${p.venue.spots}`, c: POP.coral };

  // цена для коммерческих занятий (нижний-правый)
  const showPrice = !owned && !p.free && p.price ? p.price : null;

  return (
    <div className="tap pbtn" onClick={() => onOpen(p)} style={{ cursor: 'pointer', padding: 12, borderRadius: 18, backgroundColor: 'var(--p-elev)', border: '1px solid var(--p-border)', boxShadow: '0 6px 18px -14px rgba(40,20,60,0.4)' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-ink)', lineHeight: 1.25, textWrap: 'pretty' }}>{p.title}</div>
          <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '5px 7px', marginTop: 6 }}>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 700, color: pop, backgroundColor: tint, padding: '3px 8px', borderRadius: 999 }}>{kEmoji} {kLabel}</span>
            <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-mute)' }}>⏱ {p.dur}</span>
            <span style={{ fontSize: 11, fontWeight: 600, color: 'var(--p-faint)' }}>{p.level}</span>
          </div>
          {p.when &&
            <div style={{ marginTop: 5 }}>
              {upcoming
                ? <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 700, color: offline ? POP.grape : (p.live ? POP.coral : POP.grape) }}>
                    {offline ? '📅' : (p.live ? '🔴' : '📅')} {p.when}{offline && p.venue ? ` · ${p.venue.studio}` : ''}
                  </span>
                : <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11, fontWeight: 600, color: 'var(--p-faint)' }}>🕓 {p.when}</span>}
            </div>}

          {/* ведущие занятия */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 7 }}>
            <PTeacherStack leads={leads} size={22} />
            <span style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-mute)', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
              {leads.length > 1 ? 'Ведут ' : 'Ведёт '}
              <span style={{ color: 'var(--p-soft)' }}>{leads.map((t) => leadName(t, author)).join(' · ')}</span>
            </span>
          </div>
        </div>
        <span style={{ fontSize: 22, fontWeight: 700, color: 'var(--p-faint)', flexShrink: 0, lineHeight: 1, paddingLeft: 2 }}>›</span>
      </div>

      {/* нижняя строка: статус доступа + цена */}
      {(access || showPrice) &&
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 11, paddingTop: 11, borderTop: '1px solid var(--p-border)' }}>
          {access &&
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 11.5, fontWeight: 700, color: access.c, whiteSpace: 'nowrap' }}>
              <span style={{ fontSize: 12 }}>{access.e}</span>{access.t}
            </span>}
          {showPrice &&
            <span style={{ marginLeft: 'auto', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14, color: 'var(--p-ink)', whiteSpace: 'nowrap' }}>{showPrice}</span>}
        </div>}
    </div>);

}

/* ── единая шторка занятия: обложка, описание, цена, закреплённая кнопка ── */
function PClassSheet({ host, p, onClose, onCTA, onSetTeachers }) {
  const author = usePAuthor();
  const mine = author === SELF_AUTHOR;
  const [tint, pop] = PR_TINT[p.tone] || PR_TINT.a;
  const offline = p.place === 'studio';
  const v = p.venue || {};
  const upcoming = p.status === 'upcoming';
  const [kEmoji, kLabel] = offline ? ['📍', 'Очно'] : (PR_KIND[p.kind] || PR_KIND.video);
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 4px' };

  const [editLeads, setEditLeads] = uSp(false);
  const leads = leadsOf(p, author);
  const sel = p.teachers || [];
  const toggleLead = (id) => {
    const next = sel.includes(id) ? sel.filter((x) => x !== id) : [...sel, id];
    onSetTeachers(p.id, next);
  };

  // конфиг закреплённой кнопки
  let cta;
  if (upcoming) {
    if (p.enrolled) cta = p.live
      ? { label: '▶  Подключиться к эфиру', tone: 'go' }
      : { label: '✓ Вы записаны', tone: 'done' };
    else cta = { label: p.free ? 'Записаться' : `Записаться · ${p.price}`, tone: 'pay' };
  } else {
    cta = (p.free || p.purchased)
      ? { label: '▶  Смотреть запись', tone: 'go' }
      : { label: `Купить · ${p.price}`, tone: 'pay' };
  }
  const ctaStyle = {
    pay:  { backgroundColor: BTN.primary, color: '#fff', boxShadow: '0 8px 20px -8px rgba(255,111,97,0.9)', cursor: 'pointer' },
    go:   { backgroundColor: pop, color: '#fff', boxShadow: `0 8px 20px -8px ${pop}`, cursor: 'pointer' },
    done: { backgroundColor: 'var(--p-ctrl)', color: POP.leaf, boxShadow: 'none', cursor: 'default' }
  }[cta.tone];

  // верхний бейдж на обложке
  const heroBadge = upcoming
    ? (offline ? { e: '📍', t: 'Живое занятие', c: POP.grape }
      : (p.live ? { e: '🔴', t: 'Прямой эфир', c: POP.coral }
        : { e: '📅', t: 'Скоро', c: POP.grape }))
    : { e: '🎬', t: 'Запись', c: pop };

  const metaChip = (e, t) =>
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '7px 12px', borderRadius: 999, backgroundColor: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12.5 }}>
      <span style={{ fontSize: 14 }}>{e}</span>{t}
    </span>;

  const infoRow = (e, label, value) =>
    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12, padding: '11px 0', borderTop: '1px solid var(--p-border)' }}>
      <span style={{ fontSize: 19, width: 22, textAlign: 'center', flexShrink: 0 }}>{e}</span>
      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{label}</div>
        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)', marginTop: 2, textWrap: 'pretty' }}>{value}</div>
      </div>
    </div>;

  const priceLabel = p.free ? 'Бесплатно' : (p.price || null);

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 24, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={grabber} />

          {/* прокручиваемая часть */}
          <div style={{ maxHeight: 408, overflowY: 'auto', scrollbarWidth: 'none' }}>
            {/* обложка — показываем фото целиком, без обрезки по высоте */}
            <div style={{ position: 'relative' }}>
              {(() => {
                const ck = practiceCover(p);
                const [ca, cb] = PHOTO_MAP[ck] || ['#FFB020', '#FF6F61'];
                return (
                  <div style={{ width: '100%', minHeight: 150, background: `linear-gradient(135deg, ${ca}, ${cb})` }}>
                    <PImg src={REAL_IMG[ck]} style={{ height: 'auto', objectFit: 'initial' }} />
                  </div>);
              })()}
              <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, rgba(20,14,30,0.28), rgba(20,14,30,0))' }} />
              <span style={{ position: 'absolute', top: 12, left: 14, display: 'inline-flex', alignItems: 'center', gap: 5, padding: '6px 11px', borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.94)', color: heroBadge.c, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12 }}>{heroBadge.e} {heroBadge.t}</span>
              {priceLabel &&
                <span style={{ position: 'absolute', bottom: 12, right: 14, padding: '6px 13px', borderRadius: 999, backgroundColor: 'rgba(20,14,30,0.6)', color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13.5, backdropFilter: 'blur(4px)', WebkitBackdropFilter: 'blur(4px)' }}>{priceLabel}</span>}
            </div>

            <div style={{ padding: '15px 18px 4px' }}>
              <h3 style={{ margin: 0, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', lineHeight: 1.25, textWrap: 'pretty' }}>{p.title}</h3>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7, marginTop: 12 }}>
                {metaChip(kEmoji, kLabel)}
                {metaChip('⏱', p.dur)}
                {metaChip('🎚', p.level)}
              </div>

              {/* ── ведущие занятия ── */}
              <div style={{ marginTop: 14, padding: 13, borderRadius: 16, backgroundColor: tint }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span style={{ flex: 1, fontSize: 11, fontWeight: 800, color: pop, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                    {leads.length > 1 ? 'Ведущие' : 'Ведущий'}
                  </span>
                  {mine && <button className="tap" onClick={() => setEditLeads((v) => !v)} style={{
                    border: 'none', cursor: 'pointer', padding: '5px 12px', borderRadius: 999,
                    backgroundColor: 'var(--p-elev)', color: pop,
                    fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12
                  }}>{editLeads ? '✓ Готово' : '✎ Изменить'}</button>}
                </div>

                {/* текущие ведущие */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: 9, marginTop: 11 }}>
                  {leads.map((t) =>
                  <div key={t.id} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                      <span style={{
                        width: 36, height: 36, borderRadius: '50%', flexShrink: 0, overflow: 'hidden',
                        background: 'var(--t-coral)',
                        border: '2px solid var(--p-elev)',
                        display: 'inline-flex'
                      }}><PImg src={leadPhoto(t, author)} /></span>
                      <div style={{ minWidth: 0 }}>
                        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14, color: 'var(--p-ink)', display: 'flex', alignItems: 'center', gap: 6 }}>
                          {t.id === 'self' ? (mine ? t.spiritual : author.spiritual) : t.spiritual}
                          {t.id === 'self' && mine && <span style={{ fontSize: 10, fontWeight: 800, color: pop, backgroundColor: 'var(--p-elev)', padding: '2px 7px', borderRadius: 999 }}>ВЫ</span>}
                        </div>
                        <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-mute)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.id === 'self' && !mine ? author.name + ' · ' + (author.role || '') : t.name + ' · ' + t.role}</div>
                      </div>
                    </div>
                  )}
                </div>

                {/* редактор: отметить ведущих */}
                {editLeads &&
                  <div style={{ marginTop: 12, paddingTop: 12, borderTop: '1px dashed var(--p-border)' }}>
                    <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--p-soft)', lineHeight: 1.45, marginBottom: 10, textWrap: 'pretty' }}>
                      Отметьте, кто ведёт занятие. Если никого не отметить — ведёте вы (аккаунт).
                    </div>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                      {(window.TEACHERS || []).map((t) => {
                        const on = sel.includes(t.id);
                        return (
                          <button key={t.id} className="tap pbtn" onClick={() => toggleLead(t.id)} style={{
                            display: 'inline-flex', alignItems: 'center', gap: 7, border: 'none', cursor: 'pointer',
                            padding: '7px 12px 7px 8px', borderRadius: 999,
                            backgroundColor: on ? pop : 'var(--p-elev)',
                            color: on ? '#fff' : 'var(--p-soft)',
                            fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12.5,
                            boxShadow: on ? `0 5px 14px -7px ${pop}` : 'none', transition: 'all .14s ease'
                          }}>
                            <span style={{
                              width: 24, height: 24, borderRadius: '50%', flexShrink: 0, overflow: 'hidden',
                              background: 'var(--t-coral)',
                              display: 'inline-flex'
                            }}><PImg src={teacherPhoto(t)} /></span>
                            {t.id === 'self' ? 'Вы' : t.spiritual}
                            <span style={{ fontSize: 12, marginLeft: 1 }}>{on ? '✓' : '+'}</span>
                          </button>);
                      })}
                    </div>
                  </div>}
              </div>

              {/* описание */}
              {p.desc &&
                <p style={{ margin: '14px 0 0', fontSize: 14, lineHeight: 1.55, color: 'var(--p-soft)', textWrap: 'pretty' }}>{p.desc}</p>}

              {/* детали */}
              <div style={{ marginTop: 14 }}>
                {p.when && infoRow(upcoming ? '📅' : '🕓', 'Когда', p.when)}
                {offline && v.studio && infoRow('🏠', 'Где', v.studio)}
                {offline && v.address && infoRow('🧭', 'Адрес', v.address + (v.metro ? ` · м. ${v.metro}` : ''))}
              </div>

              {/* подсказка для живого занятия */}
              {offline &&
                <div style={{ marginTop: 4, marginBottom: 4, display: 'flex', gap: 9, padding: '11px 13px', borderRadius: 14, backgroundColor: tint }}>
                  <span style={{ fontSize: 16, flexShrink: 0 }}>💡</span>
                  <div style={{ fontSize: 12.5, lineHeight: 1.45, color: 'var(--p-soft)', fontWeight: 600, textWrap: 'pretty' }}>Это живое занятие в студии — онлайн-подключения нет. Запишитесь и приходите на коврик.</div>
                </div>}

              {offline && v.spots != null && !p.enrolled &&
                <div style={{ marginTop: 10, textAlign: 'center', fontSize: 12.5, fontWeight: 700, color: POP.coral }}>🔥 Осталось мест: {v.spots}</div>}
            </div>
          </div>

          {/* закреплённая кнопка */}
          <div style={{ padding: '12px 16px 22px', borderTop: '1px solid var(--p-border)', background: 'var(--p-elev)' }}>
            <button className="pbtn" onClick={() => cta.tone !== 'done' && onCTA(p)} disabled={cta.tone === 'done'} style={{
              width: '100%', height: 52, borderRadius: 16, border: 'none',
              fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15,
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 7, ...ctaStyle
            }}>{cta.label}</button>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

function PPractices({ items, setItems }) {
  const [sub, setSub] = uSp('upcoming');
  const [open, setOpen] = uSp(null); // id занятия для шторки
  const [toast, setToast] = uSp(null);
  const rootRef = React.useRef(null);
  const tref = React.useRef(0);

  const ping = (msg) => {
    setToast(msg);
    clearTimeout(tref.current);
    tref.current = setTimeout(() => setToast(null), 1900);
  };
  React.useEffect(() => () => clearTimeout(tref.current), []);

  // CTA в шторке: записаться / купить / подключиться / смотреть
  const onCTA = (p) => {
    if (p.status === 'upcoming') {
      if (p.enrolled) { if (p.live) ping('Подключаемся к эфиру…'); return; }
      setItems((xs) => xs.map((x) => x.id === p.id ? { ...x, enrolled: true } : x));
      ping(p.place === 'studio' ? '✓ Место забронировано — ждём в студии' : (p.free ? '✓ Вы записаны' : '✓ Оплачено — вы записаны'));
    } else {
      if (p.free || p.purchased) { ping('Открываем запись…'); return; }
      setItems((xs) => xs.map((x) => x.id === p.id ? { ...x, purchased: true } : x));
      ping('✓ Покупка оформлена — доступ открыт');
    }
  };

  const setTeachers = (id, ids) => setItems((xs) => xs.map((x) => x.id === id ? { ...x, teachers: ids } : x));

  const list = items.filter((p) => p.status === sub);
  const counts = {
    upcoming: items.filter((p) => p.status === 'upcoming').length,
    recording: items.filter((p) => p.status === 'recording').length
  };
  const host = rootRef.current && rootRef.current.closest('.ylp');

  return (
    <div ref={rootRef} style={{ padding: '4px 14px 0' }}>
      {/* под-табы: предстоящие / записи */}
      <div style={{ display: 'flex', gap: 6, backgroundColor: CTRL.surface, borderRadius: 14, padding: 4, marginBottom: 11 }}>
        {PR_SUBTABS.map(([id, label]) => {
          const on = sub === id;
          return (
            <button key={id} onClick={() => setSub(id)} style={{
              flex: 1, height: 38, border: 'none', cursor: 'pointer', borderRadius: 11,
              fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13,
              backgroundColor: on ? 'var(--p-elev)' : 'transparent',
              color: on ? CTRL.accent : CTRL.text,
              boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none',
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 6,
              transition: 'all .15s ease'
            }}>
              {label}
              <span style={{
                fontSize: 11, fontWeight: 800, minWidth: 18, padding: '1px 6px', borderRadius: 999,
                backgroundColor: on ? CTRL.accentSoft : 'var(--p-bg)', color: on ? CTRL.accent : 'var(--p-faint)'
              }}>{counts[id]}</span>
            </button>);

        })}
      </div>

      {/* список занятий */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 9 }}>
        {list.map((p) => <PPracticeRow key={p.id} p={p} onOpen={(x) => setOpen(x.id)} />)}
      </div>

      {host && open && (() => {
        const di = items.find((x) => x.id === open);
        return di ? <PClassSheet host={host} p={di} onClose={() => setOpen(null)} onCTA={onCTA} onSetTeachers={setTeachers} /> : null;
      })()}

      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)'
        }}>{toast}</div>, host)}
    </div>);

}

/* ── «Практики» ── те же карточки, что на странице «Моя практика» (MPracticeCard) ── */
function PPublic() {
  const [items, setMP] = uSp(() => (window.MY_PRACTICES || []).map((p) => ({ ...p })));
  const [toast, setToast] = uSp(null);
  const rootRef = React.useRef(null);
  const tref = React.useRef(0);

  const ping = (msg) => {
    setToast(msg);
    clearTimeout(tref.current);
    tref.current = setTimeout(() => setToast(null), 1900);
  };
  React.useEffect(() => () => clearTimeout(tref.current), []);

  const toggleSave = (p) => {
    setMP((prev) => prev.map((x) => x.id === p.id ? { ...x, saved: !x.saved } : x));
    ping(p.saved ? `«${p.name}» убрана из ваших` : `«${p.name}» добавлена к вам ✓`);
  };

  // «у меня» — свои практики + сохранённые (как сегмент «У меня» на странице «Моя практика»)
  const added = window.isAdded || ((p) => (p.by && p.by.name === 'Вы') || p.saved);
  const shown = items.filter(added);
  const host = rootRef.current && rootRef.current.closest('.ylp');
  const MPCard = window.MPracticeCard;

  return (
    <div ref={rootRef} style={{ padding: '4px 14px 0', display: 'flex', flexDirection: 'column', gap: 12 }}>
      {MPCard && shown.map((p) => <MPCard key={p.id} p={p} onToggleSave={toggleSave} hideAction />)}

      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)'
        }}>{toast}</div>, host)}
    </div>);

}

/* ── bottom nav ── эмодзи + мягкая радуга (свой цвет на каждый пункт) ── */
const NAV_ITEMS = [
  { key: 'practice', label: 'Моя практика', emoji: '🌀', pop: '#E8615A', soft: 'rgba(232,97,90,0.16)' },
  { key: 'classes', label: 'Занятия', emoji: '🧘', pop: '#E8902F', soft: 'rgba(232,144,47,0.16)' },
  { key: 'calendar', label: 'Календарь', emoji: '📅', pop: '#4FA85B', soft: 'rgba(79,168,91,0.16)' },
  { key: 'sangat', label: 'Сангат', emoji: '❤️', pop: '#3E92D8', soft: 'rgba(62,146,216,0.16)' },
  { key: 'ahamkara', label: 'Ахамкара', emoji: '🪬', pop: '#8E55D8', soft: 'rgba(142,85,216,0.16)' }];

function PBottomNav({ active: activeProp, onNav } = {}) {
  const [activeState, setActiveState] = uSp('ahamkara');
  const active = activeProp != null ? activeProp : activeState;
  const setActive = onNav || setActiveState;
  return (
    <div style={{
      position: 'absolute', bottom: 0, left: 0, right: 0, zIndex: 6,
      backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)',
      borderTop: '1px solid var(--p-border)', display: 'flex', alignItems: 'stretch', justifyContent: 'space-around',
      padding: '8px 4px 24px'
    }}>
      {NAV_ITEMS.map(({ key, label, emoji, pop, soft }) => {
        const on = active === key;
        return (
          <button key={key} className="tap pbtn" onClick={() => setActive(key)} style={{
            flex: 1, minWidth: 0, border: 'none', background: 'transparent', cursor: 'pointer',
            display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 5, padding: '2px 1px'
          }}>
            <span style={{
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
              width: 36, height: 36, borderRadius: '50%',
              backgroundColor: on ? soft : 'transparent',
              transform: on ? 'translateY(-1px)' : 'none',
              transition: 'background .18s ease, transform .18s ease'
            }}>
              <span style={{ fontSize: 19, lineHeight: 1, filter: on ? 'none' : 'saturate(0.8) opacity(0.6)' }}>{emoji}</span>
            </span>
            <span style={{
              fontFamily: "'Nunito Sans', sans-serif", fontSize: 9.5, lineHeight: 1, letterSpacing: '-0.1px',
              fontWeight: on ? 800 : 600, whiteSpace: 'nowrap',
              color: on ? pop : 'var(--p-mute)', transition: 'color .18s ease'
            }}>{label}</span>
          </button>);
      })}
    </div>);

}

/* ── shell ─────────────────────────────────────────────────────── */
/* ── строка фильтров: хэштеги + иконка фильтра */
function PFeedFilter({ activeTag, setActiveTag, openSheet, anyFilter, resetAll, hasSheetFilter }) {
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '0 14px 8px' }}>
      <div style={{ flex: 1, minWidth: 0, display: 'flex', gap: 7, overflowX: 'auto', scrollbarWidth: 'none', WebkitOverflowScrolling: 'touch', paddingBottom: 2 }}>
        {anyFilter &&
        <button className="tap" onClick={resetAll} style={{
          flexShrink: 0, border: 'none', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 4,
          padding: '7px 12px', borderRadius: 999, backgroundColor: TINT.coral, color: POP.coral,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 12.5, fontWeight: 800
        }}>✕ Сброс</button>
        }
        {FEED_TAGS.map((t) => {
          const on = activeTag === t;
          return (
            <button key={t} className="tap" onClick={() => setActiveTag(on ? null : t)} style={{
              flexShrink: 0, border: 'none', cursor: 'pointer', whiteSpace: 'nowrap',
              padding: '7px 13px', borderRadius: 999,
              backgroundColor: on ? POP.sky : CTRL.surface, color: on ? '#fff' : 'var(--p-soft)',
              fontFamily: "'Nunito Sans', sans-serif", fontSize: 12.5, fontWeight: 700,
              boxShadow: on ? '0 5px 14px -6px rgba(77,157,224,0.9)' : 'none', transition: 'all .15s ease'
            }}>#{t}</button>);

        })}
      </div>
      <button className="picon" onClick={openSheet} style={{
        flexShrink: 0, width: 40, height: 40, borderRadius: 13, position: 'relative',
        backgroundColor: hasSheetFilter ? CTRL.accentSoft : CTRL.surface
      }}>
        <IconFilter size={20} style={{ color: CTRL.accent }} />
        {hasSheetFilter &&
        <span style={{ position: 'absolute', top: 6, right: 6, width: 9, height: 9, borderRadius: '50%', backgroundColor: POP.coral, border: '2px solid var(--p-bg)' }} />
        }
      </button>
    </div>);

}

/* ── шторка фильтра: тип контента + комментарии */
function PFilterSheet({ host, types, setTypes, comments, setComments, onClose, onReset, count }) {
  if (!host) return null;
  const toggle = (t) => setTypes((prev) => prev.includes(t) ? prev.filter((x) => x !== t) : [...prev, t]);
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 4px' };
  const label = { fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.07em' };

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 22, padding: '0 18px 18px', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={grabber} />
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '4px 0 16px' }}>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 19, color: 'var(--p-ink)' }}>Фильтры</span>
            <button className="tap" onClick={onReset} style={{
              border: 'none', cursor: 'pointer', background: 'none', padding: '4px 6px',
              fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5, color: POP.coral
            }}>Сбросить</button>
          </div>

          <div style={label}>Содержит</div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 11 }}>
            {CONTENT_TYPES.map(([id, e, name]) => {
              const on = types.includes(id);
              return (
                <button key={id} className="tap" onClick={() => toggle(id)} style={{
                  display: 'inline-flex', alignItems: 'center', gap: 7, padding: '10px 15px', borderRadius: 14,
                  border: 'none', cursor: 'pointer',
                  backgroundColor: on ? CTRL.accent : CTRL.surface, color: on ? '#fff' : 'var(--p-soft)',
                  fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14,
                  boxShadow: on ? '0 6px 16px -8px rgba(77,157,224,0.9)' : 'none', transition: 'all .15s ease'
                }}><span style={{ fontSize: 16 }}>{e}</span>{name}</button>);

            })}
          </div>
          <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-faint)', marginTop: 9 }}>Запись должна содержать всё выбранное.</div>

          <div style={{ ...label, marginTop: 22 }}>Комментарии</div>
          <div style={{ display: 'flex', gap: 6, marginTop: 11, backgroundColor: CTRL.surface, borderRadius: 14, padding: 4 }}>
            {[['all', 'Все'], ['with', 'С комментариями'], ['without', 'Без']].map(([id, name]) => {
              const on = comments === id;
              return (
                <button key={id} className="tap" onClick={() => setComments(id)} style={{
                  flex: 1, height: 40, border: 'none', cursor: 'pointer', borderRadius: 11,
                  fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12.5,
                  backgroundColor: on ? 'var(--p-elev)' : 'transparent', color: on ? CTRL.accent : CTRL.text,
                  boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none', transition: 'all .15s ease'
                }}>{name}</button>);

            })}
          </div>

          <button className="pbtn" onClick={onClose} disabled={count === 0} style={{
            width: '100%', marginTop: 20, height: 50, borderRadius: 16, border: 'none',
            cursor: count === 0 ? 'default' : 'pointer', opacity: count === 0 ? 0.5 : 1,
            backgroundColor: BTN.primary, color: '#fff',
            fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15,
            boxShadow: '0 8px 20px -8px rgba(255,111,97,0.9)'
          }}>{count === 0 ? 'Ничего не найдено' : `Показать ${count}`}</button>
        </div>
      </div>
    </React.Fragment>, host);
}

/* ── создание контента: поля, помощники, попап и формы ───────────── */
const COVER_KEYS = ['morning', 'studio', 'asana', 'breath', 'meadow', 'candle', 'mat', 'temple', 'forest'];
const cInput = {
  width: '100%', boxSizing: 'border-box', height: 46, borderRadius: 13,
  border: '1.5px solid var(--p-border)', background: 'var(--p-ctrl)',
  padding: '0 14px', fontFamily: "'Nunito Sans', sans-serif", fontSize: 14.5,
  color: 'var(--p-ink)', outline: 'none'
};
const cLabel = { display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11.5, color: 'var(--p-faint)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 9 };

function PSeg({ value, onChange, opts }) {
  return (
    <div style={{ display: 'flex', gap: 6, backgroundColor: CTRL.surface, borderRadius: 13, padding: 4 }}>
      {opts.map(([v, label]) => {
        const on = value === v;
        return (
          <button key={v} className="tap" onClick={() => onChange(v)} style={{
            flex: 1, height: 42, border: 'none', cursor: 'pointer', borderRadius: 10,
            fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12.5,
            backgroundColor: on ? 'var(--p-elev)' : 'transparent', color: on ? CTRL.accent : CTRL.text,
            boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none', transition: 'all .14s ease'
          }}>{label}</button>);
      })}
    </div>);
}

function PSwitch({ on, onChange }) {
  return (
    <button className="tap" onClick={() => onChange(!on)} aria-pressed={on} style={{
      width: 46, height: 28, borderRadius: 999, border: 'none', cursor: 'pointer', flexShrink: 0, padding: 3,
      backgroundColor: on ? POP.leaf : 'var(--p-border)', transition: 'background .18s ease',
      display: 'flex', alignItems: 'center', justifyContent: on ? 'flex-end' : 'flex-start'
    }}>
      <span style={{ width: 22, height: 22, borderRadius: '50%', background: '#fff', boxShadow: '0 1px 3px rgba(0,0,0,0.3)' }} />
    </button>);
}

function PCoverPicker({ value, onChange }) {
  return (
    <div style={{ display: 'flex', gap: 9, overflowX: 'auto', scrollbarWidth: 'none', paddingBottom: 2 }}>
      {COVER_KEYS.map((k) => {
        const [a, b] = PHOTO_MAP[k];
        const on = value === k;
        return (
          <button key={k} className="tap pbtn" onClick={() => onChange(k)} style={{
            flexShrink: 0, width: 58, height: 58, borderRadius: 15, cursor: 'pointer', overflow: 'hidden',
            border: on ? '3px solid var(--ctrl-accent)' : '3px solid transparent',
            background: `linear-gradient(135deg, ${a}, ${b})`,
            boxShadow: on ? '0 6px 16px -8px rgba(40,20,60,0.5)' : 'none'
          }}><PImg src={REAL_IMG[k]} /></button>);
      })}
    </div>);
}

/* ── всплывающий список «что создать» — якорится к кнопке + ──────── */
function PCreateMenu({ host, anchorRef, onClose, onPick }) {
  const [pos, setPos] = uSp(null);
  React.useLayoutEffect(() => {
    if (!host || !anchorRef.current) return;
    const hr = host.getBoundingClientRect();
    const br = anchorRef.current.getBoundingClientRect();
    setPos({ top: br.bottom - hr.top + 10, left: br.left - hr.left });
  }, []);
  if (!host) return null;

  const opt = (emoji, title, sub, tint, pop, key) => (
    <button className="tap pbtn" onClick={() => onPick(key)} style={{
      display: 'flex', alignItems: 'center', gap: 12, width: '100%', border: 'none', cursor: 'pointer',
      background: 'none', padding: '12px 13px', textAlign: 'left', borderRadius: 13
    }}>
      <span style={{ width: 42, height: 42, flexShrink: 0, borderRadius: 13, backgroundColor: tint, color: pop, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 20 }}>{emoji}</span>
      <span style={{ minWidth: 0 }}>
        <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 600, fontSize: 14, color: 'var(--p-ink)', letterSpacing: '0.005em' }}>{title}</span>
        <span style={{ display: 'block', fontSize: 11.5, fontWeight: 500, color: 'var(--p-mute)', marginTop: 2 }}>{sub}</span>
      </span>
    </button>);

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" style={{ background: 'rgba(20,14,30,0.18)', backdropFilter: 'none', WebkitBackdropFilter: 'none' }} onClick={onClose} />
      {pos &&
        <div className="pcreatepop" style={{
          position: 'absolute', top: pos.top, left: pos.left, zIndex: 45, width: 266,
          background: 'var(--p-elev)', borderRadius: 18, padding: 6,
          border: '1px solid var(--p-border)', boxShadow: '0 16px 40px -12px rgba(20,14,30,0.45)'
        }}>
          {opt('📝', 'Запись в ленту', 'Текст, фото, аудио или видео', TINT.coral, POP.coral, 'post')}
          <div style={{ height: 1, background: 'var(--p-border)', margin: '2px 8px' }} />
          {opt('🧘', 'Занятие', 'Эфир, запись или очно в студии', TINT.sky, POP.sky, 'class')}
        </div>}
    </React.Fragment>, host);
}

/* ── форма: новая запись в ленту ────────────────────────────────── */
function PFeedComposer({ host, onClose, onPublish }) {
  const [text, setText] = uSp('');
  const [media, setMedia] = uSp(null); // null | 'photo' | 'audio' | 'video'
  const [cover, setCover] = uSp('morning');
  const [audioTitle, setAudioTitle] = uSp('');
  const [dur, setDur] = uSp('');
  const [tagStr, setTagStr] = uSp('');
  if (!host) return null;
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 4px' };
  const canPost = !!(text.trim() || media);
  const MEDIA = [[null, '📝', 'Текст'], ['photo', '📷', 'Фото'], ['audio', '🎧', 'Аудио'], ['video', '🎬', 'Видео']];

  const submit = () => {
    if (!canPost) return;
    const tags = tagStr.split(/[,#\s]+/).map((s) => s.trim()).filter(Boolean);
    const post = { id: 'u' + Date.now(), type: (media === 'photo' || media === 'video') ? 'photo' : 'text',
      media: media ? [media] : [], tags, time: 'только что', text: text.trim(), likes: 0, liked: false, comments: [] };
    if (media === 'photo' || media === 'video') post.image = cover;
    if (media === 'video') post.video = { dur: dur.trim() || '0:30' };
    if (media === 'audio') post.audio = { title: audioTitle.trim() || 'Аудио-запись', dur: dur.trim() || '00:00' };
    onPublish(post);
  };

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 24, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={grabber} />
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '2px 18px 12px', borderBottom: '1px solid var(--p-border)' }}>
            <button className="tap" onClick={onClose} style={{ border: 'none', background: 'none', cursor: 'pointer', fontFamily: "'Nunito Sans', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-mute)', padding: 0 }}>Отмена</button>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, color: 'var(--p-ink)' }}>Новая запись</span>
            <button className="pbtn" onClick={submit} disabled={!canPost} style={{ border: 'none', cursor: canPost ? 'pointer' : 'default', opacity: canPost ? 1 : 0.4, borderRadius: 999, padding: '8px 16px', backgroundColor: BTN.primary, color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13.5 }}>Опубликовать</button>
          </div>

          <div style={{ maxHeight: 440, overflowY: 'auto', scrollbarWidth: 'none', padding: '16px 18px 20px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
              <PAvatar size={38} ring={false} />
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14, color: 'var(--p-ink)' }}>{PROFILE.name}</div>
            </div>

            <textarea autoFocus value={text} onChange={(e) => setText(e.target.value)} rows={3} placeholder="Чем поделитесь?" style={{
              width: '100%', boxSizing: 'border-box', border: 'none', background: 'none', resize: 'none', outline: 'none',
              fontFamily: "'Nunito Sans', sans-serif", fontSize: 16, lineHeight: 1.5, color: 'var(--p-ink)'
            }} />

            <div style={{ marginTop: 8 }}>
              <span style={cLabel}>Вложение</span>
              <div style={{ display: 'flex', gap: 8 }}>
                {MEDIA.map(([k, e, name]) => {
                  const on = media === k;
                  return (
                    <button key={name} className="tap pbtn" onClick={() => setMedia(k)} style={{
                      flex: 1, display: 'inline-flex', flexDirection: 'column', alignItems: 'center', gap: 4, border: 'none', cursor: 'pointer',
                      padding: '11px 4px', borderRadius: 14, backgroundColor: on ? CTRL.accentSoft : CTRL.surface, color: on ? CTRL.accent : 'var(--p-mute)',
                      fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 11.5, transition: 'all .14s ease',
                      boxShadow: on ? 'inset 0 0 0 1.5px var(--ctrl-accent)' : 'none'
                    }}><span style={{ fontSize: 19 }}>{e}</span>{name}</button>);
                })}
              </div>
            </div>

            {(media === 'photo' || media === 'video') &&
              <div style={{ marginTop: 16 }}>
                <span style={cLabel}>Обложка</span>
                <PCoverPicker value={cover} onChange={setCover} />
              </div>}
            {media === 'video' &&
              <div style={{ marginTop: 14 }}>
                <span style={cLabel}>Длительность</span>
                <input value={dur} onChange={(e) => setDur(e.target.value)} placeholder="8:45" style={cInput} />
              </div>}
            {media === 'audio' &&
              <div style={{ marginTop: 16, display: 'flex', flexDirection: 'column', gap: 12 }}>
                <div><span style={cLabel}>Название аудио</span>
                  <input value={audioTitle} onChange={(e) => setAudioTitle(e.target.value)} placeholder="Медитация перед сном" style={cInput} /></div>
                <div><span style={cLabel}>Длительность</span>
                  <input value={dur} onChange={(e) => setDur(e.target.value)} placeholder="14:20" style={cInput} /></div>
              </div>}

            <div style={{ marginTop: 16 }}>
              <span style={cLabel}>Хэштеги</span>
              <input value={tagStr} onChange={(e) => setTagStr(e.target.value)} placeholder="осознанность, дыхание" style={cInput} />
            </div>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

/* ── форма: новое занятие ───────────────────────────────────────── */
function PClassComposer({ host, onClose, onCreate }) {
  const [title, setTitle] = uSp('');
  const [desc, setDesc] = uSp('');
  const [format, setFormat] = uSp('video');     // video | audio | studio
  const [status, setStatus] = uSp('upcoming');  // upcoming | recording
  const [dur, setDur] = uSp('');
  const [when, setWhen] = uSp('');
  const [level, setLevel] = uSp('Любой');
  const [free, setFree] = uSp(false);
  const [price, setPrice] = uSp('');
  const [tone, setTone] = uSp('a');
  const [cover, setCover] = uSp('mat');
  const [venue, setVenue] = uSp('');
  const [teachers, setTeachers] = uSp([]);
  const [announce, setAnnounce] = uSp(true);
  if (!host) return null;

  const studio = format === 'studio';
  const grabber = { width: 38, height: 5, borderRadius: 999, background: 'var(--p-border)', margin: '9px auto 4px' };
  const canCreate = !!title.trim();
  const coTeachers = (window.TEACHERS || []).filter((t) => t.id !== 'self');
  const [tTint, tPop] = PR_TINT[tone] || PR_TINT.a;
  const TONES = [['a', POP.coral], ['b', POP.sky], ['c', POP.mint]];

  const submit = () => {
    if (!canCreate) return;
    const st = studio ? 'upcoming' : status;
    const p = { id: 'pr' + Date.now(), title: title.trim(), kind: format === 'audio' ? 'audio' : 'video',
      dur: dur.trim() || '30 мин', level, tone, status: st, image: cover };
    if (studio) p.place = 'studio';
    if (when.trim()) p.when = when.trim();
    if (st === 'upcoming' && !studio) p.live = true;
    if (free) p.free = true; else if (price.trim()) p.price = price.trim();
    if (teachers.length) p.teachers = teachers;
    if (desc.trim()) p.desc = desc.trim();
    if (studio) p.venue = { studio: venue.trim() || 'Студия', spots: 8 };
    onCreate(p, announce);
  };

  return ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>
        <div style={{ background: 'var(--p-elev)', borderRadius: 24, overflow: 'hidden', boxShadow: '0 -12px 44px -14px rgba(20,14,30,0.45)' }}>
          <div style={grabber} />
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '2px 18px 12px', borderBottom: '1px solid var(--p-border)' }}>
            <button className="tap" onClick={onClose} style={{ border: 'none', background: 'none', cursor: 'pointer', fontFamily: "'Nunito Sans', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-mute)', padding: 0 }}>Отмена</button>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, color: 'var(--p-ink)' }}>Новое занятие</span>
            <button className="pbtn" onClick={submit} disabled={!canCreate} style={{ border: 'none', cursor: canCreate ? 'pointer' : 'default', opacity: canCreate ? 1 : 0.4, borderRadius: 999, padding: '8px 16px', backgroundColor: BTN.primary, color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13.5 }}>Создать</button>
          </div>

          <div style={{ maxHeight: 460, overflowY: 'auto', scrollbarWidth: 'none', padding: '16px 18px 22px', display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div>
              <span style={cLabel}>Название</span>
              <input autoFocus value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Утренняя виньяса" style={cInput} />
            </div>

            <div>
              <span style={cLabel}>Формат</span>
              <PSeg value={format} onChange={setFormat} opts={[['video', '🎬 Видео'], ['audio', '🎧 Аудио'], ['studio', '📍 Очно']]} />
            </div>

            {!studio &&
              <div>
                <span style={cLabel}>Тип</span>
                <PSeg value={status} onChange={setStatus} opts={[['upcoming', '📅 Эфир'], ['recording', '🎞 Запись']]} />
              </div>}

            <div style={{ display: 'flex', gap: 12 }}>
              <div style={{ flex: 1 }}>
                <span style={cLabel}>Длительность</span>
                <input value={dur} onChange={(e) => setDur(e.target.value)} placeholder="60 мин" style={cInput} />
              </div>
              <div style={{ flex: 1 }}>
                <span style={cLabel}>{(studio || status === 'upcoming') ? 'Когда' : 'Дата'}</span>
                <input value={when} onChange={(e) => setWhen(e.target.value)} placeholder={status === 'upcoming' || studio ? 'Сб · 10:00' : '2 дня назад'} style={cInput} />
              </div>
            </div>

            {studio &&
              <div>
                <span style={cLabel}>Студия</span>
                <input value={venue} onChange={(e) => setVenue(e.target.value)} placeholder="Лофт «Лотос»" style={cInput} />
              </div>}

            <div>
              <span style={cLabel}>Уровень</span>
              <div style={{ display: 'flex', gap: 8 }}>
                {['Начало', 'Любой', 'Продвинуто'].map((lv) => {
                  const on = level === lv;
                  return (
                    <button key={lv} className="tap pbtn" onClick={() => setLevel(lv)} style={{
                      flex: 1, height: 42, border: 'none', cursor: 'pointer', borderRadius: 13,
                      backgroundColor: on ? CTRL.accentSoft : CTRL.surface, color: on ? CTRL.accent : 'var(--p-mute)',
                      fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12.5,
                      boxShadow: on ? 'inset 0 0 0 1.5px var(--ctrl-accent)' : 'none', transition: 'all .14s ease'
                    }}>{lv}</button>);
                })}
              </div>
            </div>

            <div>
              <span style={cLabel}>Доступ</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <button className="tap pbtn" onClick={() => setFree((v) => !v)} style={{
                  display: 'inline-flex', alignItems: 'center', gap: 7, border: 'none', cursor: 'pointer',
                  padding: '11px 15px', borderRadius: 13, backgroundColor: free ? TINT.leaf : CTRL.surface, color: free ? POP.leaf : 'var(--p-mute)',
                  fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5, whiteSpace: 'nowrap',
                  boxShadow: free ? 'inset 0 0 0 1.5px ' + POP.leaf : 'none'
                }}>{free ? '✓ ' : ''}🎁 Бесплатно</button>
                <input value={price} disabled={free} onChange={(e) => setPrice(e.target.value)} placeholder="1 900 ₽" style={{ ...cInput, flex: 1, opacity: free ? 0.4 : 1 }} />
              </div>
            </div>

            <div>
              <span style={cLabel}>Цвет</span>
              <div style={{ display: 'flex', gap: 10 }}>
                {TONES.map(([v, c]) => {
                  const on = tone === v;
                  return (
                    <button key={v} className="tap pbtn" onClick={() => setTone(v)} aria-label={'Цвет ' + v} style={{
                      width: 38, height: 38, borderRadius: '50%', cursor: 'pointer', backgroundColor: c,
                      border: on ? '3px solid var(--p-elev)' : '3px solid transparent',
                      boxShadow: on ? '0 0 0 2px ' + c : 'none'
                    }} />);
                })}
              </div>
            </div>

            <div>
              <span style={cLabel}>Обложка</span>
              <PCoverPicker value={cover} onChange={setCover} />
            </div>

            <div>
              <span style={cLabel}>Соведущие</span>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                {coTeachers.map((t) => {
                  const on = teachers.includes(t.id);
                  return (
                    <button key={t.id} className="tap pbtn" onClick={() => setTeachers((s) => s.includes(t.id) ? s.filter((x) => x !== t.id) : [...s, t.id])} style={{
                      display: 'inline-flex', alignItems: 'center', gap: 7, border: 'none', cursor: 'pointer',
                      padding: '7px 13px 7px 8px', borderRadius: 999,
                      backgroundColor: on ? tPop : CTRL.surface, color: on ? '#fff' : 'var(--p-soft)',
                      fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12.5, transition: 'all .14s ease'
                    }}>
                      <span style={{ width: 24, height: 24, borderRadius: '50%', flexShrink: 0, overflow: 'hidden', background: 'var(--t-coral)', display: 'inline-flex' }}><PImg src={teacherPhoto(t)} /></span>
                      {t.spiritual}<span style={{ marginLeft: 1 }}>{on ? '✓' : '+'}</span>
                    </button>);
                })}
              </div>
              <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-faint)', marginTop: 8 }}>Если никого не отметить — ведёте вы.</div>
            </div>

            <div>
              <span style={cLabel}>Описание</span>
              <textarea value={desc} onChange={(e) => setDesc(e.target.value)} rows={3} placeholder="О чём это занятие…" style={{ ...cInput, height: 'auto', padding: '12px 14px', lineHeight: 1.5, resize: 'none', fontSize: 14 }} />
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '13px 14px', borderRadius: 14, backgroundColor: CTRL.surface }}>
              <span style={{ fontSize: 18 }}>📣</span>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5, color: 'var(--p-ink)' }}>Анонс в ленте</div>
                <div style={{ fontSize: 11.5, fontWeight: 600, color: 'var(--p-mute)' }}>Опубликовать карточку занятия в Ленте</div>
              </div>
              <PSwitch on={announce} onChange={setAnnounce} />
            </div>
          </div>
        </div>
      </div>
    </React.Fragment>, host);
}

function PlayfulProfile({ dark = false, nav }) {
  const [feed, setFeed] = uSp(() => JSON.parse(JSON.stringify(FEED)));
  const [tab, setTab] = uSp('feed');
  const [activeTag, setActiveTag] = uSp(null);
  const [types, setTypes] = uSp([]); // ['text'|'photo'|'audio'|'video']
  const [comments, setComments] = uSp('all'); // 'all'|'with'|'without'
  const [sheet, setSheet] = uSp(false);
  const [menu, setMenu] = uSp(false);
  const [practices, setPractices] = uSp(() => JSON.parse(JSON.stringify(PRACTICES)));
  const [create, setCreate] = uSp(null); // null | 'menu' | 'post' | 'class'
  const rootRef = React.useRef(null);
  const createRef = React.useRef(null);

  const practiceById = React.useMemo(() => Object.fromEntries(practices.map((p) => [p.id, p])), [practices]);

  /* сетка → лента: сбрасываем фильтры, открываем Ленту и подсвечиваем запись */
  const [focusId, setFocusId] = uSp(null);
  const openPost = (id) => { setActiveTag(null); setTypes([]); setComments('all'); setTab('feed'); setFocusId(id); };
  React.useEffect(() => {
    if (!focusId) return;
    const t = setTimeout(() => {
      const root = rootRef.current;
      const sc = root && root.querySelector('.pscroll');
      const el = root && root.querySelector('[data-post-id="' + focusId + '"]');
      if (sc && el) sc.scrollTop += el.getBoundingClientRect().top - sc.getBoundingClientRect().top - 8;
    }, 60);
    const clear = setTimeout(() => setFocusId(null), 1500);
    return () => { clearTimeout(t); clearTimeout(clear); };
  }, [focusId]);

  const like = (id) => setFeed((f) => f.map((p) => p.id === id ? { ...p, liked: !p.liked, likes: p.likes + (p.liked ? -1 : 1) } : p));
  const removePost = (id) => setFeed((f) => f.filter((p) => p.id !== id));
  const editPost = (id, text) => setFeed((f) => f.map((p) => p.id === id ? { ...p, text } : p));
  const addPost = (post) => { setFeed((f) => [post, ...f]); setCreate(null); setTab('feed'); };
  const addClass = (p, announce) => {
    setPractices((xs) => [p, ...xs]);
    if (announce) setFeed((f) => [{ id: 'a' + Date.now(), type: 'class', practiceId: p.id, tags: ['занятие'], time: 'только что', text: '✨ Новое занятие — ' + p.title + (p.free ? '. Вход свободный 🙏' : ''), likes: 0, liked: false, comments: [] }, ...f]);
    setCreate(null);
    setTab(announce ? 'feed' : 'practices');
  };

  const matches = (p) => {
    if (activeTag && !(p.tags || []).includes(activeTag)) return false;
    if (p.type === 'class') {
      // анонс занятия: прячем только когда выбран фильтр по типу контента
      if (types.length) return false;
    } else if (types.length) {
      const has = (t) => t === 'text' ? !!p.text : (p.media || []).includes(t);
      if (!types.every(has)) return false;
    }
    if (comments === 'with' && p.comments.length === 0) return false;
    if (comments === 'without' && p.comments.length > 0) return false;
    return true;
  };
  const filtered = feed.filter(matches);

  const hasSheetFilter = types.length > 0 || comments !== 'all';
  const anyFilter = hasSheetFilter || activeTag !== null;
  const resetAll = () => { setActiveTag(null); setTypes([]); setComments('all'); };
  const host = rootRef.current && rootRef.current.classList.contains('ylp') ? rootRef.current : null;

  return (
    <div ref={rootRef} className={'ylp' + (dark ? ' dark' : '')}>
      <PTopBar onMenu={() => setMenu(true)} />
      <div className="pscroll">
        <PHeader nav={nav} />
        <PTabs tab={tab} setTab={setTab} onCreate={() => setCreate((c) => c === 'menu' ? null : 'menu')} createRef={createRef} createOpen={create === 'menu'} />
        <div style={{ paddingBottom: 96 }}>
          {tab === 'feed' &&
          <React.Fragment>
              <PFeedFilter
              activeTag={activeTag} setActiveTag={setActiveTag}
              openSheet={() => setSheet(true)} anyFilter={anyFilter}
              resetAll={resetAll} hasSheetFilter={hasSheetFilter} />
              {filtered.map((p) => {
                const card = p.type === 'class' ?
                (practiceById[p.practiceId] ? <PClassAnnounceCard post={p} practice={practiceById[p.practiceId]} onLike={like} onTag={(t) => setActiveTag((cur) => cur === t ? null : t)} activeTag={activeTag} onDelete={removePost} onEdit={editPost} /> : null) :
                <PPostCard post={p} onLike={like} onTag={(t) => setActiveTag((cur) => cur === t ? null : t)} activeTag={activeTag} />;
                if (!card) return null;
                const hot = focusId === p.id;
                return (
                  <div key={p.id} data-post-id={p.id} style={{
                    position: 'relative', transition: 'background-color .5s ease',
                    backgroundColor: hot ? 'var(--t-sun)' : 'transparent'
                  }}>{card}</div>);

              })}
              {filtered.length === 0 &&
              <div style={{ padding: '38px 28px 8px', textAlign: 'center' }}>
                  <div style={{ fontSize: 44 }}>🍃</div>
                  <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 17, color: 'var(--p-ink)', marginTop: 10 }}>Ничего не найдено</div>
                  <div style={{ fontSize: 13.5, color: 'var(--p-mute)', marginTop: 6, lineHeight: 1.5 }}>Попробуйте изменить фильтры или хэштег.</div>
                  <button className="tap" onClick={resetAll} style={{ marginTop: 16, border: 'none', cursor: 'pointer', padding: '10px 20px', borderRadius: 13, backgroundColor: CTRL.surface, color: CTRL.accent, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14 }}>Сбросить фильтры</button>
                </div>
              }
            </React.Fragment>
          }
          {tab === 'grid' && <PGrid feed={feed} practiceById={practiceById} onOpen={openPost} />}
          {tab === 'practices' && <PPractices items={practices} setItems={setPractices} />}
          {tab === 'saved' && <PPublic items={practices} setItems={setPractices} />}
        </div>
      </div>
      <PBottomNav {...nav} />
      {sheet &&
      <PFilterSheet
        host={host} types={types} setTypes={setTypes}
        comments={comments} setComments={setComments}
        onClose={() => setSheet(false)} onReset={resetAll} count={filtered.length} />
      }
      {create === 'menu' && host &&
      <PCreateMenu host={host} anchorRef={createRef} onClose={() => setCreate(null)} onPick={(k) => setCreate(k)} />
      }
      {create === 'post' && host &&
      <PFeedComposer host={host} onClose={() => setCreate(null)} onPublish={addPost} />
      }
      {create === 'class' && host &&
      <PClassComposer host={host} onClose={() => setCreate(null)} onCreate={addClass} />
      }
      {menu && host &&
      <PSideMenu host={host} nav={nav} onClose={() => setMenu(false)} />
      }
    </div>);

}

Object.assign(window, { PlayfulProfile, PTopBar, PHeader });
/* примитивы для страницы «чужой профиль» (other-profile.jsx) */
Object.assign(window, {
  PTabs, PFeedFilter, PFilterSheet, PPostCard, PClassAnnounceCard,
  PGrid, PPractices, PPublic, PActions, PSelfActions, PHilite, PStat, PChip, HILITES, PBottomNav,
  PAuthorCtx, SELF_AUTHOR,
});
/* реиспользуемые примитивы для страницы Сангат */
Object.assign(window, {
  PImg, PPhoto, PAudio, PVideo, PAvatar, PCommentSheet, PCommentRow, PCommentAvatar,
  POP, TINT, CTRL, BTN, GOLD_INK, REAL_IMG, PHOTO_MAP, avaFor, AVA_SELF, AVA_POOL, UA, U,
  teacherPhoto, TEACHER_PHOTO,
});