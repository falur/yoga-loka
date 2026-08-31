// practice-player.jsx — визуальный проигрыватель практики.
// Открывается по клику на карточку в «Моей практике». Гибко подстраивается под
// содержимое: видео-часть → видео-плеер, таймерная часть → кольцо обратного отсчёта,
// цикл → шаги с повторами, метроном → пульс BPM. Аудио — строкой «сейчас играет».
// Экспорт в window: PracticePlayerScreen.

const { useState: uPP, useEffect: uPPe, useRef: uPPr, useMemo: uPPm } = React;

const PQ = "'Quicksand', sans-serif";
const PN = "'Nunito Sans', system-ui, sans-serif";
const PS = "'Space Grotesk', sans-serif";

const PP_TONES = {
  grape: 'var(--c-grape)', coral: 'var(--c-coral)', sky: 'var(--c-sky)', mint: 'var(--c-mint)',
  sun: 'var(--c-goldink)', bubble: 'var(--c-bubble)', leaf: 'var(--c-leaf)',
};
const PP_TINTS = {
  grape: 'var(--t-grape)', coral: 'var(--t-coral)', sky: 'var(--t-sky)', mint: 'var(--t-mint)',
  sun: 'var(--t-sun)', bubble: 'var(--t-bubble)', leaf: 'var(--t-leaf)',
};

const ppTime = (s) => {
  s = Math.max(0, Math.ceil(s));
  const m = Math.floor(s / 60);
  return `${m}:${String(s % 60).padStart(2, '0')}`;
};

/* ── детерминированная «начинка» практики ─────────────────────── */
const ppSeed = (str) => { let h = 0; for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0; return h; };
// имена по позиции в практике: начало → середина → завершение
const PP_OPEN = ['Настройка и дыхание', 'Разминка суставов'];
const PP_MID = ['Основной блок', 'Динамический поток', 'Пранаяма', 'Работа с балансом', 'Силовой блок'];
const PP_END = ['Глубокое расслабление', 'Шавасана'];
const ppName = (seed, i, n) => {
  if (i === 0) return PP_OPEN[0];
  if (n > 3 && i === 1) return PP_OPEN[1];
  if (i === n - 1) return PP_END[seed % PP_END.length];
  return PP_MID[(seed + i) % PP_MID.length];
};
const PP_CYCLE_STEPS = [
  { name: 'Вдох — руки вверх', anim: 'inflate' },
  { name: 'Задержка на вдохе', anim: 'vibrate' },
  { name: 'Выдох — наклон', anim: 'deflate' },
  { name: 'Пауза', anim: 'none' },
];
const PP_TRACKS = ['Раскрытие дыхания · Anoushka', 'Гонг и тишина · Mirabai', 'Мантра «Ом» · Deva P.', 'Тёплый поток · Garth S.'];

// Разворачивает карточку практики в структуру частей.
function ppBuild(p) {
  const seed = ppSeed(p.id);
  const n = Math.max(1, p.parts || 1);
  const hasVideo = (p.media || []).includes('video');
  const hasAudio = (p.media || []).includes('audio');
  const hasTimer = (p.media || []).includes('timer');
  const total = (p.min || 20) * 60;
  const parts = [];
  for (let i = 0; i < n; i++) {
    const share = i === 0 ? 0.8 : i === n - 1 ? 1.2 : 1;
    const sec = Math.max(60, Math.round((total / n) * share / 10) * 10);
    const name = ppName(seed, i, n);
    const video = hasVideo && (i % 2 === 0 || n <= 2);
    const audio = hasAudio && !video;
    let type = 'simple';
    if (hasTimer && n > 2 && i === Math.max(1, Math.floor(n / 2))) type = 'cycle';
    else if (hasTimer && n > 3 && i === n - 2 && (seed % 3 === 0)) type = 'metro';
    const part = {
      id: p.id + '-' + i, name, video, audio, type, sec,
      desc: type === 'cycle' ? ('Повторяйте последовательность в своём темпе дыхания.' + (video ? ' Ориентир — видео выше.' : ''))
        : type === 'metro' ? 'Держите ритм ударов, не ускоряйтесь.'
        : video ? 'Следуйте за видео. Держите ровное дыхание.' : 'Оставайтесь в позе, наблюдайте дыхание.',
      track: (hasAudio || audio) ? PP_TRACKS[(seed + i) % PP_TRACKS.length] : null,
    };
    if (type === 'cycle') {
      const stepCount = 3 + ((seed + i) % 2);
      part.steps = PP_CYCLE_STEPS.slice(0, stepCount).map((s, k) => ({ ...s, sec: [20, 10, 20, 10][k] || 15 }));
      part.reps = Math.max(2, Math.round(sec / part.steps.reduce((a, s) => a + s.sec, 0)));
    }
    if (type === 'metro') {
      part.bpm = [50, 60, 72, 90][(seed + i) % 4];
      part.beats = Math.round(sec / (60 / part.bpm) / 4) * 4;
    }
    parts.push(part);
  }
  return parts;
}

