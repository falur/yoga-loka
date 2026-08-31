// sangat-messages.jsx — нижняя навигация, личные сообщения (директ) и просмотр историй
// Зависит от sangat.jsx (SAvatar, sPerson), sangat-data.jsx (SANGAT_CHATS),
//          playful.jsx (POP/TINT/CTRL/PPhoto/PImg/avaFor/AVA_SELF), icons.jsx.
// Экспорт в window: SBottomNav, SMessages, SStoryViewer

const { useState: uSm, useEffect: uEf, useRef: uRf } = React;

/* ── нижняя навигация: активна «Сангат» ── */
const S_NAV = [
  { key: 'practice', label: 'Моя практика', emoji: '🌀', pop: '#E8615A', soft: 'rgba(232,97,90,0.16)' },
  { key: 'classes', label: 'Занятия', emoji: '🧘', pop: '#E8902F', soft: 'rgba(232,144,47,0.16)' },
  { key: 'calendar', label: 'Календарь', emoji: '📅', pop: '#4FA85B', soft: 'rgba(79,168,91,0.16)' },
  { key: 'sangat', label: 'Сангат', emoji: '❤️', pop: '#3E92D8', soft: 'rgba(62,146,216,0.16)' },
  { key: 'ahamkara', label: 'Ахамкара', emoji: '🪬', pop: '#8E55D8', soft: 'rgba(142,85,216,0.16)' },
];
function SBottomNav({ active: activeProp, onNav } = {}) {
  const [activeState, setActiveState] = uSm('sangat');
  const active = activeProp != null ? activeProp : activeState;
  const setActive = onNav || setActiveState;
  return (
    <div style={{
      position: 'absolute', bottom: 0, left: 0, right: 0, zIndex: 6,
      backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)',
      borderTop: '1px solid var(--p-border)', display: 'flex', alignItems: 'stretch', justifyContent: 'space-around',
      padding: '8px 4px 24px',
    }}>
      {S_NAV.map(({ key, label, emoji, pop, soft }) => {
        const on = active === key;
        return (
          <button key={key} className="tap pbtn" onClick={() => setActive(key)} style={{
            flex: 1, minWidth: 0, border: 'none', background: 'transparent', cursor: 'pointer',
            display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 5, padding: '2px 1px',
          }}>
            <span style={{
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 36, height: 36, borderRadius: '50%',
              backgroundColor: on ? soft : 'transparent', transform: on ? 'translateY(-1px)' : 'none',
              transition: 'background .18s ease, transform .18s ease',
            }}>
              <span style={{ fontSize: 19, lineHeight: 1, filter: on ? 'none' : 'saturate(0.8) opacity(0.6)' }}>{emoji}</span>
            </span>
            <span style={{
              fontFamily: "'Nunito Sans', sans-serif", fontSize: 9.5, lineHeight: 1, letterSpacing: '-0.1px',
              fontWeight: on ? 800 : 600, whiteSpace: 'nowrap', color: on ? pop : 'var(--p-mute)', transition: 'color .18s ease',
            }}>{label}</span>
          </button>
        );
      })}
    </div>
  );
}

/* ════════ ЛИЧНЫЕ СООБЩЕНИЯ (директ) ════════ */
const IconBackS = ({ size = 24, style }) =>
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={style}><path d="m15 5-7 7 7 7" /></svg>;
const IconPhone = ({ size = 22, style }) =>
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" style={style}><path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 5 5L19 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z" /></svg>;
const IconVideoCam = ({ size = 22, style }) =>
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" style={style}><rect x="2.5" y="6" width="13" height="12" rx="3" /><path d="M15.5 10.5 21.5 7v10l-6-3.5Z" /></svg>;

