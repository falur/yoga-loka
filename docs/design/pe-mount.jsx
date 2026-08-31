// pe-mount.jsx — телефон «Цветной» + переключатель светлой/тёмной темы
const { useState: uM } = React;

function PEMount() {
  const [dark, setDark] = uM(false);
  const segBtn = (on) => ({
    border: 'none', cursor: 'pointer', padding: '6px 14px', borderRadius: 999,
    fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13,
    display: 'inline-flex', alignItems: 'center', gap: 6,
    background: on ? '#fff' : 'transparent', color: on ? '#2a2330' : '#8b8398',
    boxShadow: on ? '0 3px 10px -5px rgba(20,20,40,0.4)' : 'none', transition: 'all .15s ease',
  });
  return (
    <div style={{
      minHeight: '100vh', padding: '40px 40px 70px', boxSizing: 'border-box',
      background: dark ? 'radial-gradient(120% 90% at 50% 0%, #1c1826 0%, #100d16 100%)'
                       : 'radial-gradient(120% 90% at 50% 0%, #f6f4f9 0%, #eceaf0 100%)',
      display: 'flex', flexDirection: 'column', alignItems: 'center', transition: 'background .25s ease',
    }}>
      <div style={{ display: 'inline-flex', gap: 4, padding: 4, borderRadius: 999, marginBottom: 22, background: dark ? 'rgba(255,255,255,0.08)' : 'rgba(20,20,40,0.06)' }}>
        <button style={segBtn(!dark)} onClick={() => setDark(false)}>☀️ Светлая</button>
        <button style={segBtn(dark)} onClick={() => setDark(true)}>🌙 Тёмная</button>
      </div>
      <IOSDevice width={390} height={844} dark={dark}>
        <PracticeEditorScreen dark={dark} onBack={() => {}} />
      </IOSDevice>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<PEMount />);
