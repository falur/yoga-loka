// app.jsx — галерея «3 телефона в ряд» + Tweaks + применение токенов (минимал)
const { useState: uS, useEffect: uE } = React;

const THEMES = {
  light: {
    '--bg': '#ffffff', '--bg-2': '#f1f1f4',
    '--ink': '#18181c', '--ink-soft': '#6b6b75', '--ink-faint': '#9a9aa6',
    '--hairline': 'rgba(20,20,30,0.10)', '--hairline-2': 'rgba(20,20,30,0.06)',
    '--glass-bg': 'rgba(255,255,255,0.72)', '--glass-bd': 'rgba(20,20,30,0.07)',
    '--on-accent': '#ffffff',
  },
  dark: {
    '--bg': '#0d0d11', '--bg-2': '#1b1b22',
    '--ink': '#f3f3f6', '--ink-soft': '#a2a2ad', '--ink-faint': '#74747f',
    '--hairline': 'rgba(255,255,255,0.13)', '--hairline-2': 'rgba(255,255,255,0.07)',
    '--glass-bg': 'rgba(22,22,28,0.62)', '--glass-bd': 'rgba(255,255,255,0.10)',
    '--on-accent': '#ffffff',
  },
};

const ACCENTS = {
  indigo:  { label: 'Индиго',  light: 'oklch(0.53 0.19 280)', dark: 'oklch(0.68 0.17 282)' },
  azure:   { label: 'Лазурь',  light: 'oklch(0.58 0.16 250)', dark: 'oklch(0.70 0.14 248)' },
  emerald: { label: 'Изумруд', light: 'oklch(0.56 0.14 168)', dark: 'oklch(0.70 0.13 168)' },
  coral:   { label: 'Коралл',  light: 'oklch(0.62 0.17 30)',  dark: 'oklch(0.72 0.15 32)'  },
};

const FONTS = {
  'Sora · Manrope': ["'Sora', sans-serif", "'Manrope', sans-serif"],
  'Outfit': ["'Outfit', sans-serif", "'Outfit', sans-serif"],
  'Quicksand · Nunito': ["'Quicksand', sans-serif", "'Nunito Sans', sans-serif"],
  'Space Grotesk · Manrope': ["'Space Grotesk', sans-serif", "'Manrope', sans-serif"],
};

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "light",
  "accent": "indigo",
  "fonts": "Sora · Manrope",
  "glassBlur": 18,
  "radius": 14,
  "density": "regular"
}/*EDITMODE-END*/;

function applyTokens(t) {
  const r = document.documentElement.style;
  const theme = THEMES[t.theme] || THEMES.light;
  Object.entries(theme).forEach(([k, v]) => r.setProperty(k, v));
  const acc = (ACCENTS[t.accent] || ACCENTS.indigo)[t.theme] || (ACCENTS[t.accent] || ACCENTS.indigo).light;
  r.setProperty('--accent', acc);
  r.setProperty('--accent-soft', `color-mix(in oklch, ${acc} 14%, transparent)`);
  r.setProperty('--glass-blur', t.glassBlur + 'px');
  r.setProperty('--r-lg', (t.radius + 8) + 'px');
  r.setProperty('--r-md', t.radius + 'px');
  r.setProperty('--r-sm', Math.max(6, t.radius - 4) + 'px');
  const dmap = { compact: [0.82, 0.96], regular: [1, 1], comfy: [1.2, 1.05] };
  const [sp, fs] = dmap[t.density] || dmap.regular;
  r.setProperty('--sp', sp); r.setProperty('--fontScale', fs);
  const f = FONTS[t.fonts] || FONTS['Sora · Manrope'];
  r.setProperty('--font-display', f[0]); r.setProperty('--font-body', f[1]);
}

/* ── one labelled phone ────────────────────────────────────────── */
function Frame({ tag, name, sub, variant, dark }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14, flex: '0 0 auto' }}>
      <div style={{ paddingLeft: 4 }}>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 9 }}>
          <span style={{ fontFamily: "'Sora', sans-serif", fontWeight: 700, fontSize: 15, color: dark ? '#e8e8ef' : '#26243a' }}>{tag}</span>
          <span style={{ fontFamily: "'Sora', sans-serif", fontWeight: 600, fontSize: 15, color: dark ? '#a6a6b8' : '#6b6390' }}>{name}</span>
        </div>
        <div style={{ fontFamily: "'Manrope', sans-serif", fontSize: 12.5, color: dark ? '#8a8a9c' : '#8a83ad', marginTop: 2 }}>{sub}</div>
      </div>
      <IOSDevice width={390} height={844} dark={dark}>
        <PhoneProfile variant={variant} />
      </IOSDevice>
    </div>
  );
}