/* строка диалога в списке */
function SChatRow({ chat, onOpen }) {
  const p = window.sPerson(chat.who);
  const last = chat.msgs[chat.msgs.length - 1];
  const preview = (last.from === 'me' ? 'Вы: ' : '') + last.text;
  const unread = chat.unread > 0;
  return (
    <button className="tap" onClick={() => onOpen(chat)} style={{
      display: 'flex', alignItems: 'center', gap: 13, width: '100%', border: 'none', background: 'none',
      cursor: 'pointer', padding: '10px 16px', textAlign: 'left',
    }}>
      <div style={{ position: 'relative', flexShrink: 0 }}>
        <window.SAvatar who={chat.who} size={56} />
        {chat.online &&
          <span style={{ position: 'absolute', right: 1, bottom: 1, width: 14, height: 14, borderRadius: '50%', backgroundColor: POP.leaf, border: '2.5px solid var(--p-bg)' }} />}
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)' }}>{p.name}</span>
          {p.verified && <span style={{ color: POP.sky, display: 'inline-flex' }}><IconVerified size={12} /></span>}
        </div>
        <div style={{
          fontSize: 13, marginTop: 2, color: unread ? 'var(--p-ink)' : 'var(--p-mute)', fontWeight: unread ? 700 : 500,
          whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
        }}>{preview} · <span style={{ color: 'var(--p-faint)', fontWeight: 500 }}>{chat.time}</span></div>
      </div>
      {unread
        ? <span style={{ width: 11, height: 11, borderRadius: '50%', backgroundColor: POP.sky, flexShrink: 0 }} />
        : <IconVideoCam size={22} style={{ color: 'var(--p-faint)', flexShrink: 0 }} />}
    </button>
  );
}

/* окно диалога */
function SChatThread({ chat, onBack }) {
  const p = window.sPerson(chat.who);
  const [msgs, setMsgs] = uSm(() => chat.msgs.map((m) => ({ ...m })));
  const [draft, setDraft] = uSm('');
  const scrollRef = uRf(null);
  uEf(() => { const el = scrollRef.current; if (el) el.scrollTop = el.scrollHeight; }, [msgs]);
  const send = () => {
    const t = draft.trim(); if (!t) return;
    setMsgs((m) => [...m, { from: 'me', text: t, time: 'сейчас' }]);
    setDraft('');
  };
  return (
    <div style={{ position: 'absolute', inset: 0, zIndex: 2, backgroundColor: 'var(--p-bg)', display: 'flex', flexDirection: 'column', animation: 'sslideR .26s cubic-bezier(.2,.9,.25,1) both' }}>
      {/* шапка */}
      <div style={{
        flexShrink: 0, paddingTop: 48, padding: '48px 12px 10px', display: 'flex', alignItems: 'center', gap: 10,
        backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)',
      }}>
        <button className="picon tap" onClick={onBack} style={{ color: 'var(--p-ink)', width: 32 }}><IconBackS size={24} /></button>
        <div style={{ position: 'relative', flexShrink: 0 }}>
          <window.SAvatar who={chat.who} size={38} />
          {chat.online && <span style={{ position: 'absolute', right: -1, bottom: -1, width: 11, height: 11, borderRadius: '50%', backgroundColor: POP.leaf, border: '2px solid var(--p-bg)' }} />}
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 15, color: 'var(--p-ink)' }}>
            {p.name}{p.verified && <span style={{ color: POP.sky, display: 'inline-flex' }}><IconVerified size={12} /></span>}
          </div>
          <div style={{ fontSize: 11.5, fontWeight: 600, color: chat.online ? POP.leaf : 'var(--p-faint)' }}>{chat.online ? 'в сети' : 'был(а) недавно'}</div>
        </div>
        <button className="picon tap" style={{ color: 'var(--p-ink)' }}><IconPhone size={21} /></button>
        <button className="picon tap" style={{ color: 'var(--p-ink)' }}><IconVideoCam size={22} /></button>
      </div>

      {/* сообщения */}
      <div ref={scrollRef} style={{ flex: 1, overflowY: 'auto', scrollbarWidth: 'none', padding: '16px 14px 10px' }}>
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8, padding: '6px 0 18px' }}>
          <window.SAvatar who={chat.who} size={68} story seen />
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 17, color: 'var(--p-ink)' }}>{p.name}</div>
          <div style={{ fontSize: 12.5, color: 'var(--p-mute)' }}>{p.handle} · Yoga Loka · Сангат</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 7 }}>
          {msgs.map((m, i) => {
            const mine = m.from === 'me';
            return (
              <div key={i} style={{ display: 'flex', justifyContent: mine ? 'flex-end' : 'flex-start', gap: 8 }}>
                {!mine && <window.SAvatar who={chat.who} size={26} />}
                <div style={{
                  maxWidth: '74%', padding: '9px 14px', borderRadius: 20, fontSize: 14, lineHeight: 1.4, textWrap: 'pretty',
                  backgroundColor: mine ? POP.sky : 'var(--p-ctrl)', color: mine ? '#fff' : 'var(--p-soft)',
                  borderBottomRightRadius: mine ? 6 : 20, borderBottomLeftRadius: mine ? 20 : 6,
                }}>{m.text}
                  <div style={{ fontSize: 10, marginTop: 3, opacity: 0.7, color: mine ? '#fff' : 'var(--p-faint)' }}>{m.time}</div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* ввод */}
      <div style={{ flexShrink: 0, display: 'flex', alignItems: 'center', gap: 9, padding: '10px 14px 28px', borderTop: '1px solid var(--p-border)', backgroundColor: 'var(--p-elev)' }}>
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 8, border: '1.5px solid var(--p-border)', borderRadius: 999, padding: '6px 6px 6px 16px', background: 'var(--p-ctrl)' }}>
          <input value={draft} onChange={(e) => setDraft(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && send()}
            placeholder="Сообщение…" style={{ flex: 1, border: 'none', background: 'none', outline: 'none', fontFamily: "'Nunito Sans', sans-serif", fontSize: 14, color: 'var(--p-ink)', minWidth: 0 }} />
          <span style={{ fontSize: 18 }}>🙏</span>
          <button className="pbtn" onClick={send} disabled={!draft.trim()} style={{
            width: 34, height: 34, borderRadius: '50%', border: 'none', flexShrink: 0, cursor: draft.trim() ? 'pointer' : 'default',
            opacity: draft.trim() ? 1 : 0.4, backgroundColor: POP.sky, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
          }}><IconSend size={16} /></button>
        </div>
      </div>
    </div>
  );
}

