// calendar-vedic.jsx — ведическая шапка над календарём (панчанга) + шторка экадаши
// Экспорт в window: PanchangBar, EkadashiSheet

const { useState: uVed } = React;
const vP = window.POP, vT = window.TINT;

/* луна в «ночном диске» — premium-вид для шапки/шторки */
function MoonBadge({ size = 54, illum, waxing }) {
  return (
    <span style={{
      width: size, height: size, borderRadius: '50%', flexShrink: 0,
      background: 'radial-gradient(circle at 34% 28%, #3b3458 0%, #211c36 62%, #171327 100%)',
      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
      boxShadow: '0 6px 16px -9px rgba(24,20,42,0.85), inset 0 0 0 1px rgba(255,255,255,0.07)',
    }}>
      <window.MoonPhase size={size * 0.82} illum={illum} waxing={waxing}
        lit="#ECE6D2" dark="rgba(255,255,255,0.045)" line="rgba(236,230,210,0.22)" strokeW={1.2} />
    </span>
  );
}

/* мини-чип данных панчанги (значение + времена начала/конца) */
function VChip({ label, value, times }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0, flex: 1 }}>
      <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, letterSpacing: '0.05em', textTransform: 'uppercase', color: 'var(--p-faint)' }}>{label}</span>
      <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{value}</span>
      {times && <TimeRange t={times} size={9.5} stack />}
    </div>
  );
}

/* диапазон «начало → конец» с датами. stack=true — в две строки (для узких чипов) */
function TimeRange({ t, size = 10.5, stack = false }) {
  const dstr = (c) => `${c.d}.${String(c.mo + 1).padStart(2, '0')}`;
  const base = { fontFamily: "'Space Grotesk', sans-serif", fontWeight: 600, fontSize: size, color: 'var(--p-faint)', whiteSpace: 'nowrap' };
  const dt = { opacity: 0.62, marginRight: 3 };
  if (stack) {
    return (
      <span style={{ display: 'flex', flexDirection: 'column', gap: 1, marginTop: 2, ...base }}>
        <span><span style={dt}>{dstr(t.begin)}</span>{t.begin.hm}</span>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3 }}><span style={{ opacity: 0.5, fontSize: size - 1 }}>→</span><span><span style={dt}>{dstr(t.end)}</span>{t.end.hm}</span></span>
      </span>
    );
  }
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3, marginTop: 1, ...base }}>
      <span><span style={dt}>{dstr(t.begin)}</span>{t.begin.hm}</span>
      <span style={{ opacity: 0.5, fontSize: size - 1, margin: '0 1px' }}>→</span>
      <span><span style={dt}>{dstr(t.end)}</span>{t.end.hm}</span>
    </span>
  );
}

