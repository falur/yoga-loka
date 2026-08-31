// calendar-views.jsx — три вида календаря: Месяц / Неделя / День
// Экспорт в window: MonthGrid, WeekGrid, DayTimeline, TONE, classColor

const { useState: uCv } = React;
const { POP: cPOP, TINT: cTINT } = window;

// цвет занятия по тону (как в «Занятиях»): a=коралл, b=небо, c=мята
const TONE = {
  a: { pop: cPOP.coral, tint: cTINT.coral },
  b: { pop: cPOP.sky, tint: cTINT.sky },
  c: { pop: cPOP.mint, tint: cTINT.mint },
};
const classColor = (c) => (TONE[c.tone] || TONE.a);
const LEAF = 'var(--c-leaf)';

const { keyOf: kOf, sameDay: sDay, monIdx: mIdx, fmtMin: fMin, RU_WD_SHORT: WD, RU_MONTH_GEN: MG } = window.CAL;

/* классы на конкретный день, отсортированные по времени */
const classesOn = (classes, d) => classes.filter((c) => c.date === kOf(d)).sort((a, b) => a.startMin - b.startMin);
const todosOn = (todos, d) => todos.filter((t) => t.date === kOf(d));

/* ── МЕСЯЦ — рамки по дням, фоновая фаза Луны, экадаши ───────────── */
function MonthGrid({ viewDate, selected, today, onSelect, classes, todos }) {
  const cells = window.CAL.monthMatrix(viewDate);
  const mo = viewDate.getMonth();
  return (
    <div style={{ padding: '0 12px' }}>
      {/* шапка дней недели */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', marginBottom: 6 }}>
        {WD.map((w, i) => (
          <div key={w} style={{ textAlign: 'center', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11, letterSpacing: '0.04em', color: i > 4 ? cPOP.coral : 'var(--p-faint)', padding: '2px 0' }}>{w}</div>
        ))}
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 5 }}>
        {cells.map((d) => {
          const inMonth = d.getMonth() === mo;
          const isToday = sDay(d, today);
          const isSel = sDay(d, selected);
          const cs = classesOn(classes, d);
          const ts = todosOn(todos, d);
          const dots = [...cs.map((c) => classColor(c).pop), ...(ts.length ? [cPOP.sky] : [])].slice(0, 3);
          const pan = window.PANCHANG.for(d);
          const isEka = pan.isEkadashi;
          const isFull = pan.phase.key === 'full';
          const isNew = pan.phase.key === 'new';
          // рамка ячейки
          let ring = '1px solid var(--p-border)';
          let boxShadow = 'none';
          if (isSel) { ring = `1.5px solid ${LEAF}`; boxShadow = '0 4px 14px -8px rgba(40,20,60,0.4)'; }
          else if (isEka) ring = '1.5px solid var(--eka-ring)';
          const cellBg = isSel ? 'var(--t-leaf)' : (isEka ? 'var(--eka-soft)' : 'var(--p-elev)');
          return (
            <button key={kOf(d)} className="tap pbtn" onClick={() => onSelect(d)} style={{
              position: 'relative', overflow: 'hidden', cursor: 'pointer', borderRadius: 13,
              border: ring, boxShadow, padding: '5px 0', minHeight: 62,
              display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
              background: cellBg, opacity: inMonth ? 1 : 0.4, transition: 'border-color .14s ease, background .14s ease',
            }}>
              {/* фоновая Луна в своей фазе */}
              <span style={{ position: 'absolute', left: '50%', top: '50%', transform: 'translate(-50%,-50%)', pointerEvents: 'none', lineHeight: 0 }}>
                <window.MoonPhase size={isFull ? 50 : 46} illum={pan.illum} waxing={pan.waxing} strokeW={isNew ? 1.6 : 1} />
              </span>
              {/* метка экадаши */}
              {isEka && (
                <span style={{ position: 'absolute', top: 3, right: 4, width: 14, height: 14, borderRadius: '50%', background: 'var(--eka)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontSize: 8, lineHeight: 1, color: '#fff', fontWeight: 900, fontFamily: "'Quicksand', sans-serif", boxShadow: '0 1px 4px -1px rgba(199,127,10,0.7)' }}>е</span>
              )}
              {/* число */}
              <span style={{
                position: 'relative', width: 25, height: 25, borderRadius: '50%', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                fontFamily: "'Quicksand', sans-serif", fontWeight: isToday || isSel || isEka ? 800 : 700, fontSize: 14,
                background: isToday ? LEAF : 'transparent',
                color: isToday ? '#fff' : (isEka ? 'var(--eka)' : 'var(--p-ink)'),
                textShadow: isToday ? 'none' : '0 0 4px var(--p-elev), 0 0 4px var(--p-elev), 0 1px 1px var(--p-elev)',
              }}>{d.getDate()}</span>
              {/* точки событий */}
              <span style={{ position: 'absolute', left: '50%', bottom: 5, transform: 'translateX(-50%)', display: 'flex', gap: 3, height: 5, alignItems: 'center' }}>
                {dots.map((c, i) => (
                  <span key={i} style={{ width: 5, height: 5, borderRadius: '50%', background: c, boxShadow: '0 0 0 1.5px var(--p-elev)' }} />
                ))}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* ── общий таймлайн-каркас (часы) ───────────────────────────────── */
const H_START = 6, H_END = 23;            // 06:00 … 23:00
const HOURS = Array.from({ length: H_END - H_START }, (_, i) => H_START + i);
const NOW_MIN = 9 * 60 + 20;              // «сейчас» для подсветки сегодняшнего дня

function classBlockStyle(c, pxPerHour) {
  const top = ((c.startMin - H_START * 60) / 60) * pxPerHour;
  const h = Math.max(22, (c.durMin / 60) * pxPerHour - 3);
  return { top, height: h };
}

/* ── НЕДЕЛЯ — сетка 7 колонок × часы ────────────────────────────── */
function WeekGrid({ viewDate, selected, today, onSelect, classes, onOpenClass }) {
  const days = window.CAL.weekDays(viewDate);
  const PPH = 46;                          // px на час
  const gutter = 30;
  const gridH = HOURS.length * PPH;
  return (
    <div style={{ padding: '0 10px' }}>
      {/* шапка дней */}
      <div style={{ display: 'flex', paddingLeft: gutter, marginBottom: 6 }}>
        {days.map((d) => {
          const isToday = sDay(d, today);
          const isSel = sDay(d, selected);
          return (
            <button key={kOf(d)} className="tap pbtn" onClick={() => onSelect(d)} style={{
              flex: 1, minWidth: 0, border: 'none', cursor: 'pointer', background: 'transparent',
              display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3, padding: '2px 0',
            }}>
              <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 10, color: mIdx(d) > 4 ? cPOP.coral : 'var(--p-faint)' }}>{WD[mIdx(d)]}</span>
              <span style={{
                width: 26, height: 26, borderRadius: '50%', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13,
                background: isToday ? LEAF : (isSel ? 'var(--t-leaf)' : 'transparent'),
                color: isToday ? '#fff' : 'var(--p-ink)',
                boxShadow: isSel && !isToday ? `inset 0 0 0 1.5px ${LEAF}` : 'none',
              }}>{d.getDate()}</span>
            </button>
          );
        })}
      </div>
      {/* сетка часов */}
      <div style={{ position: 'relative', height: gridH }}>
        {/* линии часов + подписи */}
        {HOURS.map((h, i) => (
          <div key={h} style={{ position: 'absolute', left: 0, right: 0, top: i * PPH, height: PPH }}>
            <span style={{ position: 'absolute', left: 0, top: -6, width: gutter - 6, textAlign: 'right', fontSize: 9.5, fontWeight: 700, color: 'var(--p-faint)', fontFamily: "'Quicksand', sans-serif" }}>{fMin(h * 60)}</span>
            <div style={{ position: 'absolute', left: gutter, right: 0, top: 0, borderTop: '1px solid var(--p-border)' }} />
          </div>
        ))}
        {/* колонки */}
        <div style={{ position: 'absolute', left: gutter, right: 0, top: 0, bottom: 0, display: 'flex' }}>
          {days.map((d) => {
            const cs = classesOn(classes, d);
            const isSel = sDay(d, selected);
            return (
              <div key={kOf(d)} onClick={() => onSelect(d)} style={{ flex: 1, minWidth: 0, position: 'relative', borderLeft: '1px solid var(--p-border)', background: isSel ? 'var(--t-leaf)' : 'transparent' }}>
                {cs.map((c) => {
                  const col = classColor(c);
                  const bs = classBlockStyle(c, PPH);
                  return (
                    <button key={c.id} className="tap pbtn" onClick={(e) => { e.stopPropagation(); onOpenClass(c); }} style={{
                      position: 'absolute', top: bs.top, left: 2, right: 2, height: bs.height, overflow: 'hidden',
                      border: 'none', cursor: 'pointer', borderRadius: 6, padding: '2px 3px', textAlign: 'left',
                      background: col.tint, borderLeft: `2.5px solid ${col.pop}`,
                      display: 'flex', flexDirection: 'column', gap: 1,
                    }}>
                      <span style={{ fontSize: 10, lineHeight: 1 }}>{c.glyph}</span>
                      <span style={{ fontSize: 8.5, fontWeight: 800, color: col.pop, fontFamily: "'Quicksand', sans-serif", lineHeight: 1 }}>{c.time}</span>
                    </button>
                  );
                })}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}

/* ── ДЕНЬ — вертикальный таймлайн ───────────────────────────────── */
function DayTimeline({ selected, today, classes, onOpenClass }) {
  const PPH = 60;
  const gutter = 46;
  const gridH = HOURS.length * PPH;
  const cs = classesOn(classes, selected);
  const showNow = sDay(selected, today);
  const nowTop = ((NOW_MIN - H_START * 60) / 60) * PPH;
  return (
    <div style={{ padding: '0 14px' }}>
      {cs.length === 0 && (
        <div style={{ textAlign: 'center', padding: '6px 20px 14px' }}>
          <div style={{ fontSize: 34 }}>🌿</div>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 15, color: 'var(--p-ink)', marginTop: 6 }}>Свободный день</div>
          <div style={{ fontSize: 12.5, color: 'var(--p-mute)', marginTop: 3 }}>Нет записанных занятий — можно отдохнуть или добавить практику.</div>
        </div>
      )}
      <div style={{ position: 'relative', height: gridH }}>
        {HOURS.map((h, i) => (
          <div key={h} style={{ position: 'absolute', left: 0, right: 0, top: i * PPH, height: PPH }}>
            <span style={{ position: 'absolute', left: 0, top: -7, width: gutter - 8, textAlign: 'right', fontSize: 10.5, fontWeight: 700, color: 'var(--p-faint)', fontFamily: "'Quicksand', sans-serif" }}>{fMin(h * 60)}</span>
            <div style={{ position: 'absolute', left: gutter, right: 0, top: 0, borderTop: '1px solid var(--p-border)' }} />
          </div>
        ))}
        {/* блоки занятий */}
        <div style={{ position: 'absolute', left: gutter, right: 0, top: 0, bottom: 0 }}>
          {cs.map((c) => {
            const col = classColor(c);
            const bs = classBlockStyle(c, PPH);
            return (
              <button key={c.id} className="tap pbtn" onClick={() => onOpenClass(c)} style={{
                position: 'absolute', top: bs.top, left: 4, right: 2, height: bs.height, overflow: 'hidden',
                border: 'none', cursor: 'pointer', borderRadius: 12, padding: '7px 11px', textAlign: 'left',
                background: col.tint, borderLeft: `3px solid ${col.pop}`,
                display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 2,
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                  <span style={{ fontSize: 13 }}>{c.glyph}</span>
                  <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13.5, color: 'var(--p-ink)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.title}</span>
                </div>
                <div style={{ fontSize: 11.5, fontWeight: 700, color: col.pop }}>{c.time}–{fMin(c.endMin)} · {c.teacher}</div>
              </button>
            );
          })}
          {showNow && (
            <div style={{ position: 'absolute', left: -6, right: 0, top: nowTop, display: 'flex', alignItems: 'center', pointerEvents: 'none' }}>
              <span style={{ width: 8, height: 8, borderRadius: '50%', background: cPOP.coral, flexShrink: 0 }} />
              <span style={{ flex: 1, height: 2, background: cPOP.coral, opacity: 0.7 }} />
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { MonthGrid, WeekGrid, DayTimeline, TONE, classColor });
