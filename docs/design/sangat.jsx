// sangat.jsx — страница «Сангат»: лента сообщества в стиле Instagram
// Истории сверху · общая лента (все типы записей) · сообщения (директ) в углу.
// Зависит от playful.jsx (PPhoto/PAudio/PVideo/PCommentSheet/POP/TINT/CTRL/avaFor…)
//          + sangat-data.jsx + sangat-messages.jsx.
// Экспорт в window: SangatFeed

const { useState: uSs } = React;

const sPerson = (who) => (window.SANGAT_PEOPLE || {})[who] || { name: who, handle: who };

/* ── DM-иконка (бумажный самолётик, контур) ── */
const IconDM = ({ size = 22, style }) =>
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
       strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" style={style}>
    <path d="M21 4 11 14" /><path d="M21 4 14.5 21l-3.5-7-7-3.5L21 4Z" />
  </svg>;

/* ── аватар участника (по handle) — опц. сюжетное кольцо ── */
function SAvatar({ who, size = 40, story = false, seen = false, add = false }) {
  const p = sPerson(who);
  const src = who === 'you' ? AVA_SELF : avaFor(p.handle || who);
  const inner = story ? size - 7 : size;
  const ringBg = seen
    ? 'var(--p-border)'
    : 'conic-gradient(from 210deg, #FF6F61, #FFB020, #5DBB63, #22C2B0, #4D9DE0, #9B5DE5, #F15BB5, #FF6F61)';
  const core = (
    <div style={{
      width: inner, height: inner, borderRadius: '50%', overflow: 'hidden', flexShrink: 0,
      background: 'var(--t-coral)', border: story ? '2.5px solid var(--p-elev)' : 'none',
    }}><PImg src={src} /></div>
  );
  return (
    <div style={{ position: 'relative', width: size, height: size, flexShrink: 0 }}>
      {story
        ? <div style={{ width: size, height: size, borderRadius: '50%', background: ringBg, padding: 3, boxSizing: 'border-box' }}>{core}</div>
        : core}
      {add &&
        <span style={{
          position: 'absolute', right: -2, bottom: -2, width: 20, height: 20, borderRadius: '50%',
          backgroundColor: POP.sky, border: '2.5px solid var(--p-bg)', color: '#fff',
          display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 14, fontWeight: 800, lineHeight: 1,
        }}>+</span>}
    </div>
  );
}

/* ── верхняя панель: логотип + поиск + лайки + сообщения ── */
function STopBar({ onSearch, onMessages, onLikes, unread, activity }) {
  return (
    <div style={{
      position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6,
      backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)',
      borderBottom: '1px solid var(--p-border)', padding: '48px 16px 11px',
      display: 'flex', alignItems: 'center', gap: 8,
    }}>
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 7, minWidth: 0 }}>
        <span style={{ fontSize: 19 }}>❤️</span>
        <span style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 22, letterSpacing: '-0.01em', color: 'var(--p-ink)' }}>Сангат</span>
      </div>
      <button className="picon tap" onClick={onSearch} aria-label="Поиск" style={{ backgroundColor: 'transparent', color: 'var(--p-ink)' }}>
        <IconSearch size={23} />
      </button>
      <button className="picon tap" onClick={onLikes} aria-label="Уведомления" style={{ position: 'relative', backgroundColor: 'transparent', color: 'var(--p-ink)' }}>
        <IconHeart size={23} />
        {activity > 0 &&
          <span style={{
            position: 'absolute', top: 3, right: 2, minWidth: 17, height: 17, padding: '0 4px', borderRadius: 999,
            backgroundColor: POP.coral, color: '#fff', border: '2px solid var(--p-chrome)',
            fontSize: 10.5, fontWeight: 800, lineHeight: '17px', textAlign: 'center', boxSizing: 'border-box',
          }}>{activity}</span>}
      </button>
      <button className="picon tap" onClick={onMessages} aria-label="Сообщения" style={{ position: 'relative', backgroundColor: 'transparent', color: 'var(--p-ink)' }}>
        <IconDM size={23} />
        {unread > 0 &&
          <span style={{
            position: 'absolute', top: 3, right: 2, minWidth: 17, height: 17, padding: '0 4px', borderRadius: 999,
            backgroundColor: POP.coral, color: '#fff', border: '2px solid var(--p-chrome)',
            fontSize: 10.5, fontWeight: 800, lineHeight: '17px', textAlign: 'center', boxSizing: 'border-box',
          }}>{unread}</span>}
      </button>
    </div>
  );
}