/* ── ШАПКА ПАНЧАНГИ над календарём ───────────────────────────────── */
function PanchangBar({ date, onOpenEkadashi, onOpenDetail }) {
  const p = window.PANCHANG.for(date);
  const tm = uVedMemo(date);
  const ritu = p.ritu;
  const rPop = vP[ritu.tone], rTint = vT[ritu.tone];
  const pakAccent = p.paksha === 'shukla' ? vP.sun : vP.grape;
  return (
    <div className="tap" onClick={onOpenDetail} style={{ margin: '2px 14px 16px', borderRadius: 20, background: 'var(--p-elev)', border: '1px solid var(--p-border)', boxShadow: '0 8px 22px -16px rgba(20,14,30,0.5)', overflow: 'hidden', cursor: 'pointer' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 13, padding: '13px 14px 12px' }}>
        <MoonBadge size={52} illum={p.illum} waxing={p.waxing} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, letterSpacing: '0.05em', textTransform: 'uppercase', color: 'var(--p-faint)', marginBottom: 2 }}>Титхи · лунные сутки</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 7, flexWrap: 'wrap' }}>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16.5, color: 'var(--p-ink)', lineHeight: 1.1 }}>{p.tName}</span>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 10.5, color: pakAccent, background: p.paksha === 'shukla' ? vT.sun : vT.grape, padding: '2px 8px', borderRadius: 999, whiteSpace: 'nowrap' }}>{p.pakshaShort}</span>
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginTop: 4, flexWrap: 'wrap' }}>
            <span style={{ fontSize: 11.5, fontWeight: 700, color: 'var(--p-mute)' }}>{p.tNum}-е сутки</span>
            <span style={{ width: 3, height: 3, borderRadius: '50%', background: 'var(--p-faint)' }} />
            <TimeRange t={tm.tithi} />
          </div>
        </div>
        {p.isEkadashi && (
          <button className="tap pbtn" onClick={(e) => { e.stopPropagation(); onOpenEkadashi(); }} style={{
            flexShrink: 0, border: 'none', cursor: 'pointer', borderRadius: 14, padding: '8px 11px',
            background: 'var(--eka-soft)', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2,
            boxShadow: 'inset 0 0 0 1.5px var(--eka-ring)',
          }}>
            <span style={{ fontSize: 17, lineHeight: 1 }}>🪷</span>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, color: 'var(--eka)', whiteSpace: 'nowrap' }}>Экадаши</span>
          </button>
        )}
      </div>
      <div style={{ display: 'flex', gap: 8, padding: '10px 14px', borderTop: '1px solid var(--p-border)', background: 'var(--p-bg)' }}>
        <VChip label="Накшатра" value={`${p.nak} · ${p.pada}`} times={tm.nak} />
        <span style={{ width: 1, background: 'var(--p-border)' }} />
        <VChip label="Йога" value={p.yoga} times={tm.yoga} />
        <span style={{ width: 1, background: 'var(--p-border)' }} />
        <VChip label="Карана" value={p.karana} times={tm.karana} />
      </div>
      {/* сезон (риту) + аффорданс «подробнее» */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '9px 14px', borderTop: '1px solid var(--p-border)', background: rTint, whiteSpace: 'nowrap', overflow: 'hidden' }}>
        <span style={{ fontSize: 15, lineHeight: 1, flexShrink: 0 }}>{ritu.emoji}</span>
        <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, letterSpacing: '0.05em', textTransform: 'uppercase', color: rPop, flexShrink: 0 }}>Сезон</span>
        <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13, color: 'var(--p-ink)', flexShrink: 0 }}>{ritu.ru}</span>
        <span style={{ flex: 1, minWidth: 6 }} />
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 2, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11, color: rPop, flexShrink: 0 }}>Подробнее<IconChevron size={13} style={{ color: rPop }} /></span>
      </div>
    </div>
  );
}

// мемоизация дорогого расчёта времён по ключу даты
function uVedMemo(date) {
  const key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
  return React.useMemo(() => window.PANCHANG.times(date), [key]);
}

/* ── ШТОРКА ЭКАДАШИ: описание + список ближайших ─────────────────── */
const vWrapSheet = (host, onClose, body) =>
  ReactDOM.createPortal(
    <React.Fragment>
      <div className="pscrim" onClick={onClose} />
      <div className="psheet" style={{ padding: '0 8px 12px' }}>{body}</div>
    </React.Fragment>, host);