/* оверлей сообщений: список ↔ диалог */
function SMessages({ host, onClose, initialChat }) {
  const [chats] = uSm(() => JSON.parse(JSON.stringify(window.SANGAT_CHATS)));
  const [open, setOpen] = uSm(null); // активный chat
  uEf(() => { if (initialChat) { const c = chats.find((x) => x.who === initialChat); if (c) setOpen(c); } }, []);
  const [q, setQ] = uSm('');
  const list = chats.filter((c) => { const p = window.sPerson(c.who); return !q || p.name.toLowerCase().includes(q.toLowerCase()) || p.handle.includes(q.toLowerCase()); });

  return ReactDOM.createPortal(
    <div style={{ position: 'absolute', inset: 0, zIndex: 45, backgroundColor: 'var(--p-bg)', animation: 'sslideR .28s cubic-bezier(.2,.9,.25,1)', overflow: 'hidden' }}>
      {/* шапка списка */}
      <div style={{
        position: 'absolute', top: 0, left: 0, right: 0, zIndex: 2, padding: '48px 12px 10px',
        display: 'flex', alignItems: 'center', gap: 8, backgroundColor: 'var(--p-chrome)',
        backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)',
      }}>
        <button className="picon tap" onClick={onClose} style={{ color: 'var(--p-ink)', width: 32 }}><IconBackS size={24} /></button>
        <div style={{ flex: 1, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)' }}>Сообщения</div>
        <button className="picon tap" style={{ color: 'var(--p-ink)' }}><IconDM size={22} /></button>
      </div>

      <div className="pscroll" style={{ paddingTop: 92 }}>
        {/* поиск */}
        <div style={{ padding: '8px 16px 10px' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, height: 40, borderRadius: 13, backgroundColor: 'var(--p-ctrl)', padding: '0 14px' }}>
            <IconSearch size={18} style={{ color: 'var(--p-faint)' }} />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Поиск" style={{ flex: 1, border: 'none', background: 'none', outline: 'none', fontFamily: "'Nunito Sans', sans-serif", fontSize: 14, color: 'var(--p-ink)', minWidth: 0 }} />
          </div>
        </div>
        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13, color: 'var(--p-mute)', padding: '4px 16px 4px' }}>Сообщения</div>
        {list.map((c) => <SChatRow key={c.id} chat={c} onOpen={setOpen} />)}
        {list.length === 0 &&
          <div style={{ textAlign: 'center', padding: '40px 24px', color: 'var(--p-mute)' }}>Ничего не найдено</div>}
      </div>

      {open && <SChatThread chat={open} onBack={() => setOpen(null)} />}
    </div>, host);
}