/* ── строка историй ── */
function SStories({ stories, onOpen }) {
  return (
    <div style={{
      display: 'flex', gap: 14, overflowX: 'auto', scrollbarWidth: 'none', WebkitOverflowScrolling: 'touch',
      padding: '104px 16px 14px', borderBottom: '1px solid var(--p-border)',
    }}>
      {stories.map((s) => {
        const p = sPerson(s.who);
        return (
          <button key={s.id} className="tap" onClick={() => onOpen(s)} style={{
            border: 'none', background: 'none', cursor: 'pointer', padding: 0, flexShrink: 0,
            display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 7, width: 66,
          }}>
            <SAvatar who={s.who} size={66} story={!s.add} seen={s.seen} add={s.add} />
            <span style={{
              fontFamily: "'Nunito Sans', sans-serif", fontSize: 11.5, fontWeight: s.seen ? 600 : 700,
              color: s.seen ? 'var(--p-faint)' : 'var(--p-soft)', maxWidth: 66, whiteSpace: 'nowrap',
              overflow: 'hidden', textOverflow: 'ellipsis',
            }}>{s.add ? 'Вы' : s.label}</span>
          </button>
        );
      })}
    </div>
  );
}

/* ── шапка карточки записи ── */
function SPostHead({ who, time, place }) {
  const p = sPerson(who);
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 11 }}>
      <SAvatar who={who} size={40} story seen />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14, color: 'var(--p-ink)' }}>
          <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{p.handle}</span>
          {p.verified && <span style={{ color: POP.sky, display: 'inline-flex', flexShrink: 0 }}><IconVerified size={13} /></span>}
        </div>
        <div style={{ fontSize: 11.5, color: 'var(--p-faint)', fontWeight: 600 }}>
          {place ? <span>📍 {place} · </span> : null}{time}
        </div>
      </div>
      <button className="picon" style={{ width: 30, height: 30, color: 'var(--p-faint)' }}><IconMenu size={18} /></button>
    </div>
  );
}

/* ── строка действий: лайк · коммент · поделиться · сохранить ── */
function SActions({ post, onLike, onComment, count, onShare, saved, onSave }) {
  const pill = {
    display: 'inline-flex', alignItems: 'center', gap: 7, border: 'none', cursor: 'pointer',
    padding: '8px 14px', borderRadius: 999, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5,
    transition: 'all .15s ease',
  };
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 12 }}>
      <button onClick={() => onLike(post.id)} style={{ ...pill, backgroundColor: post.liked ? TINT.coral : CTRL.surface, color: post.liked ? POP.coral : CTRL.text }}>
        <IconHeart size={17} filled={post.liked} style={post.liked ? { animation: 'ylpop .35s ease' } : undefined} />{post.likes}
      </button>
      <button onClick={onComment} style={{ ...pill, backgroundColor: CTRL.surface, color: CTRL.text }}>
        <IconComment size={16} />{count}
      </button>
      <button onClick={onShare} aria-label="Поделиться" style={{ ...pill, padding: '8px 12px', backgroundColor: CTRL.surface, color: CTRL.text }}>
        <IconShareVK size={17} />
      </button>
      <div style={{ flex: 1 }} />
      <button onClick={onSave} aria-label="Сохранить" style={{ ...pill, padding: '8px 12px', backgroundColor: saved ? TINT.sun : CTRL.surface, color: saved ? GOLD_INK : CTRL.text }}>
        <IconBookmark size={17} filled={saved} />
      </button>
    </div>
  );
}

/* ── хэштеги ── */
function STags({ tags }) {
  if (!tags || !tags.length) return null;
  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 10 }}>
      {tags.map((t) =>
        <span key={t} style={{
          padding: '4px 11px', borderRadius: 999, backgroundColor: CTRL.surface, color: POP.sky,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 12, fontWeight: 700,
        }}>#{t}</span>)}
    </div>
  );
}