function EkadashiSheet({ host, date, onClose, onPickDate }) {
  if (!host) return null;
  const p = window.PANCHANG.for(date);
  const e = p.ekadashi;
  if (!e) return null;
  const C = window.CAL;
  const list = window.PANCHANG.upcomingEkadashi(date, 6);
  const fmtD = (d) => `${d.getDate()} ${C.RU_MONTH_GEN[d.getMonth()]}`;
  const wd = (d) => C.RU_WD_LONG[C.monIdx(d)].toLowerCase();
  return vWrapSheet(host, onClose, (
    <div style={{ ...window.CALUI.card, maxHeight: '86vh', overflowY: 'auto', WebkitOverflowScrolling: 'touch' }}>
      <div style={window.CALUI.grab} />
      {/* золотая шапка */}
      <div style={{ margin: '4px 16px 0', borderRadius: 18, padding: '16px', background: 'var(--eka-soft)', display: 'flex', alignItems: 'center', gap: 13, boxShadow: 'inset 0 0 0 1px var(--eka-ring)' }}>
        <MoonBadge size={54} illum={p.illum} waxing={p.waxing} />
        <div style={{ minWidth: 0, flex: 1 }}>
          <div style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '3px 9px', borderRadius: 999, background: 'var(--p-elev)', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 10.5, color: 'var(--eka)' }}>🪷 {p.pakshaShort}-экадаши</div>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', marginTop: 6, lineHeight: 1.1 }}>{e.ru} <span style={{ fontWeight: 700, fontSize: 13, color: 'var(--p-faint)' }}>· {e.sa}</span></div>
          <div style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--p-mute)', marginTop: 3 }}>{fmtD(date)}, {wd(date)}</div>
        </div>
      </div>
      <div style={{ padding: '14px 18px 16px' }}>
        <p style={{ margin: 0, fontSize: 14.5, lineHeight: 1.6, color: 'var(--p-soft)', textWrap: 'pretty' }}>{e.desc}</p>
        {/* практика дня */}
        <div style={{ display: 'flex', gap: 8, marginTop: 14 }}>
          {[['🍵', 'Пост'], ['🤫', 'Тишина'], ['🧘', 'Практика'], ['📿', 'Джапа']].map(([ic, t]) => (
            <div key={t} style={{ flex: 1, background: 'var(--p-ctrl)', borderRadius: 13, padding: '10px 4px', textAlign: 'center' }}>
              <div style={{ fontSize: 17 }}>{ic}</div>
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 10.5, color: 'var(--p-soft)', marginTop: 3 }}>{t}</div>
            </div>
          ))}
        </div>
        {/* список ближайших экадаши */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 7, margin: '20px 0 9px' }}>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12, letterSpacing: 0.4, textTransform: 'uppercase', color: 'var(--p-faint)' }}>Ближайшие экадаши</span>
          <span style={{ flex: 1, height: 1, background: 'var(--p-border)' }} />
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
          {list.map((it) => {
            const active = it.key === p.key;
            return (
              <button key={it.key} className="tap pbtn" onClick={() => onPickDate && onPickDate(it.date)} style={{
                display: 'flex', alignItems: 'center', gap: 12, width: '100%', textAlign: 'left', cursor: 'pointer',
                border: active ? '1.5px solid var(--eka-ring)' : '1px solid var(--p-border)', borderRadius: 14, padding: '10px 12px',
                background: active ? 'var(--eka-soft)' : 'var(--p-elev)',
              }}>
                <span style={{ width: 42, height: 42, borderRadius: 12, flexShrink: 0, background: it.paksha === 'shukla' ? vT.sun : vT.grape, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', lineHeight: 1 }}>
                  <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, color: it.paksha === 'shukla' ? vP.sun : vP.grape }}>{it.date.getDate()}</span>
                  <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 8, color: 'var(--p-mute)', marginTop: 1 }}>{C.RU_MONTH_GEN[it.date.getMonth()].slice(0, 3)}</span>
                </span>
                <span style={{ flex: 1, minWidth: 0 }}>
                  <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)' }}>{it.ru} <span style={{ fontWeight: 700, fontSize: 11.5, color: 'var(--p-faint)' }}>· {it.sa}</span></span>
                  <span style={{ display: 'block', fontSize: 11.5, fontWeight: 700, color: it.paksha === 'shukla' ? vP.sun : vP.grape, marginTop: 1 }}>{it.paksha === 'shukla' ? 'Шукла пакша · растущая' : 'Кришна пакша · убывающая'}</span>
                </span>
                {active && <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 10.5, color: 'var(--eka)', flexShrink: 0 }}>сейчас</span>}
              </button>
            );
          })}
        </div>
      </div>
    </div>
  ));
}

