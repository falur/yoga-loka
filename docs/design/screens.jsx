// screens.jsx — минимал: плоская хром-обвязка, 3 компактные шапки, оболочка
// Экспорт в window: PhoneProfile

const { useState: useS } = React;
const CHROME_TOP = 94;

/* ── flat top bar ──────────────────────────────────────────────── */
function TopBar() {
  return (
    <div className="glass" style={{
      position: 'absolute', top: 0, left: 0, right: 0, zIndex: 6,
      borderBottom: '1px solid var(--glass-bd)', padding: '48px 12px 10px',
      display: 'flex', alignItems: 'center', gap: 6,
    }}>
      <button className="icon-btn" style={{ width: 34, height: 34 }}><IconBack size={20} /></button>
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 5, minWidth: 0 }}>
        <span style={{ fontWeight: 700, fontSize: 15.5 }}>{PROFILE.username}</span>
        <span className="faint" style={{ display: 'inline-flex', transform: 'translateY(1px)' }}><IconChevron size={13} style={{ transform: 'rotate(90deg)' }} /></span>
      </div>
      <button className="icon-btn" style={{ width: 34, height: 34 }}><IconBell size={20} /></button>
      <button className="icon-btn" style={{ width: 34, height: 34 }}><IconMenu size={21} /></button>
    </div>
  );
}

/* ── flat bottom nav ───────────────────────────────────────────── */
function BottomNav() {
  return (
    <div className="glass" style={{
      position: 'absolute', bottom: 0, left: 0, right: 0, zIndex: 6,
      borderTop: '1px solid var(--glass-bd)', display: 'flex', alignItems: 'center',
      justifyContent: 'space-around', padding: '11px 10px 26px',
    }}>
      {[IconHome, IconSearch, IconPlus, IconBell].map((Ic, i) => (
        <button key={i} className="icon-btn" style={{ color: i === 0 ? 'var(--ink)' : 'var(--ink-soft)', width: 44, height: 40 }}><Ic size={25} /></button>
      ))}
      <button className="icon-btn" style={{ width: 44, height: 40 }}>
        <span style={{ borderRadius: '50%', border: '2px solid var(--accent)', padding: 1, display: 'inline-flex' }}>
          <Avatar size={26} ring="none" />
        </span>
      </button>
    </div>
  );
}

/* ── pieces ────────────────────────────────────────────────────── */
const Stat = ({ n, l, center = true }) => (
  <div style={{ textAlign: center ? 'center' : 'left' }}>
    <div className="display" style={{ fontWeight: 700, fontSize: 17, lineHeight: 1 }}>{n}</div>
    <div className="faint" style={{ fontSize: 11.5, marginTop: 3 }}>{l}</div>
  </div>
);

function ActionButtons({ withIcon = true }) {
  const [following, setFollowing] = useS(false);
  return (
    <div style={{ display: 'flex', gap: 8, width: '100%' }}>
      <button className={following ? 'btn btn-ghost' : 'btn btn-accent'} onClick={() => setFollowing(v => !v)}
        style={{ flex: 1, height: 34, borderRadius: 'var(--r-sm)', fontSize: 13.5 }}>
        {following ? <><IconCheck size={15} /> Вы подписаны</> : 'Подписаться'}
      </button>
      <button className="btn btn-ghost" style={{ flex: 1, height: 34, borderRadius: 'var(--r-sm)', fontSize: 13.5 }}>Написать</button>
      {withIcon && (
        <button className="btn btn-ghost" style={{ width: 34, height: 34, borderRadius: 'var(--r-sm)', flexShrink: 0 }}>
          <IconChevron size={15} style={{ transform: 'rotate(90deg)' }} />
        </button>
      )}
    </div>
  );
}

const SpiritualInline = () => (
  <span style={{ color: 'var(--accent)', fontStyle: 'italic', fontFamily: 'var(--font-display)', fontWeight: 500 }}>
    {PROFILE.spiritual}
  </span>
);