/* ── единая карточка записи сообщества (все типы) ── */
function SPostCard({ post, onLike, host }) {
  const [open, setOpen] = uSs(false);
  const [saved, setSaved] = uSs(false);
  const [toast, setToast] = uSs(null);
  const tref = React.useRef(0);
  const ping = (m) => { setToast(m); clearTimeout(tref.current); tref.current = setTimeout(() => setToast(null), 1900); };
  React.useEffect(() => () => clearTimeout(tref.current), []);

  // комментарии в форму, понятную PCommentSheet (name/handle/text/time/likes)
  const toRows = (cs) => cs.map((c) => { const p = sPerson(c.who); return { id: c.id, name: p.handle, handle: p.handle, text: c.text, time: c.time, likes: c.likes }; });
  const [comments, setComments] = uSs(() => toRows(post.comments));
  const addComment = (text) => setComments((cs) => [...cs, { id: 'cu' + Date.now(), name: 'you', handle: 'you', text, time: 'только что', likes: 0 }]);

  const k = post.klass;

  return (
    <article style={{ padding: '8px 14px 6px' }}>
      <div style={{ backgroundColor: 'var(--p-elev)', borderRadius: 22, padding: 14, boxShadow: '0 8px 24px -16px rgba(40,20,60,0.4)', border: '1px solid var(--p-border)' }}>
        <SPostHead who={post.who} time={post.time} place={post.place} />

        {/* медиа по типу */}
        {post.type === 'photo' &&
          <div style={{ marginTop: 12 }}><PPhoto image={post.image} height={300} radius={16} /></div>}
        {post.type === 'video' &&
          <PVideo image={post.image} dur={post.video.dur} />}
        {post.type === 'audio' &&
          <PAudio audio={post.audio} />}

        {/* анонс занятия */}
        {post.type === 'class' && k &&
          <button className="tap" onClick={() => ping('Открываем занятие…')} style={{
            display: 'block', width: '100%', textAlign: 'left', border: 'none', cursor: 'pointer', padding: 0,
            marginTop: 12, borderRadius: 16, overflow: 'hidden', backgroundColor: CTRL.surface,
          }}>
            <div style={{ position: 'relative' }}>
              <PPhoto image={k.image} height={150} radius={0} />
              <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg, rgba(20,14,30,0) 40%, rgba(20,14,30,0.55))' }} />
              <span style={{ position: 'absolute', left: 12, top: 12, padding: '5px 11px', borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.92)', color: '#241b30', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11.5 }}>
                {k.kind === 'audio' ? '🎧 Аудио' : '🎬 Видео'} · {k.when}
              </span>
              {k.free &&
                <span style={{ position: 'absolute', right: 12, top: 12, padding: '5px 11px', borderRadius: 999, backgroundColor: POP.leaf, color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 11.5 }}>🎁 Бесплатно</span>}
              <div style={{ position: 'absolute', left: 12, right: 12, bottom: 11, color: '#fff' }}>
                <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 16, lineHeight: 1.2 }}>{k.title}</div>
                <div style={{ fontSize: 12, fontWeight: 600, opacity: 0.9, marginTop: 3 }}>⏱ {k.dur} · {k.level}</div>
              </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '11px 14px' }}>
              <p style={{ flex: 1, margin: 0, fontSize: 12.5, lineHeight: 1.45, color: 'var(--p-mute)', textWrap: 'pretty' }}>{k.desc}</p>
              <span style={{ flexShrink: 0, padding: '9px 16px', borderRadius: 999, backgroundColor: POP.coral, color: '#fff', fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 13 }}>Записаться</span>
            </div>
          </button>}

        {/* подпись */}
        {post.text &&
          <p style={{ margin: `${post.type !== 'text' ? 11 : 9}px 0 0`, fontSize: 14, lineHeight: 1.5, color: 'var(--p-soft)', textWrap: 'pretty' }}>{post.text}</p>}

        <STags tags={post.tags} />

        <SActions
          post={post} onLike={onLike} onComment={() => setOpen(true)} count={comments.length}
          onShare={() => ping('Ссылка скопирована 🔗')} saved={saved}
          onSave={() => { setSaved((v) => !v); ping(saved ? 'Убрано из сохранённых' : 'Сохранено в коллекцию 🔖'); }} />
      </div>

      {open &&
        <PCommentSheet host={host} comments={comments} onAdd={addComment} onClose={() => setOpen(false)} />}
      {host && toast && ReactDOM.createPortal(
        <div className="ptoast" style={{
          position: 'absolute', left: '50%', bottom: 96, zIndex: 50, transform: 'translateX(-50%)',
          backgroundColor: 'var(--p-ink)', color: 'var(--p-bg)', padding: '11px 18px', borderRadius: 999,
          fontFamily: "'Nunito Sans', sans-serif", fontSize: 13.5, fontWeight: 700, whiteSpace: 'nowrap',
          boxShadow: '0 12px 34px -10px rgba(0,0,0,0.5)',
        }}>{toast}</div>, host)}
    </article>
  );
}