/* ── ШТОРКА «ПАНЧАНГА»: сезон + начала/концы всех элементов дня ──── */
const PAN_META = {
  tithi: { ru: 'Титхи', emoji: '🌙', tone: 'sun', desc: 'Лунные сутки — угол между Луной и Солнцем; задаёт ритм и настроение дня.' },
  nak: { ru: 'Накшатра', emoji: '⭐', tone: 'sky', desc: 'Лунная стоянка — созвездие, в котором сейчас Луна; влияет на характер дня.' },
  yoga: { ru: 'Йога', emoji: '🌀', tone: 'grape', desc: 'Сумма движений Луны и Солнца — качество и благоприятность энергии.' },
  karana: { ru: 'Карана', emoji: '⚖️', tone: 'mint', desc: 'Половина титхи — характер дел, подходящих на полсуток.' }
};

function PanElementRow({ k, data, valueLabel }) {
  const m = PAN_META[k];
  const pop = vP[m.tone], tint = vT[m.tone];
  const MO = window.CAL.RU_MONTH_GEN;
  const clk = (c, lead) => (
    <span style={{ display: 'inline-flex', alignItems: 'baseline', gap: 5 }}>
      <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11, color: 'var(--p-faint)' }}>{lead}</span>
      <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12, color: 'var(--p-soft)', whiteSpace: 'nowrap' }}>{c.d} {MO[c.mo].slice(0, 3)}</span>
      <span style={{ fontFamily: "'Space Grotesk', sans-serif", fontWeight: 600, fontSize: 14, color: 'var(--p-ink)' }}>{c.hm}</span>
    </span>
  );
  return (
    <div style={{ display: 'flex', gap: 12, padding: '13px 0', borderBottom: '1px solid var(--p-border)' }}>
      <span style={{ width: 40, height: 40, borderRadius: 12, flexShrink: 0, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 19, background: tint }}>{m.emoji}</span>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, letterSpacing: '0.05em', textTransform: 'uppercase', color: 'var(--p-faint)' }}>{m.ru}</span>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15, color: 'var(--p-ink)' }}>{valueLabel}</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 6, flexWrap: 'wrap' }}>
          {clk(data.begin, 'с')}
          <span style={{ color: 'var(--p-faint)', fontSize: 13 }}>→</span>
          {clk(data.end, 'до')}
        </div>
        <div style={{ display: 'inline-flex', alignItems: 'center', gap: 4, marginTop: 7, padding: '2px 9px', borderRadius: 999, background: tint, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11, color: pop }}>далее {data.next}</div>
        <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--p-mute)', marginTop: 7, lineHeight: 1.45, textWrap: 'pretty' }}>{m.desc}</div>
      </div>
    </div>
  );
}