const BadgeChips = () => (
  <div style={{ display: 'flex', gap: 7, overflowX: 'auto', scrollbarWidth: 'none' }}>
    <span className="chip"><span style={{ color: 'var(--ink-soft)', display: 'inline-flex' }}><IconFlame size={14} /></span>{PROFILE.badges[0].label}</span>
    <span className="chip">{PROFILE.stats.practices} практик</span>
    <span className="chip">{PROFILE.badges[1].label}</span>
  </div>
);

const Highlights = () => (
  <div style={{ display: 'flex', gap: 14, overflowX: 'auto', scrollbarWidth: 'none', paddingTop: 2 }}>
    {['Асаны', 'Дыхание', 'Ретриты', 'Цитаты', 'Студия'].map(l => <StoryRing key={l} label={l} />)}
  </div>
);

const Bio = ({ center }) => (
  <div style={{ textAlign: center ? 'center' : 'left' }}>
    <div style={{ fontSize: 14, fontWeight: 600 }}>
      {PROFILE.name} <span style={{ color: 'var(--accent)' }}><IconVerified size={13} style={{ verticalAlign: '-2px' }} /></span> · <SpiritualInline />
    </div>
    <div className="soft" style={{ fontSize: 13.5, marginTop: 3 }}>{PROFILE.role}</div>
    <p className="soft" style={{ margin: '6px 0 0', fontSize: 14, lineHeight: 1.5, textWrap: 'pretty' }}>{PROFILE.bio}</p>
    <div style={{ fontSize: 13.5, marginTop: 5, display: 'flex', gap: 12, justifyContent: center ? 'center' : 'flex-start' }}>
      <span style={{ color: 'var(--accent)', fontWeight: 600 }}>{PROFILE.link}</span>
      <span className="faint">{PROFILE.location}</span>
    </div>
  </div>
);