// Плоский список отрезков воспроизведения.
function ppSegments(parts) {
  const segs = [];
  parts.forEach((part, pi) => {
    if (part.type === 'cycle') {
      for (let r = 0; r < part.reps; r++) {
        part.steps.forEach((s, si) => segs.push({
          part, pi, label: s.name, sec: s.sec, anim: s.anim,
          cycle: { rep: r + 1, reps: part.reps, step: si + 1, steps: part.steps.length },
        }));
      }
    } else if (part.type === 'metro') {
      segs.push({ part, pi, label: part.name, sec: Math.round(part.beats * (60 / part.bpm)), metro: true });
    } else {
      segs.push({ part, pi, label: part.name, sec: part.sec, anim: part.video ? 'none' : 'inflate' });
    }
  });
  return segs;
}

const PP_ANIM = {
  inflate: 'peInflate 4s ease-in-out infinite',
  deflate: 'peDeflate 4s ease-in-out infinite',
  none: 'none',
};

/* ── кольцо обратного отсчёта ─────────────────────────────────── */
function PPRing({ size, pct, color, anim, children }) {
  const sw = size > 200 ? 9 : 7;
  const r = (size - sw) / 2;
  const c = 2 * Math.PI * r;
  return (
    <div style={{ position: 'relative', width: size, height: size, flexShrink: 0 }}>
      <svg width={size} height={size} style={{ transform: 'rotate(-90deg)', display: 'block' }}>
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="var(--p-ctrl)" strokeWidth={sw} />
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke={color} strokeWidth={sw} strokeLinecap="round"
          strokeDasharray={c} strokeDashoffset={c * (1 - pct)} style={{ transition: 'stroke-dashoffset .25s linear' }} />
      </svg>
      {anim && anim !== 'none' && (
        <div className={anim === 'vibrate' ? 'peWave' : undefined} style={{
          position: 'absolute', left: '50%', top: '50%', width: size * 0.52, height: size * 0.52,
          marginLeft: -(size * 0.26), marginTop: -(size * 0.26), borderRadius: '50%',
          background: color, opacity: 0.13, color, animation: PP_ANIM[anim] || 'none',
        }} />
      )}
      <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 2 }}>{children}</div>
    </div>
  );
}

/* ── видео-панель (плейсхолдер под реальный файл) ─────────────── */
function PPVideo({ playing, pct, color, onToggle }) {
  return (
    <div style={{ position: 'relative', width: '100%', aspectRatio: '16 / 10', borderRadius: 20, overflow: 'hidden', background: 'var(--p-ctrl)' }}>
      <div style={{ position: 'absolute', inset: 0, background: 'repeating-linear-gradient(135deg, rgba(120,110,140,0.10) 0 12px, transparent 12px 24px)' }} />
      <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 9, color: 'var(--p-mute)' }}>
        <button onClick={onToggle} className="pbtn tap" aria-label={playing ? 'Пауза' : 'Играть'} style={{
          width: 60, height: 60, borderRadius: '50%', border: 'none', cursor: 'pointer',
          background: color, color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 16px 30px -14px rgba(20,14,30,0.6)',
        }}>{playing ? <PPPause size={22} /> : <PPPlay size={24} />}</button>
        <span style={{ fontFamily: 'monospace', fontSize: 11.5 }}>видео практики · flow.mp4</span>
      </div>
      <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, height: 4, background: 'rgba(120,110,140,0.22)' }}>
        <div style={{ height: '100%', width: `${pct * 100}%`, background: color, transition: 'width .25s linear' }} />
      </div>
    </div>
  );
}

