// other-profile.jsx — «Чужой профиль»: то же, что в Ахамкаре, но глазами гостя.
// Пока это копия структуры Ахамкары на других данных — база, чтобы разводить дизайн дальше.
// Экспорт в window: OtherProfile.
const { useState: uOP } = React;

const OTHER = {
  name: 'Антон Рассвет',
  spiritual: 'Сурья Дас',
  username: '@surya.das',
  role: 'Виньяса · аштанга · 9 лет практики',
  bio: 'Динамика и дыхание. Веду утренние потоки и выездные интенсивы. Начинал с бега, остался в йоге.',
  link: 'surya.yoga',
  location: 'Санкт-Петербург',
  stats: { posts: 84, practices: 21, followers: '3.4К' },
};

/* автор всего контента на этом экране — не вы, а владелец профиля */
const OTHER_AUTHOR = {
  name: OTHER.name, spiritual: OTHER.spiritual, role: OTHER.role,
  avatar: TEACHER_PHOTO.surya, verified: false,
  selfLabel: OTHER.spiritual, selfLabelCap: OTHER.spiritual,
};

/* ── верхняя панель: назад + имя ────────────────────────────────── */
function OPTopBar({ onBack }) {
  return (
    <div style={{
      position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6,
      backgroundColor: 'var(--p-chrome)', backdropFilter: 'blur(16px)', WebkitBackdropFilter: 'blur(16px)',
      borderBottom: '1px solid var(--p-border)', padding: '48px 14px 11px',
      display: 'flex', alignItems: 'center', gap: 8
    }}>
      <button className="picon tap" onClick={onBack} aria-label="Назад" style={{ backgroundColor: CTRL.surface, flexShrink: 0 }}>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" style={{ color: CTRL.accent }}><path d="M15 5l-7 7 7 7" /></svg>
      </button>
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 6, minWidth: 0 }}>
        <span style={{ fontWeight: 700, fontSize: 16, color: 'var(--p-ink)' }}>{OTHER.username}</span>
        <span style={{ fontSize: 14 }}>☀️</span>
      </div>
    </div>);
}

/* ── шапка профиля ──────────────────────────────────────────────── */
function OPHeader({ nav }) {
  const s = OTHER.stats;
  return (
    <div style={{ padding: '106px 16px 16px', display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
        <PAvatar size={86} dot src={TEACHER_PHOTO.surya} />
        <div style={{ flex: 1, display: 'flex', gap: 4 }}>
          <PStat n={s.posts} l="записей" hue={POP.coral} />
          <PStat n={s.practices} l="занятий" hue={POP.mint} />
          <PStat n={s.followers} l="подписчиков" hue={POP.grape} />
        </div>
      </div>

      <div>
        <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 18, color: 'var(--p-ink)', display: 'flex', alignItems: 'center', gap: 6, whiteSpace: 'nowrap' }}>
          {OTHER.name}
          <span style={{ color: POP.bubble, fontSize: 15 }}>· {OTHER.spiritual}</span>
        </div>
        <div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--p-mute)', marginTop: 4 }}>🌿 {OTHER.role}</div>
        <p style={{ margin: '7px 0 0', fontSize: 14, lineHeight: 1.55, color: 'var(--p-soft)', textWrap: 'pretty' }}>{OTHER.bio}</p>
        <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '4px 12px', marginTop: 8, fontSize: 13, fontWeight: 700 }}>
          <span style={{ color: POP.sky }}>🔗 {OTHER.link}</span>
          <span style={{ color: 'var(--p-faint)', fontWeight: 600 }}>📍 {OTHER.location}</span>
        </div>
      </div>

      <PActions nav={nav} />

      <div style={{ display: 'flex', gap: 14, overflowX: 'auto', scrollbarWidth: 'none', paddingTop: 2 }}>
        {HILITES.map(([l, e, p, t]) => <PHilite key={l} label={l} emoji={e} pop={p} tint={t} />)}
      </div>
    </div>);
}

/* ── табы без кнопки «создать» (гость ничего не публикует) ───────── */
const OP_TABS = [['feed', 'Лента'], ['grid', 'Сетка'], ['practices', 'Занятия'], ['saved', 'Практики']];
function OPTabs({ tab, setTab }) {
  return (
    <div style={{ position: 'sticky', top: 94, zIndex: 5, backgroundColor: 'var(--p-bg)', padding: '6px 14px 10px' }}>
      <div style={{ display: 'flex', gap: 6, backgroundColor: CTRL.surface, borderRadius: 16, padding: 4 }}>
        {OP_TABS.map(([id, label]) => {
          const on = tab === id;
          return (
            <button key={id} onClick={() => setTab(id)} style={{
              flex: 1, height: 38, border: 'none', cursor: 'pointer', borderRadius: 13,
              fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 13,
              backgroundColor: on ? 'var(--p-elev)' : 'transparent',
              color: on ? CTRL.accent : CTRL.text,
              boxShadow: on ? '0 4px 12px -6px rgba(40,20,60,0.35)' : 'none',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              transition: 'all .15s ease'
            }}>{label}</button>);
        })}
      </div>
    </div>);
}