/* ════════ ПОИСК (люди · записи) ════════ */
function SSearchPersonRow({ pkey, onOpen }) {
  const p = window.sPerson(pkey);
  return (
    <button className="tap" onClick={() => onOpen(pkey)} style={{
      display: 'flex', alignItems: 'center', gap: 13, width: '100%', border: 'none', background: 'none',
      cursor: 'pointer', padding: '9px 16px', textAlign: 'left',
    }}>
      <window.SAvatar who={pkey} size={48} />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)' }}>{p.handle}</span>
          {p.verified && <span style={{ color: POP.sky, display: 'inline-flex' }}><IconVerified size={12} /></span>}
        </div>
        <div style={{ fontSize: 12.5, marginTop: 1, color: 'var(--p-mute)', fontWeight: 500, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
          {p.name}{p.spiritual ? ` · ${p.spiritual}` : ''}
        </div>
      </div>
    </button>
  );
}

function SSearchPostRow({ post, onOpen }) {
  const p = window.sPerson(post.who);
  const TYPE = { photo: '🖼️', video: '🎬', audio: '🎧', text: '✍️', class: '🧘' };
  const thumb = post.image || (post.klass && post.klass.image);
  return (
    <button className="tap" onClick={() => onOpen(post)} style={{
      display: 'flex', alignItems: 'center', gap: 12, width: '100%', border: 'none', background: 'none',
      cursor: 'pointer', padding: '9px 16px', textAlign: 'left',
    }}>
      <div style={{ width: 50, height: 50, borderRadius: 13, overflow: 'hidden', flexShrink: 0, backgroundColor: 'var(--p-ctrl)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        {thumb ? <PImg src={REAL_IMG[thumb] || thumb} /> : <span style={{ fontSize: 22 }}>{TYPE[post.type] || '✍️'}</span>}
      </div>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5, color: 'var(--p-ink)' }}>{p.handle}</div>
        <div style={{ fontSize: 12.5, marginTop: 1, color: 'var(--p-mute)', fontWeight: 500, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{post.text || (post.klass && post.klass.title)}</div>
      </div>
    </button>
  );
}

function SSearch({ host, onClose, onOpenStory }) {
  const [q, setQ] = uSm('');
  const inputRef = uRf(null);
  uEf(() => { const t = setTimeout(() => inputRef.current && inputRef.current.focus(), 120); return () => clearTimeout(t); }, []);

  const ql = q.trim().toLowerCase();
  const people = window.SANGAT_PEOPLE || {};
  const feed = window.SANGAT_FEED || [];

  const peopleHits = Object.keys(people).filter((k) => k !== 'you').filter((k) => {
    if (!ql) return false;
    const p = people[k];
    return (p.name + ' ' + p.handle + ' ' + (p.spiritual || '')).toLowerCase().includes(ql);
  });
  const postHits = !ql ? [] : feed.filter((p) => {
    const hay = (p.text || '') + ' ' + (p.tags || []).join(' ') + ' ' + (p.place || '') + ' ' + (p.klass ? p.klass.title + ' ' + p.klass.desc : '');
    return hay.toLowerCase().includes(ql);
  });

  const allTags = [...new Set(feed.flatMap((p) => p.tags || []))];
  const tagHits = ql ? allTags.filter((t) => t.includes(ql)) : allTags;
  const suggestPeople = Object.keys(people).filter((k) => k !== 'you').slice(0, 6);

  const noResults = ql && peopleHits.length === 0 && postHits.length === 0 && tagHits.length === 0;
  const sectionLbl = { fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12.5, letterSpacing: '0.02em', textTransform: 'uppercase', color: 'var(--p-mute)', padding: '14px 16px 4px' };

  return ReactDOM.createPortal(
    <div style={{ position: 'absolute', inset: 0, zIndex: 45, backgroundColor: 'var(--p-bg)', animation: 'sslideR .28s cubic-bezier(.2,.9,.25,1)', overflow: 'hidden' }}>
      {/* шапка с полем поиска */}
      <div style={{
        position: 'absolute', top: 0, left: 0, right: 0, zIndex: 2, padding: '48px 12px 10px',
        display: 'flex', alignItems: 'center', gap: 8, backgroundColor: 'var(--p-chrome)',
        backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)',
      }}>
        <button className="picon tap" onClick={onClose} style={{ color: 'var(--p-ink)', width: 32 }}><IconBackS size={24} /></button>
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 8, height: 40, borderRadius: 13, backgroundColor: 'var(--p-ctrl)', padding: '0 14px' }}>
          <IconSearch size={18} style={{ color: 'var(--p-faint)', flexShrink: 0 }} />
          <input ref={inputRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Поиск людей и записей"
            style={{ flex: 1, border: 'none', background: 'none', outline: 'none', fontFamily: "'Nunito Sans', sans-serif", fontSize: 14.5, color: 'var(--p-ink)', minWidth: 0 }} />
          {q &&
            <button className="tap" onClick={() => { setQ(''); inputRef.current && inputRef.current.focus(); }} aria-label="Очистить"
              style={{ border: 'none', background: 'var(--p-border)', color: 'var(--p-mute)', width: 20, height: 20, borderRadius: '50%', cursor: 'pointer', fontSize: 13, lineHeight: 1, flexShrink: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>×</button>}
        </div>
      </div>

      <div className="pscroll" style={{ paddingTop: 104, paddingBottom: 40 }}>
        {/* пустой запрос — подсказки */}
        {!ql &&
          <>
            <div style={sectionLbl}>Люди сообщества</div>
            {suggestPeople.map((k) => <SSearchPersonRow key={k} pkey={k} onOpen={() => {}} />)}
            <div style={sectionLbl}>Популярные темы</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, padding: '4px 16px 8px' }}>
              {tagHits.map((t) =>
                <button key={t} className="tap" onClick={() => setQ(t)} style={{
                  border: 'none', cursor: 'pointer', padding: '8px 14px', borderRadius: 999, backgroundColor: 'var(--p-ctrl)',
                  color: POP.sky, fontFamily: "'Nunito Sans', sans-serif", fontSize: 13, fontWeight: 700,
                }}>#{t}</button>)}
            </div>
          </>}

        {/* результаты */}
        {ql && peopleHits.length > 0 &&
          <>
            <div style={sectionLbl}>Люди</div>
            {peopleHits.map((k) => <SSearchPersonRow key={k} pkey={k} onOpen={() => {}} />)}
          </>}

        {ql && tagHits.length > 0 &&
          <>
            <div style={sectionLbl}>Темы</div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, padding: '4px 16px 8px' }}>
              {tagHits.map((t) =>
                <button key={t} className="tap" onClick={() => setQ(t)} style={{
                  border: 'none', cursor: 'pointer', padding: '8px 14px', borderRadius: 999, backgroundColor: 'var(--p-ctrl)',
                  color: POP.sky, fontFamily: "'Nunito Sans', sans-serif", fontSize: 13, fontWeight: 700,
                }}>#{t}</button>)}
            </div>
          </>}

        {ql && postHits.length > 0 &&
          <>
            <div style={sectionLbl}>Записи</div>
            {postHits.map((p) => <SSearchPostRow key={p.id} post={p} onOpen={onClose} />)}
          </>}

        {noResults &&
          <div style={{ textAlign: 'center', padding: '48px 28px', color: 'var(--p-mute)' }}>
            <div style={{ fontSize: 30 }}>🔍</div>
            <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, marginTop: 8, color: 'var(--p-soft)' }}>Ничего не найдено</div>
            <div style={{ fontSize: 13, marginTop: 4 }}>Попробуйте другое имя или тему</div>
          </div>}
      </div>
    </div>, host);
}

/* ════════ ПРОСМОТР ИСТОРИЙ ════════ */
function SStoryViewer({ host, story, all, onClose, onSeen }) {
  // порядок историй (только с кадрами), стартуем с выбранной
  const playable = (all || []).filter((s) => !s.add && s.slides && s.slides.length);
  const [si, setSi] = uSm(() => Math.max(0, playable.findIndex((s) => s.id === story.id)));
  const [ki, setKi] = uSm(0);
  const cur = playable[si];
  const slide = cur && cur.slides[ki];

  const next = () => {
    if (!cur) return;
    if (ki < cur.slides.length - 1) { setKi(ki + 1); return; }
    if (si < playable.length - 1) { const n = playable[si + 1]; onSeen && onSeen(n.id); setSi(si + 1); setKi(0); return; }
    onClose();
  };
  const prev = () => { if (ki > 0) setKi(ki - 1); else if (si > 0) { setSi(si - 1); setKi(playable[si - 1].slides.length - 1); } };

  // авто-перелистывание
  uEf(() => { const t = setTimeout(next, 4200); return () => clearTimeout(t); }, [si, ki]);
  uEf(() => { if (cur) onSeen && onSeen(cur.id); }, [si]);

  if (!cur || !slide) return null;
  const p = window.sPerson(cur.who);

  return ReactDOM.createPortal(
    <div style={{ position: 'absolute', inset: 0, zIndex: 60, background: '#0d0a14', animation: 'pfade .2s ease', overflow: 'hidden' }}>
      {/* кадр */}
      <PPhoto image={slide.image} height={'100%'} radius={0} />
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg, rgba(13,10,20,0.55) 0%, rgba(13,10,20,0) 22% 70%, rgba(13,10,20,0.6) 100%)' }} />

      {/* прогресс-бары */}
      <div style={{ position: 'absolute', top: 50, left: 12, right: 12, display: 'flex', gap: 5, zIndex: 3 }}>
        {cur.slides.map((_, i) => (
          <div key={i} style={{ flex: 1, height: 3, borderRadius: 999, background: 'rgba(255,255,255,0.35)', overflow: 'hidden' }}>
            <div style={{
              height: '100%', borderRadius: 999, background: '#fff',
              width: i < ki ? '100%' : i === ki ? '100%' : '0%',
              animation: i === ki ? 'sgrow 4.2s linear' : 'none',
            }} />
          </div>
        ))}
      </div>

      {/* шапка */}
      <div style={{ position: 'absolute', top: 62, left: 12, right: 12, display: 'flex', alignItems: 'center', gap: 10, zIndex: 3 }}>
        <window.SAvatar who={cur.who} size={36} />
        <div style={{ flex: 1, minWidth: 0, display: 'flex', alignItems: 'center', gap: 6 }}>
          <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, color: '#fff' }}>{p.handle}</span>
          {p.verified && <span style={{ color: '#fff', display: 'inline-flex' }}><IconVerified size={12} /></span>}
          <span style={{ fontSize: 12, color: 'rgba(255,255,255,0.75)', fontWeight: 600 }}>{slide.time}</span>
        </div>
        <button className="tap" onClick={onClose} aria-label="Закрыть" style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#fff', fontSize: 26, lineHeight: 1, padding: 4 }}>×</button>
      </div>

      {/* зоны тапа */}
      <button onClick={prev} aria-label="Назад" style={{ position: 'absolute', left: 0, top: 90, bottom: 90, width: '32%', border: 'none', background: 'transparent', cursor: 'pointer', zIndex: 2 }} />
      <button onClick={next} aria-label="Дальше" style={{ position: 'absolute', right: 0, top: 90, bottom: 90, width: '68%', border: 'none', background: 'transparent', cursor: 'pointer', zIndex: 2 }} />

      {/* подпись */}
      {slide.text &&
        <div style={{ position: 'absolute', left: 18, right: 18, bottom: 92, zIndex: 3, color: '#fff', fontFamily: "'Nunito Sans', sans-serif", fontSize: 16, fontWeight: 600, lineHeight: 1.4, textWrap: 'pretty', textShadow: '0 1px 12px rgba(0,0,0,0.5)' }}>{slide.text}</div>}

      {/* ответ */}
      <div style={{ position: 'absolute', left: 14, right: 14, bottom: 30, zIndex: 3, display: 'flex', alignItems: 'center', gap: 10 }}>
        <div style={{ flex: 1, display: 'flex', alignItems: 'center', height: 44, borderRadius: 999, border: '1.5px solid rgba(255,255,255,0.5)', padding: '0 18px', color: 'rgba(255,255,255,0.85)', fontSize: 14, fontWeight: 600 }}>
          Ответить {p.handle}…
        </div>
        <button className="tap" aria-label="Нравится" style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#fff' }}><IconHeart size={26} /></button>
        <button className="tap" aria-label="Отправить" style={{ border: 'none', background: 'none', cursor: 'pointer', color: '#fff' }}><IconDM size={24} /></button>
      </div>
    </div>, host);
}

