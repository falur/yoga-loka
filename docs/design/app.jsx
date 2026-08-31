// app.jsx — галерея «3 телефона в ряд» + Tweaks + применение токенов (минимал)
const { useState: uS, useEffect: uE } = React;

const THEMES = {
  light: {
    '--bg': '#fcfbf7', '--bg-2': '#efebe2',
    '--ink': '#211d18', '--ink-soft': '#6e685d', '--ink-faint': '#a39c8e',
    '--hairline': 'rgba(40,33,22,0.12)', '--hairline-2': 'rgba(40,33,22,0.06)',
    '--glass-bg': 'rgba(252,251,247,0.74)', '--glass-bd': 'rgba(40,33,22,0.08)',
    '--on-accent': '#fcfbf7',
  },
  dark: {
    '--bg': '#161310', '--bg-2': '#241f19',
    '--ink': '#f2efe7', '--ink-soft': '#aaa294', '--ink-faint': '#776f63',
    '--hairline': 'rgba(245,238,225,0.14)', '--hairline-2': 'rgba(245,238,225,0.07)',
    '--glass-bg': 'rgba(28,24,19,0.64)', '--glass-bd': 'rgba(245,238,225,0.11)',
    '--on-accent': '#16130f',
  },
};

// тёплая гамма золото → терракота (умеренная хрома, благородный тон) + пара альтернатив
const ACCENTS = {
  gold:    { label: 'Золото',    light: 'oklch(0.72 0.13 85)',  dark: 'oklch(0.80 0.13 86)'  },
  honey:   { label: 'Мёд',       light: 'oklch(0.68 0.13 78)',  dark: 'oklch(0.77 0.13 79)'  },
  amber:   { label: 'Янтарь',    light: 'oklch(0.66 0.13 65)',  dark: 'oklch(0.75 0.14 67)'  },
  ochre:   { label: 'Охра',      light: 'oklch(0.63 0.12 58)',  dark: 'oklch(0.73 0.13 60)'  },
  apricot: { label: 'Абрикос',   light: 'oklch(0.67 0.14 50)',  dark: 'oklch(0.76 0.14 52)'  },
  copper:  { label: 'Медь',      light: 'oklch(0.58 0.13 47)',  dark: 'oklch(0.70 0.13 49)'  },
  clay:    { label: 'Глина',     light: 'oklch(0.55 0.12 42)',  dark: 'oklch(0.69 0.12 44)'  },
  rust:    { label: 'Ржавчина',  light: 'oklch(0.51 0.13 38)',  dark: 'oklch(0.65 0.13 40)'  },
  terra:   { label: 'Терракота', light: 'oklch(0.49 0.14 34)',  dark: 'oklch(0.63 0.14 36)'  },
  forest:  { label: 'Хвоя',      light: 'oklch(0.46 0.08 156)', dark: 'oklch(0.66 0.10 158)' },
  plum:    { label: 'Слива',     light: 'oklch(0.45 0.11 332)', dark: 'oklch(0.65 0.12 332)' },
  ink:     { label: 'Чернила',   light: 'oklch(0.43 0.10 258)', dark: 'oklch(0.67 0.12 260)' },
};

const FONTS = {
  'Sora · Manrope': ["'Sora', sans-serif", "'Manrope', sans-serif"],
  'Outfit': ["'Outfit', sans-serif", "'Outfit', sans-serif"],
  'Quicksand · Nunito': ["'Quicksand', sans-serif", "'Nunito Sans', sans-serif"],
  'Space Grotesk · Manrope': ["'Space Grotesk', sans-serif", "'Manrope', sans-serif"],
};

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "light",
  "accent": "clay",
  "fonts": "Sora · Manrope",
  "glassBlur": 18,
  "radius": 14,
  "density": "regular"
}/*EDITMODE-END*/;

function applyTokens(t) {
  const r = document.documentElement.style;
  const theme = THEMES[t.theme] || THEMES.light;
  Object.entries(theme).forEach(([k, v]) => r.setProperty(k, v));
  const acc = (ACCENTS[t.accent] || ACCENTS.clay)[t.theme] || (ACCENTS[t.accent] || ACCENTS.clay).light;
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

/* ── tweaks ────────────────────────────────────────────────────── */
function Panel({ t, setTweak }) {
  return (
    <TweaksPanel>
      <TweakSection label="Тема" />
      <TweakRadio label="Фон" value={t.theme} options={['light', 'dark']} onChange={v => setTweak('theme', v)} />

      <TweakSection label="Акцент" />
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px 6px', padding: '2px 2px 4px' }}>
        {Object.entries(ACCENTS).map(([key, a]) => {
          const on = t.accent === key;
          return (
            <button key={key} onClick={() => setTweak('accent', key)} title={a.label}
              style={{ flex: '0 0 20%', boxSizing: 'border-box', border: 'none', background: 'none', cursor: 'pointer', padding: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 5 }}>
              <span style={{ width: 28, height: 28, borderRadius: '50%', background: a[t.theme], boxShadow: on ? '0 0 0 2px #fcfbf7, 0 0 0 4px #8a7c63' : 'inset 0 0 0 1px rgba(0,0,0,0.12)' }} />
              <span style={{ fontSize: 10, fontWeight: on ? 700 : 500, color: on ? '#3d352a' : '#a39c8e', textAlign: 'center', lineHeight: 1.15 }}>{a.label}</span>
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
    <div style={{ minHeight: '100vh', padding: '40px 24px 60px', fontFamily: "'Manrope', sans-serif", background: dark ? '#141418' : '#ececf0', transition: 'background .25s ease', display: 'flex', justifyContent: 'center', alignItems: 'flex-start' }}>
      <IOSDevice width={390} height={844} dark={dark}>
        <PhoneProfile variant="classic" />
      </IOSDevice>

      <Panel t={t} setTweak={setTweak} />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
