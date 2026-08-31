// apps.jsx — экран-лаунчер «Приложения»: плитки сервисов Yoga Loka.
// Пока одно приложение — «Построение практики». Список вынесен в APPS,
// чтобы легко добавлять новые. Экспорт в window: AppsScreen.

const { useState: uA } = React;

/* ── каталог приложений ────────────────────────────────────────── */
const APPS = [
  {
    id: 'builder',
    title: 'Моя практика',
    desc: 'Собранные крийи, последовательности асан и практики для себя — с удобным контролем времени и сопровождением',
    eyebrow: 'Йога-конструктор',
    // фирменный градиент иконки (виноград → коралл — два основных action-цвета)
    iconGrad: 'linear-gradient(135deg, var(--c-grape), var(--c-coral))',
    iconShadow: 'rgba(155,93,229,0.55)',
    // реальное фото на обложку (градиент остаётся фолбэком-подложкой)
    cover: 'linear-gradient(135deg, var(--t-grape), var(--t-coral))',
    coverImg: 'https://images.unsplash.com/photo-1620121692029-d088224ddc74?q=80&auto=format&fit=crop&w=900&h=420',
  },
];

/* ── глиф «строительные блоки» — метафора построения ───────────── */
function BuildGlyph({ size = 36 }) {
  return (
    <svg width={size} height={size} viewBox="0 0 32 32" fill="none">
      <rect x="4.5" y="17.5" width="10" height="10" rx="3.2" fill="#fff" />
      <rect x="17.5" y="17.5" width="10" height="10" rx="3.2" fill="#fff" fillOpacity="0.82" />
      <rect x="11" y="4.5" width="10" height="10" rx="3.2" fill="#fff" />
    </svg>
  );
}

/* ── иконка приложения (градиентный скруглённый квадрат) ────────── */
function AppIcon({ app, size = 66, radius = 19 }) {
  return (
    <div style={{
      width: size, height: size, borderRadius: radius, flexShrink: 0,
      background: app.iconGrad, position: 'relative',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      boxShadow: `0 14px 26px -12px ${app.iconShadow}, inset 0 1.5px 0 rgba(255,255,255,0.4)`,
    }}>
      <BuildGlyph size={size * 0.55} />
      {/* мягкий блик сверху */}
      <div style={{
        position: 'absolute', inset: 0, borderRadius: radius, pointerEvents: 'none',
        background: 'linear-gradient(180deg, rgba(255,255,255,0.22), transparent 55%)',
      }} />
    </div>
  );
}

/* ── крупная «фичевая» карточка приложения ─────────────────────── */
function FeaturedApp({ app, onOpen }) {
  return (
    <div className="tap pbtn" onClick={() => onOpen(app)} style={{
      cursor: 'pointer', borderRadius: 26, overflow: 'hidden',
      background: 'var(--p-elev)', border: '1px solid var(--p-border)',
      boxShadow: '0 24px 46px -28px rgba(40,20,60,0.6)',
    }}>
      {/* обложка-баннер: солнечная сцена с йогом */}
      <div style={{ position: 'relative', height: 156, overflow: 'hidden' }}>
        <window.SunYogiScene />
      </div>

      {/* тело: заголовок + текст */}
      <div style={{ padding: '15px 18px 18px', position: 'relative' }}>
        <h2 style={{ margin: 0, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 19, letterSpacing: '-0.01em', color: 'var(--p-ink)', lineHeight: 1.14 }}>{app.title}</h2>

        <p style={{ margin: '9px 0 0', fontSize: 14, fontWeight: 600, lineHeight: 1.5, color: 'var(--p-soft)', textWrap: 'pretty' }}>{app.desc}</p>

        {/* CTA */}
        <button className="pbtn" onClick={(ev) => { ev.stopPropagation(); onOpen(app); }} style={{
          marginTop: 18, width: '100%', height: 50, border: 'none', cursor: 'pointer',
          borderRadius: 16, background: 'var(--c-coral)', color: '#fff',
          fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15.5,
          display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 8,
          boxShadow: '0 16px 30px -14px rgba(255,111,97,0.95)',
        }}>Открыть <IconChevron size={17} style={{ marginTop: 1 }} /></button>
      </div>
    </div>
  );
}

/* ── главный экран ─────────────────────────────────────────────── */
/* ── нижняя навигация (единая для всех экранов) ──────────── */
const APPS_NAV = [
  { key: 'practice', label: 'Моя практика', emoji: '🌀', pop: '#E8615A', soft: 'rgba(232,97,90,0.16)' },
  { key: 'classes', label: 'Занятия', emoji: '🧘', pop: '#E8902F', soft: 'rgba(232,144,47,0.16)' },
  { key: 'calendar', label: 'Календарь', emoji: '📅', pop: '#4FA85B', soft: 'rgba(79,168,91,0.16)' },
  { key: 'sangat', label: 'Сангат', emoji: '❤️', pop: '#3E92D8', soft: 'rgba(62,146,216,0.16)' },
  { key: 'ahamkara', label: 'Ахамкара', emoji: '🪬', pop: '#8E55D8', soft: 'rgba(142,85,216,0.16)' },
];
function AppsBottomNav() {
  const [active, setActive] = uA('practice');
  return (
    <div style={{ position: 'absolute', bottom: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderTop: '1px solid var(--p-border)', display: 'flex', alignItems: 'stretch', justifyContent: 'space-around', padding: '8px 4px 24px' }}>
      {APPS_NAV.map(({ key, label, emoji, pop, soft }) => {
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

/* ── главный экран ────────────────────────────── */
function AppsScreen({ dark = false }) {
  const [toast, setToast] = uA(null);
  const [view, setView] = uA('home');

  const open = (app) => {
    if (app.id === 'builder') { setView('builder'); return; }
    setToast(`Открываем «${app.title}»`);
    clearTimeout(open._t);
    open._t = setTimeout(() => setToast(null), 1900);
  };

  if (view === 'builder') return <BuilderScreen dark={dark} onBack={() => setView('home')} />;

  return (
    <div className={'ylp' + (dark ? ' dark' : '')} style={{ height: '100%' }}>
      {/* top bar — как в Календаре: зафиксирована, стекло + размытие + граница */}
      <div style={{ position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6, backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)', padding: '48px 14px 11px', display: 'flex', alignItems: 'center', gap: 8 }}>
        <span style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 7, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 20, color: 'var(--p-ink)' }}>🌀 Приложения</span>
      </div>

      <div className="pscroll" style={{ paddingBottom: 96 }}>
        {/* распорка под фиксированную шапку */}
        <div style={{ height: 96 }} />

        {/* список приложений */}
        <div style={{ padding: '16px 14px 0', display: 'flex', flexDirection: 'column', gap: 14 }}>
          {APPS.map((app) => <FeaturedApp key={app.id} app={app} onOpen={open} />)}
        </div>
      </div>

      <AppsBottomNav />

      {/* тост */}
      {toast && (
        <div className="ptoast" style={{
          position: 'absolute', bottom: 38, left: '50%', transform: 'translateX(-50%)', zIndex: 60,
          padding: '12px 18px', borderRadius: 16, maxWidth: '80%',
          background: 'var(--p-ink)', color: 'var(--p-bg)',
          fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5, whiteSpace: 'nowrap',
          boxShadow: '0 18px 34px -16px rgba(0,0,0,0.6)',
        }}>{toast}</div>
      )}
    </div>
  );
}

Object.assign(window, { AppsScreen, AppsBottomNav });