Object.assign(window, { SBottomNav, SMessages, SStoryViewer, SChatThread, SSearch });

/* ════════ УВЕДОМЛЕНИЯ (экран «сердечко» — активность) ════════ */
const N_GROUPS = [
  ['new', 'Новое'], ['today', 'Сегодня'], ['week', 'На этой неделе'], ['earlier', 'Ранее'],
];

/* кнопка подписки в строке */
function SFollowBtn({ followsYou }) {
  const [on, setOn] = uSm(false);
  return (
    <button className="pbtn tap" onClick={() => setOn((v) => !v)} style={{
      flexShrink: 0, border: on ? '1.5px solid var(--p-border)' : 'none', cursor: 'pointer',
      padding: on ? '7px 14px' : '8px 16px', borderRadius: 11,
      fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 12.5, whiteSpace: 'nowrap',
      backgroundColor: on ? 'transparent' : POP.sky, color: on ? 'var(--p-ink)' : '#fff',
      transition: 'all .15s ease',
    }}>{on ? 'Вы подписаны' : (followsYou ? 'Подписаться в ответ' : 'Подписаться')}</button>
  );
}

/* подсветка @you в тексте уведомления */
function SNotifText({ handle, text, verified }) {
  const parts = (text || '').split('@you');
  return (
    <div style={{ fontSize: 13.5, lineHeight: 1.4, color: 'var(--p-soft)', textWrap: 'pretty' }}>
      <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, color: 'var(--p-ink)' }}>{handle}</span>
      {verified && <span style={{ color: POP.sky, display: 'inline-flex', verticalAlign: '-2px', margin: '0 1px 0 3px' }}><IconVerified size={12} /></span>}
      {' '}
      {parts.map((seg, i) => (
        <React.Fragment key={i}>
          {seg}
          {i < parts.length - 1 && <b style={{ color: POP.sky, fontWeight: 700 }}>@you</b>}
        </React.Fragment>
      ))}
    </div>
  );
}