/* ── локальные иконки управления ──────────────────────────────── */
const PPPlay = ({ size = 22 }) => <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l10.5-6.5L8 5.5Z" /></svg>;
const PPPause = ({ size = 22 }) => <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor"><rect x="7" y="5.5" width="3.8" height="13" rx="1.4" /><rect x="13.2" y="5.5" width="3.8" height="13" rx="1.4" /></svg>;
const PPPrev = ({ size = 22 }) => <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor"><rect x="5.5" y="6" width="2.6" height="12" rx="1.2" /><path d="M19 6.5v11L10 12l9-5.5Z" /></svg>;
const PPNext = ({ size = 22 }) => <svg width={size} height={size} viewBox="0 0 24 24" fill="currentColor"><rect x="15.9" y="6" width="2.6" height="12" rx="1.2" /><path d="M5 6.5v11L14 12 5 6.5Z" /></svg>;

/* ══════════ ПЛЕЕР ══════════ */
function PracticePlayerScreen({ practice, dark = false, onClose }) {
  const color = PP_TONES[practice.tone] || 'var(--c-grape)';
  const tint = PP_TINTS[practice.tone] || 'var(--t-grape)';

  const parts = uPPm(() => ppBuild(practice), [practice.id]);
  const segs = uPPm(() => ppSegments(parts), [parts]);
  const totalSec = uPPm(() => segs.reduce((a, s) => a + s.sec, 0), [segs]);

  const [stage, setStage] = uPP('intro');   // intro | run | done
  const [si, setSi] = uPP(0);
  const [left, setLeft] = uPP(segs[0] ? segs[0].sec : 0);
  const [playing, setPlaying] = uPP(true);
  const [speed, setSpeed] = uPP(1);
  const [elapsed, setElapsed] = uPP(0);
  const beatRef = uPPr(0);

  const seg = segs[si];
  const part = seg ? seg.part : parts[0];

  uPPe(() => {
    if (stage !== 'run' || !playing) return;
    const iv = setInterval(() => {
      setLeft((v) => {
        const nv = v - 0.1 * speed;
        if (nv > 0) return nv;
        setSi((k) => {
          if (k + 1 >= segs.length) { setStage('done'); setPlaying(false); return k; }
          setLeft(segs[k + 1].sec);
          return k + 1;
        });
        return 0;
      });
      setElapsed((e) => Math.min(totalSec, e + 0.1 * speed));
    }, 100);
    return () => clearInterval(iv);
  }, [stage, playing, speed, segs, si, totalSec]);

  const goto = (k) => {
    if (k < 0 || k >= segs.length) return;
    const before = segs.slice(0, k).reduce((a, s) => a + s.sec, 0);
    setSi(k); setLeft(segs[k].sec); setElapsed(before);
  };
  const start = () => { setStage('run'); setSi(0); setLeft(segs[0].sec); setElapsed(0); setPlaying(true); };

  const segPct = seg ? 1 - left / seg.sec : 0;
  const partSegs = segs.map((s, i) => ({ s, i })).filter((x) => x.s.pi === (seg ? seg.pi : 0));
  const beatSec = part && part.bpm ? 60 / part.bpm : 1;

  /* ── экран 1: обзор практики ─────────────────────────────── */
  if (stage === 'intro') {
    return (
      <div className={'ylp' + (dark ? ' dark' : '')} data-screen-label="Практика · обзор" style={{ height: '100%', position: 'absolute', inset: 0, zIndex: 40, animation: 'peSlideIn .28s cubic-bezier(.2,.9,.25,1)' }}>
        <div className="pscroll" style={{ paddingBottom: 108 }}>
          <div style={{ padding: '52px 16px 22px', background: tint }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              <button onClick={onClose} className="pbtn tap" aria-label="Назад" style={{ width: 40, height: 40, borderRadius: 13, flexShrink: 0, border: 'none', cursor: 'pointer', background: 'var(--p-elev)', color: 'var(--p-ink)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconBack size={20} /></button>
              <span style={{ flex: 1 }} />
              <span style={{ padding: '5px 11px', borderRadius: 8, background: 'var(--p-elev)', color, fontFamily: PS, fontWeight: 600, fontSize: 11.5, letterSpacing: '0.06em' }}>{practice.code}</span>
            </div>
            <h1 style={{ margin: '18px 0 0', fontFamily: PQ, fontWeight: 800, fontSize: 27, lineHeight: 1.13, letterSpacing: '-0.02em', color: 'var(--p-ink)', textWrap: 'pretty' }}>{practice.name}</h1>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7, marginTop: 13 }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '6px 12px', borderRadius: 999, background: 'var(--p-elev)', color, fontFamily: PQ, fontWeight: 800, fontSize: 13 }}><IconClock size={14} /> {Math.round(totalSec / 60)} мин</span>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '6px 12px', borderRadius: 999, background: 'var(--p-elev)', color: 'var(--p-soft)', fontFamily: PQ, fontWeight: 800, fontSize: 13 }}><IconLayers size={14} /> {parts.length} частей</span>
              {(practice.media || []).includes('video') && <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '6px 12px', borderRadius: 999, background: 'var(--p-elev)', color: 'var(--p-soft)', fontFamily: PQ, fontWeight: 800, fontSize: 13 }}><IconVideo size={14} /> Видео</span>}
              {(practice.media || []).includes('audio') && <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '6px 12px', borderRadius: 999, background: 'var(--p-elev)', color: 'var(--p-soft)', fontFamily: PQ, fontWeight: 800, fontSize: 13 }}><IconAudio size={14} /> Аудио</span>}
            </div>
          </div>

          <div style={{ padding: '18px 16px 0', display: 'flex', alignItems: 'center', gap: 9 }}>
            <MPAva p={practice.by} size={30} />
            <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--p-mute)' }}>Практика от <strong style={{ color: 'var(--p-soft)', fontWeight: 800 }}>{practice.by.name}</strong></span>
          </div>

          <div style={{ padding: '18px 16px 6px', fontFamily: PQ, fontWeight: 800, fontSize: 11.5, letterSpacing: '0.07em', textTransform: 'uppercase', color: 'var(--p-faint)' }}>Части практики</div>
          <div style={{ padding: '0 14px', display: 'flex', flexDirection: 'column', gap: 9 }}>
            {parts.map((pt, i) => (
              <div key={pt.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 13px', borderRadius: 18, background: 'var(--p-elev)', border: '1px solid var(--p-border)' }}>
                <span style={{ width: 34, height: 34, flexShrink: 0, borderRadius: 11, background: tint, color, fontFamily: PS, fontWeight: 600, fontSize: 14, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>{i + 1}</span>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontFamily: PQ, fontWeight: 800, fontSize: 15, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{pt.name}</div>
                  <div style={{ fontFamily: PN, fontWeight: 700, fontSize: 12.5, color: 'var(--p-mute)', marginTop: 2 }}>
                    {pt.type === 'cycle' ? `Цикл · ${pt.steps.length} шага × ${pt.reps}` : pt.type === 'metro' ? `Метроном · ${pt.bpm} BPM` : `Таймер · ${ppTime(pt.sec)}`}
                  </div>
                </div>
                <span style={{ display: 'flex', gap: 5, flexShrink: 0, color: 'var(--p-faint)' }}>
                  {pt.video && <IconVideo size={16} />}
                  {pt.track && <IconAudio size={16} />}
                  <IconTimer size={16} />
                </span>
              </div>
            ))}
          </div>
        </div>

        <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, zIndex: 5, padding: '12px 14px 26px', background: 'var(--p-chrome)', backdropFilter: 'blur(14px)', WebkitBackdropFilter: 'blur(14px)', borderTop: '1px solid var(--p-border)' }}>
          <button onClick={start} className="pbtn tap" style={{ width: '100%', height: 54, borderRadius: 17, border: 'none', cursor: 'pointer', background: color, color: '#fff', fontFamily: PQ, fontWeight: 800, fontSize: 16.5, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 9 }}>
            <PPPlay size={20} /> Начать практику
          </button>
        </div>
      </div>
    );
  }

  /* ── экран 3: завершение ─────────────────────────────────── */
  if (stage === 'done') {
    return (
      <div className={'ylp' + (dark ? ' dark' : '')} data-screen-label="Практика завершена" style={{ height: '100%', position: 'absolute', inset: 0, zIndex: 40 }}>
        <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 14, padding: '0 28px', textAlign: 'center' }}>
          <div style={{ width: 92, height: 92, borderRadius: '50%', background: tint, color, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', animation: 'ylpop .5s cubic-bezier(.2,.9,.25,1)' }}><IconLotus size={44} /></div>
          <h2 style={{ margin: '6px 0 0', fontFamily: PQ, fontWeight: 800, fontSize: 24, color: 'var(--p-ink)' }}>Практика завершена</h2>
          <div style={{ fontFamily: PN, fontWeight: 600, fontSize: 14.5, color: 'var(--p-mute)', lineHeight: 1.5 }}>
            {practice.name} · {Math.round(totalSec / 60)} мин · {parts.length} частей
          </div>
          <div style={{ display: 'flex', gap: 10, marginTop: 14, width: '100%' }}>
            <button onClick={start} className="pbtn tap" style={{ flex: 1, height: 50, borderRadius: 15, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-soft)', fontFamily: PQ, fontWeight: 800, fontSize: 15 }}>Ещё раз</button>
            <button onClick={onClose} className="pbtn tap" style={{ flex: 1, height: 50, borderRadius: 15, border: 'none', cursor: 'pointer', background: color, color: '#fff', fontFamily: PQ, fontWeight: 800, fontSize: 15 }}>Готово</button>
          </div>
        </div>
      </div>
    );
  }

  /* ── экран 2: воспроизведение ────────────────────────────── */
  const ringSize = part.video ? 128 : 236;
  return (
    <div className={'ylp' + (dark ? ' dark' : '')} data-screen-label="Практика · плеер" style={{ height: '100%', position: 'absolute', inset: 0, zIndex: 40, display: 'flex', flexDirection: 'column' }}>
      {/* шапка */}
      <div style={{ flexShrink: 0, padding: '52px 14px 10px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <button onClick={onClose} className="pbtn tap" aria-label="Закрыть" style={{ width: 38, height: 38, borderRadius: 12, flexShrink: 0, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-soft)', fontSize: 17, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>✕</button>
          <div style={{ flex: 1, minWidth: 0, textAlign: 'center' }}>
            <div style={{ fontFamily: PQ, fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{practice.name}</div>
            <div style={{ fontFamily: PN, fontWeight: 700, fontSize: 12, color: 'var(--p-mute)' }}>Часть {seg.pi + 1} из {parts.length} · осталось {ppTime(totalSec - elapsed)}</div>
          </div>
          <button onClick={() => setSpeed((s) => s === 1 ? 6 : s === 6 ? 20 : 1)} className="pbtn tap" aria-label="Скорость демо" style={{ width: 38, height: 38, borderRadius: 12, flexShrink: 0, border: 'none', cursor: 'pointer', background: speed === 1 ? 'var(--p-ctrl)' : tint, color: speed === 1 ? 'var(--p-mute)' : color, fontFamily: PS, fontWeight: 600, fontSize: 12.5 }}>×{speed}</button>
        </div>
        {/* прогресс по частям */}
        <div style={{ display: 'flex', gap: 4, marginTop: 13 }}>
          {parts.map((pt, i) => {
            const done = i < seg.pi, cur = i === seg.pi;
            const pSegs = segs.filter((s) => s.pi === i);
            const pTotal = pSegs.reduce((a, s) => a + s.sec, 0);
            const doneInPart = segs.slice(0, si).filter((s) => s.pi === i).reduce((a, s) => a + s.sec, 0) + (cur ? seg.sec - left : 0);
            return (
              <div key={pt.id} style={{ flex: 1, height: 4, borderRadius: 999, background: 'var(--p-ctrl)', overflow: 'hidden' }}>
                <div style={{ height: '100%', width: done ? '100%' : cur ? `${Math.min(100, (doneInPart / pTotal) * 100)}%` : '0%', background: color, transition: 'width .25s linear' }} />
              </div>
            );
          })}
        </div>
      </div>

      {/* сцена */}
      <div className="pscroll" style={{ position: 'relative', flex: 1, inset: 'auto', padding: '6px 18px 20px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16 }}>
        {part.video && <PPVideo playing={playing} pct={segPct} color={color} onToggle={() => setPlaying((v) => !v)} />}

        {seg.metro ? (
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16, padding: '14px 0 0' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
              {[0, 1, 2].map((k) => (
                <div key={k} style={{ width: 22, height: 22, borderRadius: '50%', background: color, animation: playing ? `pePulse ${beatSec}s ease-in-out infinite` : 'none', animationDelay: `${(beatSec / 3) * k}s`, opacity: playing ? 1 : 0.35 }} />
              ))}
            </div>
            <div style={{ fontFamily: PS, fontWeight: 600, fontSize: 52, lineHeight: 1, color: 'var(--p-ink)', fontVariantNumeric: 'tabular-nums' }}>{part.bpm}</div>
            <div style={{ fontFamily: PQ, fontWeight: 800, fontSize: 13, color, letterSpacing: '0.06em', textTransform: 'uppercase', marginTop: -8 }}>ударов в минуту</div>
            <div style={{ fontFamily: PS, fontWeight: 600, fontSize: 26, color: 'var(--p-soft)', fontVariantNumeric: 'tabular-nums' }}>{ppTime(left)}</div>
          </div>
        ) : (
          <PPRing size={ringSize} pct={segPct} color={color} anim={playing ? seg.anim : 'none'}>
            <div style={{ fontFamily: PS, fontWeight: 600, fontSize: ringSize > 200 ? 52 : 30, lineHeight: 1, color: 'var(--p-ink)', fontVariantNumeric: 'tabular-nums' }}>{ppTime(left)}</div>
            {seg.cycle && <div style={{ fontFamily: PQ, fontWeight: 800, fontSize: ringSize > 200 ? 12.5 : 11, color, marginTop: ringSize > 200 ? 6 : 3, whiteSpace: 'nowrap' }}>круг {seg.cycle.rep}/{seg.cycle.reps}</div>}
          </PPRing>
        )}

        {/* текущий шаг / часть */}
        <div style={{ textAlign: 'center', maxWidth: 320 }}>
          <div style={{ fontFamily: PQ, fontWeight: 800, fontSize: 11.5, letterSpacing: '0.07em', textTransform: 'uppercase', color }}>{seg.label === part.name ? `Часть ${seg.pi + 1} · ${part.type === 'metro' ? 'Метроном' : 'Таймер'}` : part.name}</div>
          <h2 style={{ margin: '5px 0 0', fontFamily: PQ, fontWeight: 800, fontSize: 21, lineHeight: 1.2, color: 'var(--p-ink)', textWrap: 'pretty' }}>{seg.label}</h2>
          <p style={{ margin: '7px 0 0', fontFamily: PN, fontWeight: 600, fontSize: 13.5, lineHeight: 1.5, color: 'var(--p-mute)' }}>{part.desc}</p>
        </div>

        {/* шаги цикла */}
        {seg.cycle && (
          <div className="hrow" style={{ display: 'flex', gap: 7, maxWidth: '100%', overflowX: 'auto', paddingBottom: 2 }}>
            {part.steps.map((s, i) => {
              const on = i + 1 === seg.cycle.step;
              return (
                <span key={i} style={{
                  flexShrink: 0, padding: '7px 13px', borderRadius: 999, whiteSpace: 'nowrap',
                  background: on ? color : 'var(--p-ctrl)', color: on ? '#fff' : 'var(--p-mute)',
                  fontFamily: PQ, fontWeight: 800, fontSize: 12.5, transition: 'all .2s ease',
                }}>{i + 1}. {s.name}</span>
              );
            })}
          </div>
        )}

        {/* сейчас играет */}
        {part.track && (
          <div style={{ width: '100%', display: 'flex', alignItems: 'center', gap: 11, padding: '11px 13px', borderRadius: 16, background: 'var(--p-ctrl)' }}>
            <span style={{ width: 34, height: 34, flexShrink: 0, borderRadius: 11, background: color, color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><IconAudio size={17} /></span>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontFamily: PQ, fontWeight: 800, fontSize: 10.5, letterSpacing: '0.06em', textTransform: 'uppercase', color: 'var(--p-faint)' }}>Сейчас играет</div>
              <div style={{ fontFamily: PN, fontWeight: 700, fontSize: 13, color: 'var(--p-soft)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{part.track}</div>
            </div>
          </div>
        )}
      </div>

      {/* управление */}
      <div style={{ flexShrink: 0, padding: '12px 18px 26px', background: 'var(--p-chrome)', borderTop: '1px solid var(--p-border)' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 22 }}>
          <button onClick={() => goto(si - 1)} disabled={si === 0} className="pbtn tap" aria-label="Назад" style={{ width: 52, height: 52, borderRadius: '50%', border: 'none', cursor: si === 0 ? 'default' : 'pointer', background: 'var(--p-ctrl)', color: si === 0 ? 'var(--p-faint)' : 'var(--p-soft)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><PPPrev size={22} /></button>
          <button onClick={() => setPlaying((v) => !v)} className="pbtn tap" aria-label={playing ? 'Пауза' : 'Продолжить'} style={{ width: 74, height: 74, borderRadius: '50%', border: 'none', cursor: 'pointer', background: color, color: '#fff', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 18px 34px -16px rgba(20,14,30,0.6)' }}>{playing ? <PPPause size={30} /> : <PPPlay size={32} />}</button>
          <button onClick={() => goto(si + 1)} disabled={si >= segs.length - 1} className="pbtn tap" aria-label="Вперёд" style={{ width: 52, height: 52, borderRadius: '50%', border: 'none', cursor: si >= segs.length - 1 ? 'default' : 'pointer', background: 'var(--p-ctrl)', color: si >= segs.length - 1 ? 'var(--p-faint)' : 'var(--p-soft)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><PPNext size={22} /></button>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { PracticePlayerScreen });