/* ── shell страницы Сангат ── */
function SangatFeed({ dark = false, nav }) {
  const [feed, setFeed] = uSs(() => JSON.parse(JSON.stringify(SANGAT_FEED)));
  const [stories, setStories] = uSs(() => JSON.parse(JSON.stringify(SANGAT_STORIES)));
  const [view, setView] = uSs(null);   // null | 'messages' | 'search'
  const [chatIntent, setChatIntent] = uSs(() => { const c = window.__ylChatIntent || null; window.__ylChatIntent = null; return c; });
  React.useEffect(() => { if (chatIntent) setView('messages'); }, []);
  const [story, setStory] = uSs(null);  // активная история
  const rootRef = React.useRef(null);
  const host = rootRef.current && rootRef.current.classList.contains('ylp') ? rootRef.current : null;

  const like = (id) => setFeed((f) => f.map((p) => p.id === id ? { ...p, liked: !p.liked, likes: p.likes + (p.liked ? -1 : 1) } : p));
  const unread = (SANGAT_CHATS || []).reduce((n, c) => n + (c.unread || 0), 0);
  const [activitySeen, setActivitySeen] = uSs(false);
  const activityNew = (SANGAT_NOTIFS || []).filter((n) => n.group === 'new').length;

  const openStory = (s) => {
    if (s.add || !(s.slides && s.slides.length)) return;
    setStory(s);
    setStories((xs) => xs.map((x) => x.id === s.id ? { ...x, seen: true } : x));
  };

  return (
    <div ref={rootRef} className={'ylp' + (dark ? ' dark' : '')}>
      <STopBar onSearch={() => setView('search')} onMessages={() => setView('messages')} onLikes={() => { setView('activity'); setActivitySeen(true); }} unread={unread} activity={activitySeen ? 0 : activityNew} />
      <div className="pscroll">
        <SStories stories={stories} onOpen={openStory} />
        <div style={{ paddingBottom: 96 }}>
          {feed.map((p) => <SPostCard key={p.id} post={p} onLike={like} host={host} />)}
          <div style={{ textAlign: 'center', padding: '20px 28px 8px', color: 'var(--p-faint)' }}>
            <div style={{ fontSize: 26 }}>🪷</div>
            <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13.5, marginTop: 6 }}>Вы всё посмотрели</div>
            <div style={{ fontSize: 12.5, marginTop: 3 }}>Сат Нам · сообщество с вами</div>
          </div>
        </div>
      </div>
      <SBottomNav {...nav} />

      {view === 'messages' && host &&
        <SMessages host={host} initialChat={chatIntent} onClose={() => { setChatIntent(null); setView(null); }} />}
      {view === 'search' && host &&
        <SSearch host={host} onClose={() => setView(null)} />}
      {view === 'activity' && host &&
        <SNotifications host={host} onClose={() => setView(null)} />}
      {story && host &&
        <SStoryViewer host={host} story={story} all={stories} onClose={() => setStory(null)} onSeen={(id) => setStories((xs) => xs.map((x) => x.id === id ? { ...x, seen: true } : x))} />}
    </div>
  );
}

Object.assign(window, { SangatFeed, SAvatar, sPerson, IconDM });