/* ── tweaks ────────────────────────────────────────────────────── */
function Panel({ t, setTweak }) {
  return (
    <TweaksPanel>
      <TweakSection label="Тема" />
      <TweakRadio label="Фон" value={t.theme} options={['light', 'dark']} onChange={v => setTweak('theme', v)} />

      <TweakSection label="Акцент" />
      <div style={{ display: 'flex', gap: 10, padding: '2px 2px 4px' }}>
        {Object.entries(ACCENTS).map(([key, a]) => {
          const on = t.accent === key;
          return (
            <button key={key} onClick={() => setTweak('accent', key)} title={a.label}
              style={{ flex: 1, border: 'none', background: 'none', cursor: 'pointer', padding: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 5 }}>
              <span style={{ width: 30, height: 30, borderRadius: '50%', background: a[t.theme], boxShadow: on ? '0 0 0 2px #fff, 0 0 0 4px #888' : 'inset 0 0 0 1px rgba(0,0,0,0.12)' }} />
              <span style={{ fontSize: 10.5, fontWeight: on ? 700 : 500, color: on ? '#3a335a' : '#9a9aa6' }}>{a.label}</span>
            </button>
          );
        })}
      </div>

      <TweakSection label="Шрифты" />
      <TweakSelect label="Пара шрифтов" value={t.fonts} options={Object.keys(FONTS)} onChange={v => setTweak('fonts', v)} />

      <TweakSection label="Стекло и форма" />
      <TweakSlider label="Размытие стекла" value={t.glassBlur} min={2} max={40} step={1} unit="px" onChange={v => setTweak('glassBlur', v)} />
      <TweakSlider label="Скругления" value={t.radius} min={6} max={28} step={1} unit="px" onChange={v => setTweak('radius', v)} />
      <TweakRadio label="Плотность" value={t.density} options={['compact', 'regular', 'comfy']} onChange={v => setTweak('density', v)} />
    </TweaksPanel>
  );
}

/* ── app ───────────────────────────────────────────────────────── */
function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  uE(() => { applyTokens(t); }, [t]);
  const dark = t.theme === 'dark';

  return (
    <div style={{ minHeight: '100vh', padding: '34px 0 60px', fontFamily: "'Manrope', sans-serif", background: dark ? '#141418' : '#ececf0', transition: 'background .25s ease' }}>
      <div style={{ padding: '0 40px', maxWidth: 1320, margin: '0 auto 4px' }}>
        <h1 style={{ margin: 0, fontFamily: "'Sora', sans-serif", fontWeight: 700, fontSize: 25, color: dark ? '#f0f0f5' : '#1c1b2a' }}>Yoga Loka — экран профиля</h1>
        <p style={{ margin: '8px 0 0', fontSize: 14, color: dark ? '#9a9aa8' : '#6b6390', maxWidth: 760, lineHeight: 1.55 }}>
          Минимализм: плоский фон, один спокойный акцент, Liquid Glass только на верхней панели и навигации. Компактная шапка как в Instagram. Три раскладки — кликайте лайки, открывайте комментарии, переключайте вкладки. Тема, акцент и параметры — в панели <b>Tweaks</b>.
        </p>
      </div>

      <div style={{ display: 'flex', gap: 40, padding: '26px 40px 8px', overflowX: 'auto', alignItems: 'flex-start' }}>
        <Frame tag="A" name="Классика" sub="Компактная шапка как в Instagram" variant="classic" dark={dark} />
        <Frame tag="B" name="Стекло" sub="Та же шапка в матовой стеклянной карточке" variant="glass" dark={dark} />
        <Frame tag="C" name="Минимал" sub="Воздушная левая раскладка с хейрлайнами" variant="minimal" dark={dark} />
      </div>

      <Panel t={t} setTweak={setTweak} />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