/* одна строка уведомления */
function SNotifRow({ n }) {
  const p = window.sPerson(n.who);
  const isStudio = n.type === 'studio';
  const isFollow = n.type === 'follow';
  return (
    <div className="tap" style={{
      display: 'flex', alignItems: 'center', gap: 12, padding: '9px 16px', textAlign: 'left',
      backgroundColor: n._unseen ? 'var(--ctrl-accent-soft)' : 'transparent', transition: 'background .2s ease',
    }}>
      {/* аватар (для студии — кружок с эмодзи студии) */}
      <div style={{ position: 'relative', flexShrink: 0 }}>
        <window.SAvatar who={n.who} size={48} story={isStudio} seen />
        {!isStudio &&
          <span style={{
            position: 'absolute', right: -2, bottom: -2, width: 21, height: 21, borderRadius: '50%',
            backgroundColor:
              n.type === 'like' || n.type === 'likes' || n.type === 'story' ? POP.coral
              : n.type === 'comment' ? POP.sky
              : n.type === 'mention' ? POP.grape : POP.leaf,
            border: '2.5px solid var(--p-bg)', color: '#fff',
            display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11,
          }}>
            {n.type === 'like' || n.type === 'likes' ? <IconHeart size={11} filled />
              : n.type === 'story' ? <IconHeart size={11} filled />
              : n.type === 'comment' ? <IconComment size={11} />
              : n.type === 'mention' ? '@'
              : '＋'}
          </span>}
      </div>

      {/* текст */}
      <div style={{ flex: 1, minWidth: 0 }}>
        <SNotifText handle={p.handle} text={n.text || ''} verified={p.verified} />
        <div style={{ fontSize: 11.5, marginTop: 3, color: 'var(--p-faint)', fontWeight: 600 }}>{n.time}</div>
      </div>

      {/* справа: превью записи или кнопка подписки */}
      {isFollow
        ? <SFollowBtn followsYou={n.followsYou} />
        : n.thumb
          ? <div style={{ width: 46, height: 46, borderRadius: 10, overflow: 'hidden', flexShrink: 0, backgroundColor: 'var(--p-ctrl)' }}>
              <PImg src={REAL_IMG[n.thumb] || n.thumb} />
            </div>
          : isStudio
            ? <span style={{ fontSize: 22, flexShrink: 0 }}>🔔</span>
            : null}
    </div>
  );
}