/* ════════════ A · «Классика» — компактная шапка Instagram ════════════ */
function HeaderClassic() {
  const s = PROFILE.stats;
  return (
    <div style={{ padding: `${CHROME_TOP + 12}px 16px 14px`, display: 'flex', flexDirection: 'column', gap: 14 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
        <Avatar size={80} ring="accent" />
        <div style={{ flex: 1, display: 'flex', justifyContent: 'space-around' }}>
          <Stat n={s.posts} l="постов" />
          <Stat n={s.followers} l="подписчиков" />
          <Stat n={s.following} l="подписок" />
        </div>
      </div>
      <Bio />
      <BadgeChips />
      <ActionButtons />
      <Highlights />
    </div>
  );
}

/* ════════════ B · «Стекло» — компакт в матовой стеклянной карточке ════════════ */
function HeaderGlass() {
  const s = PROFILE.stats;
  return (
    <div style={{ padding: `${CHROME_TOP + 12}px 14px 14px`, display: 'flex', flexDirection: 'column', gap: 14 }}>
      <div className="glass" style={{ borderRadius: 'var(--r-lg)', padding: 16, display: 'flex', flexDirection: 'column', gap: 13, boxShadow: '0 10px 30px -16px rgba(20,20,40,0.4)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
          <Avatar size={72} ring="accent" />
          <div style={{ flex: 1, display: 'flex', justifyContent: 'space-around' }}>
            <Stat n={s.posts} l="постов" />
            <Stat n={s.followers} l="подписчиков" />
            <Stat n={s.practices} l="практик" />
          </div>
        </div>
        <Bio />
        <ActionButtons withIcon={false} />
      </div>
      <BadgeChips />
      <Highlights />
    </div>
  );
}

/* ════════════ C · «Минимал» — воздушная левая раскладка ════════════ */
function HeaderMinimal() {
  const s = PROFILE.stats;
  return (
    <div style={{ padding: `${CHROME_TOP + 18}px 18px 16px`, display: 'flex', flexDirection: 'column', gap: 20 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
        <Avatar size={62} ring="hairline" />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div className="display" style={{ fontWeight: 700, fontSize: 20, lineHeight: 1.1, display: 'flex', alignItems: 'center', gap: 6 }}>
            {PROFILE.name}<span style={{ color: 'var(--accent)' }}><IconVerified size={16} /></span>
          </div>
          <div style={{ marginTop: 3, fontSize: 14 }}><SpiritualInline /></div>
        </div>
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', padding: '15px 4px', borderTop: '1px solid var(--hairline)', borderBottom: '1px solid var(--hairline)' }}>
        <Stat n={s.posts} l="постов" />
        <Stat n={s.followers} l="подписчиков" />
        <Stat n={s.following} l="подписок" />
        <Stat n={s.practices} l="практик" />
      </div>

      <div>
        <div className="soft" style={{ fontSize: 14 }}>{PROFILE.role}</div>
        <p className="soft" style={{ margin: '7px 0 0', fontSize: 14.5, lineHeight: 1.55, textWrap: 'pretty' }}>{PROFILE.bio}</p>
        <div style={{ fontSize: 13.5, marginTop: 7, color: 'var(--accent)', fontWeight: 600 }}>{PROFILE.link}</div>
      </div>

      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 7 }}>
        {PROFILE.tags.map(t => <span key={t} className="chip" style={{ fontWeight: 500 }}>{t}</span>)}
      </div>

      <ActionButtons withIcon={false} />
    </div>
  );
}

/* ── tabs ──────────────────────────────────────────────────────── */
const TABS = [['feed', 'Лента', IconList], ['grid', 'Сетка', IconGrid], ['practices', 'Практики', IconSpark]];
function TabsBar({ tab, setTab }) {
  return (
    <div style={{ position: 'sticky', top: CHROME_TOP, zIndex: 5, background: 'var(--bg)', borderBottom: '1px solid var(--hairline)' }}>
      <div style={{ display: 'flex' }}>
        {TABS.map(([id, label, Ic]) => {
          const on = tab === id;
          return (
            <button key={id} className="icon-btn" onClick={() => setTab(id)}
              style={{ flex: 1, height: 46, gap: 7, position: 'relative', color: on ? 'var(--ink)' : 'var(--ink-faint)' }}>
              <Ic size={18} /><span style={{ fontSize: 13, fontWeight: on ? 700 : 500 }}>{label}</span>
              {on && <span style={{ position: 'absolute', left: '22%', right: '22%', bottom: -1, height: 2, background: 'var(--ink)', borderRadius: 2 }} />}
            </button>
          );
        })}
      </div>
    </div>
  );
}

/* ── shell ─────────────────────────────────────────────────────── */
const HEADERS = { classic: HeaderClassic, glass: HeaderGlass, minimal: HeaderMinimal };

function PhoneProfile({ variant = 'classic' }) {
  const [feed, setFeed] = useS(() => JSON.parse(JSON.stringify(FEED)));
  const [tab, setTab] = useS('feed');
  const [open, setOpen] = useS(null);

  const like = (id) => setFeed(f => f.map(p => p.id === id ? { ...p, liked: !p.liked, likes: p.likes + (p.liked ? -1 : 1) } : p));
  const toggle = (id) => setOpen(o => o === id ? null : id);
  const addComment = (id, text) => setFeed(f => f.map(p => p.id === id
    ? { ...p, comments: [...p.comments, { id: 'n' + Date.now(), name: PROFILE.name, handle: PROFILE.spiritual.split(' ')[0].toLowerCase(), text, likes: 0 }] }
    : p));

  const Header = HEADERS[variant];
  return (
    <div className="yl">
      <TopBar />
      <div className="scroll">
        <Header />
        <TabsBar tab={tab} setTab={setTab} />
        <div style={{ paddingBottom: 96 }}>
          {tab === 'feed' && feed.map((p, i) => (
            <React.Fragment key={p.id}>
              {i > 0 && <div className="hr" style={{ margin: '4px 16px' }} />}
              <PostCard post={p} onLike={like} onAddComment={addComment} openComments={open === p.id} onToggleComments={toggle} />
            </React.Fragment>
          ))}
          {tab === 'grid' && <GridTab />}
          {tab === 'practices' && <PracticesTab />}
        </div>
      </div>
      <BottomNav />
    </div>
  );
}

Object.assign(window, { PhoneProfile });