function PanchangSheet({ host, date, onClose, onOpenEkadashi }) {
  if (!host) return null;
  const p = window.PANCHANG.for(date);
  const tm = window.PANCHANG.times(date);
  const C = window.CAL;
  const ritu = p.ritu;
  const rPop = vP[ritu.tone], rTint = vT[ritu.tone];
  const fmtD = (d) => `${d.getDate()} ${C.RU_MONTH_GEN[d.getMonth()]}, ${C.RU_WD_LONG[C.monIdx(d)].toLowerCase()}`;
  return vWrapSheet(host, onClose, (
    <div style={{ ...window.CALUI.card, maxHeight: '88vh', overflowY: 'auto', WebkitOverflowScrolling: 'touch' }}>
      <div style={window.CALUI.grab} />
      {/* лунная шапка */}
      <div style={{ margin: '4px 16px 0', borderRadius: 18, padding: '16px', background: 'var(--moon-lit)', display: 'flex', alignItems: 'center', gap: 13, boxShadow: 'inset 0 0 0 1px var(--moon-line)' }}>
        <MoonBadge size={54} illum={p.illum} waxing={p.waxing} />
        <div style={{ minWidth: 0, flex: 1 }}>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, letterSpacing: '0.05em', textTransform: 'uppercase', color: 'var(--p-faint)' }}>Панчанга дня</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginTop: 3, flexWrap: 'wrap' }}>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', lineHeight: 1.1 }}>{p.tName}</span>
            <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 10.5, color: p.paksha === 'shukla' ? vP.sun : vP.grape, background: p.paksha === 'shukla' ? vT.sun : vT.grape, padding: '2px 9px', borderRadius: 999 }}>{p.pakshaName}</span>
          </div>
          <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--p-mute)', marginTop: 4 }}>{p.phase.sym} {p.phase.ru} · {fmtD(date)}</div>
        </div>
      </div>
      <div style={{ padding: '14px 18px 18px' }}>
        {/* сезон (риту) */}
        <div style={{ display: 'flex', gap: 13, padding: '14px', borderRadius: 16, background: rTint, marginBottom: 4 }}>
          <span style={{ fontSize: 30, lineHeight: 1 }}>{ritu.emoji}</span>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
              <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 9.5, letterSpacing: '0.05em', textTransform: 'uppercase', color: rPop }}>Сезон · риту</span>
            </div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, marginTop: 2 }}>
              <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)' }}>{ritu.ru}</span>
              <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--p-mute)' }}>{ritu.season} · {ritu.sa}</span>
            </div>
            <div style={{ fontSize: 11.5, fontWeight: 700, color: rPop, marginTop: 3 }}>Месяцы: {ritu.months}</div>
            <div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--p-soft)', marginTop: 6, lineHeight: 1.5, textWrap: 'pretty' }}>{ritu.note}</div>
          </div>
        </div>

        {/* заголовок секции */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 7, margin: '18px 0 2px' }}>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12, letterSpacing: 0.4, textTransform: 'uppercase', color: 'var(--p-faint)' }}>Начало и конец</span>
          <span style={{ flex: 1, height: 1, background: 'var(--p-border)' }} />
        </div>

        <PanElementRow k="tithi" data={tm.tithi} valueLabel={`${p.tName} · ${p.tNum}-е`} />
        <PanElementRow k="nak" data={tm.nak} valueLabel={`${p.nak} · пада ${p.pada}`} />
        <PanElementRow k="yoga" data={tm.yoga} valueLabel={p.yoga} />
        <PanElementRow k="karana" data={tm.karana} valueLabel={p.karana} />

        {p.isEkadashi && (
          <button className="tap pbtn" onClick={onOpenEkadashi} style={{ display: 'flex', alignItems: 'center', gap: 11, width: '100%', textAlign: 'left', cursor: 'pointer', border: '1.5px solid var(--eka-ring)', borderRadius: 15, padding: '12px 14px', marginTop: 14, background: 'var(--eka-soft)' }}>
            <span style={{ fontSize: 20 }}>🪷</span>
            <span style={{ flex: 1, minWidth: 0 }}>
              <span style={{ display: 'block', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 14.5, color: 'var(--p-ink)' }}>Сегодня экадаши · {p.ekadashi.ru}</span>
              <span style={{ display: 'block', fontSize: 12, fontWeight: 700, color: 'var(--eka)', marginTop: 1 }}>День поста и практики — открыть</span>
            </span>
            <IconChevron size={16} style={{ color: 'var(--eka)', flexShrink: 0 }} />
          </button>
        )}

        <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--p-faint)', marginTop: 14, lineHeight: 1.5, textAlign: 'center' }}>Времена указаны по московскому времени (МСК). Расчёт упрощённый — для точных мухурт сверяйтесь с местной панчангой.</div>
      </div>
    </div>
  ));
}

Object.assign(window, { PanchangBar, EkadashiSheet, MoonBadge, PanchangSheet });