/* оверлей уведомлений */
function SNotifications({ host, onClose }) {
  const [notifs] = uSm(() => {
    const raw = JSON.parse(JSON.stringify(window.SANGAT_NOTIFS || []));
    return raw.map((n) => ({ ...n, _unseen: n.group === 'new' }));
  });

  return ReactDOM.createPortal(
    <div style={{ position: 'absolute', inset: 0, zIndex: 45, backgroundColor: 'var(--p-bg)', animation: 'sslideR .28s cubic-bezier(.2,.9,.25,1)', overflow: 'hidden' }}>
      {/* шапка */}
      <div style={{
        position: 'absolute', top: 0, left: 0, right: 0, zIndex: 2, padding: '48px 12px 10px',
        display: 'flex', alignItems: 'center', gap: 8, backgroundColor: 'var(--p-chrome)',
        backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)', borderBottom: '1px solid var(--p-border)',
      }}>
        <button className="picon tap" onClick={onClose} style={{ color: 'var(--p-ink)', width: 32 }}><IconBackS size={24} /></button>
        <div style={{ flex: 1, fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 18, color: 'var(--p-ink)' }}>Уведомления</div>
      </div>

      <div className="pscroll" style={{ paddingTop: 96, paddingBottom: 40 }}>
        {N_GROUPS.map(([key, label]) => {
          const rows = notifs.filter((n) => n.group === key);
          if (!rows.length) return null;
          return (
            <div key={key}>
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13.5, color: 'var(--p-ink)', padding: '14px 16px 5px' }}>{label}</div>
              {rows.map((n) => <SNotifRow key={n.id} n={n} />)}
            </div>
          );
        })}
        <div style={{ textAlign: 'center', padding: '26px 28px 8px', color: 'var(--p-faint)' }}>
          <div style={{ fontSize: 24 }}>🪷</div>
          <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 12.5, marginTop: 5 }}>Это вся активность</div>
        </div>
      </div>
    </div>, host);
}

Object.assign(window, { SNotifications });