function OtherProfile({ dark = false, nav }) {
  const [feed, setFeed] = uOP(() => JSON.parse(JSON.stringify(FEED)));
  const [tab, setTab] = uOP('feed');
  const [activeTag, setActiveTag] = uOP(null);
  const [types, setTypes] = uOP([]);
  const [comments, setComments] = uOP('all');
  const [sheet, setSheet] = uOP(false);
  const [practices, setPractices] = uOP(() => JSON.parse(JSON.stringify(PRACTICES)));
  const rootRef = React.useRef(null);

  const practiceById = React.useMemo(() => Object.fromEntries(practices.map((p) => [p.id, p])), [practices]);

  const [focusId, setFocusId] = uOP(null);
  const openPost = (id) => { setActiveTag(null); setTypes([]); setComments('all'); setTab('feed'); setFocusId(id); };
  React.useEffect(() => {
    if (!focusId) return;
    const t = setTimeout(() => {
      const root = rootRef.current;
      const sc = root && root.querySelector('.pscroll');
      const el = root && root.querySelector('[data-post-id="' + focusId + '"]');
      if (sc && el) sc.scrollTop += el.getBoundingClientRect().top - sc.getBoundingClientRect().top - 8;
    }, 60);
    const clear = setTimeout(() => setFocusId(null), 1500);
    return () => { clearTimeout(t); clearTimeout(clear); };
  }, [focusId]);

  const like = (id) => setFeed((f) => f.map((p) => p.id === id ? { ...p, liked: !p.liked, likes: p.likes + (p.liked ? -1 : 1) } : p));

  const matches = (p) => {
    if (activeTag && !(p.tags || []).includes(activeTag)) return false;
    if (p.type === 'class') { if (types.length) return false; }
    else if (types.length) {
      const has = (t) => t === 'text' ? !!p.text : (p.media || []).includes(t);
      if (!types.every(has)) return false;
    }
    if (comments === 'with' && p.comments.length === 0) return false;
    if (comments === 'without' && p.comments.length > 0) return false;
    return true;
  };
  const filtered = feed.filter(matches);

  const hasSheetFilter = types.length > 0 || comments !== 'all';
  const anyFilter = hasSheetFilter || activeTag !== null;
  const resetAll = () => { setActiveTag(null); setTypes([]); setComments('all'); };
  const host = rootRef.current && rootRef.current.classList.contains('ylp') ? rootRef.current : null;
  const back = () => nav && nav.onNav && nav.onNav('sangat');

  return (
    <PAuthorCtx.Provider value={OTHER_AUTHOR}>
    <div ref={rootRef} className={'ylp' + (dark ? ' dark' : '')}>
      <OPTopBar onBack={back} />
      <div className="pscroll">
        <OPHeader nav={nav} />
        <OPTabs tab={tab} setTab={setTab} />
        <div style={{ paddingBottom: 96 }}>
          {tab === 'feed' &&
          <React.Fragment>
            <PFeedFilter
              activeTag={activeTag} setActiveTag={setActiveTag}
              openSheet={() => setSheet(true)} anyFilter={anyFilter}
              resetAll={resetAll} hasSheetFilter={hasSheetFilter} />
            {filtered.map((p) => {
              const card = p.type === 'class' ?
                (practiceById[p.practiceId] ? <PClassAnnounceCard post={p} practice={practiceById[p.practiceId]} onLike={like} onTag={(t) => setActiveTag((cur) => cur === t ? null : t)} activeTag={activeTag} /> : null) :
                <PPostCard post={p} onLike={like} onTag={(t) => setActiveTag((cur) => cur === t ? null : t)} activeTag={activeTag} />;
              if (!card) return null;
              const hot = focusId === p.id;
              return (
                <div key={p.id} data-post-id={p.id} style={{
                  position: 'relative', transition: 'background-color .5s ease',
                  backgroundColor: hot ? 'var(--t-sun)' : 'transparent'
                }}>{card}</div>);
            })}
            {filtered.length === 0 &&
            <div style={{ padding: '38px 28px 8px', textAlign: 'center' }}>
              <div style={{ fontSize: 44 }}>🍃</div>
              <div style={{ fontFamily: "'Quicksand', sans-serif", fontWeight: 800, fontSize: 17, color: 'var(--p-ink)', marginTop: 10 }}>Ничего не найдено</div>
              <div style={{ fontSize: 13.5, color: 'var(--p-mute)', marginTop: 6, lineHeight: 1.5 }}>Попробуйте изменить фильтры или хэштег.</div>
              <button className="tap" onClick={resetAll} style={{ marginTop: 16, border: 'none', cursor: 'pointer', padding: '10px 20px', borderRadius: 13, backgroundColor: CTRL.surface, color: CTRL.accent, fontFamily: "'Quicksand', sans-serif", fontWeight: 700, fontSize: 14 }}>Сбросить фильтры</button>
            </div>}
          </React.Fragment>}
          {tab === 'grid' && <PGrid feed={feed} practiceById={practiceById} onOpen={openPost} />}
          {tab === 'practices' && <PPractices items={practices} setItems={setPractices} />}
          {tab === 'saved' && <PPublic items={practices} setItems={setPractices} />}
        </div>
      </div>
      <PBottomNav {...nav} active="sangat" />
      {sheet &&
      <PFilterSheet
        host={host} types={types} setTypes={setTypes}
        comments={comments} setComments={setComments}
        onClose={() => setSheet(false)} onReset={resetAll} count={filtered.length} />}
    </div>
    </PAuthorCtx.Provider>);
}

Object.assign(window, { OtherProfile, OTHER, OTHER_AUTHOR });
