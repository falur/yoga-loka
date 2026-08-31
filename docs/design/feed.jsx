// feed.jsx — минимал: спокойный аватар, плоские посты, комментарии, вкладки
// Экспорт в window: Avatar, PostCard, GridTab, PracticesTab, StoryRing

const { useState } = React;

function Avatar({ size = 56, ring = 'hairline', initials = 'АС' }) {
  const border =
    ring === 'accent' ? '2px solid var(--accent)' :
    ring === 'hairline' ? '1px solid var(--hairline)' : 'none';
  return (
    <div style={{
      width: size, height: size, borderRadius: '50%', flexShrink: 0,
      background: 'var(--bg-2)', border, overflow: 'hidden',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      color: 'var(--ink-soft)', fontFamily: 'var(--font-display)', fontWeight: 600,
      fontSize: size * 0.36, letterSpacing: '0.01em',
    }}>{initials}</div>
  );
}

function StoryRing({ label }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, width: 64, flexShrink: 0 }}>
      <div style={{ padding: 2, borderRadius: '50%', border: '1.5px solid var(--hairline)' }}>
        <div className="ph" style={{ width: 54, height: 54, borderRadius: '50%' }} />
      </div>
      <span className="soft" style={{ fontSize: 11, maxWidth: 64, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{label}</span>
    </div>
  );
}

function CommentRow({ c }) {
  const [liked, setLiked] = useState(false);
  const [n, setN] = useState(c.likes);
  return (
    <div style={{ display: 'flex', gap: 9, alignItems: 'flex-start' }}>
      <Avatar size={28} ring="none" initials={c.name.split(' ').map(w => w[0]).join('').slice(0, 2)} />
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13.5, lineHeight: 1.4 }}>
          <span style={{ fontWeight: 700 }}>{c.name}</span>
          <span className="faint" style={{ fontWeight: 500 }}> ·{c.handle}</span>
        </div>
        <div style={{ fontSize: 13.5, lineHeight: 1.45, marginTop: 1 }}>{c.text}</div>
      </div>
      <button className="icon-btn" onClick={() => { setLiked(v => !v); setN(x => x + (liked ? -1 : 1)); }}
        style={{ color: liked ? 'var(--accent)' : 'var(--ink-faint)', flexDirection: 'column', gap: 2, fontSize: 10.5, fontWeight: 600 }}>
        <IconHeart size={13} filled={liked} />{n > 0 && <span>{n}</span>}
      </button>
    </div>
  );
}

