// unified-app.jsx — единое приложение Yoga Loka.
// Один телефон, нижнее меню реально переключает 5 экранов:
// Моя практика · Занятия · Календарь · Сангат · Ахамкара.
const { useState: uUA } = React;

const UA_SCREENS = {
  practice: (dark, nav) => <MyPracticeScreen dark={dark} nav={nav} />,
  classes:  (dark, nav) => <ClassesScreen dark={dark} nav={nav} />,
  calendar: (dark, nav) => <CalendarPage dark={dark} nav={nav} />,
  sangat:   (dark, nav) => <SangatFeed dark={dark} nav={nav} />,
  ahamkara: (dark, nav) => <PlayfulProfile dark={dark} nav={nav} />,
  other:    (dark, nav) => <OtherProfile dark={dark} nav={nav} />,
};

function UnifiedApp() {
  const [dark, setDark] = uUA(false);
  const [active, setActive] = uUA('practice');
  const nav = { active, onNav: setActive };

  const segBtn = (on) => ({
    border: 'none', cursor: 'pointer', padding: '6px 14px', borderRadius: 999,
    fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13,
    display: 'inline-flex', alignItems: 'center', gap: 6,
    background: on ? '#fff' : 'transparent',
    color: on ? '#2a2330' : '#8b8398',
    boxShadow: on ? '0 3px 10px -5px rgba(20,20,40,0.4)' : 'none',
    transition: 'all .15s ease',
  });

  return (
    <div style={{
      minHeight: '100vh', padding: '40px 40px 70px', boxSizing: 'border-box',
      background: dark
        ? 'radial-gradient(120% 90% at 50% 0%, #1c1826 0%, #100d16 100%)'
        : 'radial-gradient(120% 90% at 50% 0%, #f6f4f9 0%, #eceaf0 100%)',
      display: 'flex', flexDirection: 'column', justifyContent: 'flex-start', alignItems: 'center',
      transition: 'background .25s ease',
    }}>
      {/* переключатель темы */}
      <div style={{
        display: 'inline-flex', gap: 4, padding: 4, borderRadius: 999, marginBottom: 22,
        background: dark ? 'rgba(255,255,255,0.08)' : 'rgba(20,20,40,0.06)',
      }}>
        <button style={segBtn(!dark)} onClick={() => setDark(false)}>☀️ Светлая</button>
        <button style={segBtn(dark)} onClick={() => setDark(true)}>🌙 Тёмная</button>
      </div>

      {/* профиль: свой ↔ чужой (служебный переключатель для дизайна) */}
      <div style={{
        display: 'inline-flex', gap: 4, padding: 4, borderRadius: 999, marginBottom: 22,
        background: dark ? 'rgba(255,255,255,0.08)' : 'rgba(20,20,40,0.06)',
      }}>
        <button style={segBtn(active === 'ahamkara')} onClick={() => setActive('ahamkara')}>🪬 Свой профиль</button>
        <button style={segBtn(active === 'other')} onClick={() => setActive('other')}>👤 Чужой профиль</button>
      </div>

      <IOSDevice width={390} height={844} dark={dark}>
        {UA_SCREENS[active](dark, nav)}
      </IOSDevice>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<UnifiedApp />);