function PostCard({ post, onLike, onAddComment, openComments, onToggleComments }) {
  const [draft, setDraft] = useState('');
  const send = () => { const t = draft.trim(); if (!t) return; onAddComment(post.id, t); setDraft(''); };
  const PAD = 'calc(13px * var(--sp))';

  return (
    <article>
      {/* header */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: `${PAD} 16px 0` }}>
        <Avatar size={36} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
            <span className="display" style={{ fontWeight: 600, fontSize: 14 }}>{PROFILE.name}</span>
            <span style={{ color: 'var(--accent)', display: 'inline-flex' }}><IconVerified size={13} /></span>
          </div>
          <div className="faint" style={{ fontSize: 11.5 }}>
            <span style={{ fontStyle: 'italic' }}>{PROFILE.spiritual}</span> · {post.time}
            {post.pinned && <span> · закреплено</span>}
          </div>
        </div>
        <button className="icon-btn faint" style={{ width: 30, height: 30 }}><IconMenu size={18} /></button>
      </div>

      {/* photo — full-bleed (before caption, IG-style) */}
      {post.type === 'photo' && (
        <div className="ph" style={{ marginTop: 11, height: 300, width: '100%' }}>
          <span className="ph-tag">фото · {post.image}</span>
        </div>
      )}

      {/* text / caption — after photo */}
      {post.text && (
        <p style={{ margin: `${post.type === 'photo' ? 11 : 9}px 16px 0`, fontSize: 14.5, lineHeight: 1.5, textWrap: 'pretty' }}>{post.text}</p>
      )}

      {/* actions */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 18, padding: `11px 16px ${draft || openComments ? '0' : PAD}` }}>
        <button className="icon-btn" onClick={() => onLike(post.id)}
          style={{ color: post.liked ? 'var(--accent)' : 'var(--ink)', gap: 7, fontSize: 13.5, fontWeight: 600 }}>
          <IconHeart size={22} filled={post.liked} style={post.liked ? { animation: 'ylpop .35s ease' } : undefined} />{post.likes}
        </button>
        <button className="icon-btn" onClick={() => onToggleComments(post.id)}
          style={{ color: openComments ? 'var(--accent)' : 'var(--ink)', gap: 7, fontSize: 13.5, fontWeight: 600 }}>
          <IconComment size={21} />{post.comments.length}
        </button>
        <button className="icon-btn" style={{ color: 'var(--ink)' }}><IconShare size={20} /></button>
        <div style={{ flex: 1 }} />
        <button className="icon-btn" style={{ color: 'var(--ink)' }}><IconBookmark size={20} /></button>
      </div>

      {/* comments */}
      {openComments && (
        <div className="yl-enter" style={{ margin: '12px 16px 0', paddingTop: 12, borderTop: '1px solid var(--hairline)', display: 'flex', flexDirection: 'column', gap: 12, paddingBottom: PAD }}>
          {post.comments.map(c => <CommentRow key={c.id} c={c} />)}
          {post.comments.length === 0 && (
            <div className="faint" style={{ fontSize: 13, textAlign: 'center' }}>Пока тихо. Будьте первым.</div>
          )}
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, border: '1px solid var(--hairline)', borderRadius: 999, padding: '5px 5px 5px 14px' }}>
            <input value={draft} onChange={e => setDraft(e.target.value)} onKeyDown={e => e.key === 'Enter' && send()}
              placeholder="Добавить комментарий…"
              style={{ flex: 1, border: 'none', background: 'none', outline: 'none', fontFamily: 'var(--font-body)', fontSize: 13.5, color: 'var(--ink)' }} />
            <button className="btn btn-accent" onClick={send}
              style={{ width: 32, height: 32, borderRadius: 999, flexShrink: 0, opacity: draft.trim() ? 1 : 0.4 }}>
              <IconSend size={16} />
            </button>
          </div>
        </div>
      )}
    </article>
  );
}

function GridTab() {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 2, padding: '2px 0 0' }}>
      {GRID.map((g, i) => (
        <div key={i} className="ph tap" style={{ aspectRatio: '1' }}>
          <span className="ph-tag" style={{ fontSize: 9, margin: 5, padding: '2px 5px' }}>{g.image}</span>
        </div>
      ))}
    </div>
  );
}

function PracticesTab() {
  return (
    <div>
      {PRACTICES.map((p, i) => (
        <div key={p.id} className="tap" style={{ display: 'flex', alignItems: 'center', gap: 13, padding: '12px 16px', borderTop: i ? '1px solid var(--hairline)' : 'none' }}>
          <div className="ph" style={{ width: 56, height: 56, borderRadius: 'var(--r-sm)', flexShrink: 0 }} />
          <div style={{ flex: 1, minWidth: 0 }}>
            <div className="display" style={{ fontWeight: 600, fontSize: 14.5 }}>{p.title}</div>
            <div className="soft" style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 12.5, marginTop: 3 }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}><IconClock size={14} />{p.dur}</span>
              <span>·</span><span>{p.level}</span>
            </div>
          </div>
          <span className="faint" style={{ display: 'inline-flex' }}><IconChevron size={17} /></span>
        </div>
      ))}
    </div>
  );
}

Object.assign(window, { Avatar, StoryRing, PostCard, GridTab, PracticesTab });
