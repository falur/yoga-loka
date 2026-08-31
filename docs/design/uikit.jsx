/* ══════════ Yoga Loka · UI Kit ══════════
   Живая библиотека: токены, типографика, иконки, контролы и компоненты
   собираются из тех же файлов, что и приложение. */

const { useState: uK, useRef: uKr } = React;

/* иконка фильтра — локальная в playful.jsx, дублируем для витрины */
const IconFilter = ({ size = 20, style }) =>
<svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" style={style}>
  <path d="M4 7h9M19 7h1M4 17h1M11 17h9" /><circle cx="16" cy="7" r="2.6" /><circle cx="8" cy="17" r="2.6" />
</svg>;

/* ── шапка секции / специмена ─────────────────────────────────── */
function KSection({ id, title, note, children }) {
  return (
    <section id={id} className="ksec">
      <div className="ksec-h">
        <h2>{title}</h2>
        {note && <p>{note}</p>}
      </div>
      <div className="kgrid">{children}</div>
    </section>
  );
}

/* карточка-специмен: содержимое рендерится внутри .ylp, чтобы работали токены */
function KItem({ name, code, w = 'auto', pad = 16, bare, children }) {
  return (
    <figure className="kitem" style={{ gridColumn: w === 'full' ? '1 / -1' : w === 'half' ? 'span 2' : 'span 1' }}>
      <div className={'kstage ylp' + (window.__kdark ? ' dark' : '')} style={{ padding: bare ? 0 : pad }}>{children}</div>
      <figcaption><b>{name}</b>{code && <code>{code}</code>}</figcaption>
    </figure>
  );
}

/* ── 1. Цвет ──────────────────────────────────────────────────── */
const NEUTRALS = [
  ['--p-bg', 'фон экрана'], ['--p-elev', 'поднятая поверхность'], ['--p-ctrl', 'контролы'],
  ['--p-border', 'границы'], ['--p-ink', 'основной текст'], ['--p-soft', 'вторичный текст'],
  ['--p-mute', 'приглушённый'], ['--p-faint', 'подписи / плейсхолдеры'],
];
const POPS = [['coral', 'Коралл', 'главное действие'], ['sun', 'Солнце', 'акцент, тепло'], ['mint', 'Мята', 'дыхание'],
['sky', 'Небо', 'контролы, ссылки'], ['grape', 'Виноград', 'вторичное действие'], ['bubble', 'Магента', 'сангат'], ['leaf', 'Лист', 'подтверждение']];

function KSwatch({ v, label, sub, big }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 0 }}>
      <div style={{ height: big ? 56 : 40, borderRadius: 12, background: v, border: '1px solid var(--p-border)' }} />
      <div style={{ minWidth: 0 }}>
        <div style={{ fontWeight: 800, fontSize: 12.5, fontFamily: "'Quicksand',sans-serif", color: 'var(--p-ink)' }}>{label}</div>
        <div style={{ fontSize: 11, color: 'var(--p-faint)', overflow: 'hidden', textOverflow: 'ellipsis' }}>{sub}</div>
      </div>
    </div>
  );
}

function KColors() {
  return (
    <KSection id="color" title="Цвет" note="Все значения — CSS-переменные в .ylp / .ylp.dark. Тема переключается сменой одного класса.">
      <KItem name="Нейтрали" code="--p-*" w="half">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12 }}>
          {NEUTRALS.map(([v, s]) => <KSwatch key={v} v={`var(${v})`} label={v.replace('--p-', '')} sub={s} />)}
        </div>
      </KItem>
      <KItem name="Акцентные цвета" code="--c-* / POP" w="half">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12 }}>
          {POPS.map(([k, l, s]) => <KSwatch key={k} v={`var(--c-${k})`} label={l} sub={s} />)}
        </div>
      </KItem>
      <KItem name="Подложки (tints)" code="--t-* / TINT" w="half">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12 }}>
          {POPS.map(([k, l]) => <KSwatch key={k} v={`var(--t-${k})`} label={l} sub={`--t-${k}`} />)}
        </div>
      </KItem>
      <KItem name="Служебные" code="ctrl · луна · экадаши" w="half">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 12 }}>
          <KSwatch v="var(--ctrl-accent)" label="ctrl" sub="акцент контролов" />
          <KSwatch v="var(--ctrl-accent-soft)" label="ctrl soft" sub="активный фон" />
          <KSwatch v="var(--eka)" label="экадаши" sub="--eka" />
          <KSwatch v="var(--moon-lit)" label="луна" sub="--moon-lit" />
        </div>
      </KItem>
    </KSection>
  );
}

/* ── 2. Типографика ───────────────────────────────────────────── */
const TYPE_ROWS = [
  ['Quicksand 800 · 22', "'Quicksand',sans-serif", 800, 22, 'Заголовок экрана'],
  ['Quicksand 800 · 15.5', "'Quicksand',sans-serif", 800, 15.5, 'Кнопки и вкладки'],
  ['Quicksand 700 · 20', "'Quicksand',sans-serif", 700, 20, 'Цифры статистики'],
  ['Nunito Sans 700 · 15', "'Nunito Sans',sans-serif", 700, 15, 'Заголовок карточки'],
  ['Nunito Sans 400 · 15', "'Nunito Sans',sans-serif", 400, 15, 'Основной текст. Плотность 1.45 — читаемо на телефоне.'],
  ['Nunito Sans 600 · 12.5', "'Nunito Sans',sans-serif", 600, 12.5, 'Чипы, подписи, метаданные'],
];
function KType() {
  return (
    <KSection id="type" title="Типографика" note="Quicksand — заголовки, кнопки, цифры. Nunito Sans — весь текст. Третий шрифт не используется.">
      <KItem name="Шкала" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          {TYPE_ROWS.map(([n, f, w, s, sample]) => (
            <div key={n} style={{ display: 'flex', alignItems: 'baseline', gap: 14 }}>
              <span style={{ width: 150, flexShrink: 0, fontSize: 10.5, fontWeight: 700, letterSpacing: '.04em', textTransform: 'uppercase', color: 'var(--p-faint)' }}>{n}</span>
              <span style={{ fontFamily: f, fontWeight: w, fontSize: s, color: 'var(--p-ink)', lineHeight: 1.3 }}>{sample}</span>
            </div>
          ))}
        </div>
      </KItem>
      <KItem name="Метка секции" code="CALUI.label" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <span style={CALUI.label}>За сколько напомнить</span>
          <span style={{ fontSize: 14, color: 'var(--p-soft)' }}>Прописные, Quicksand 800, 11.5px, трекинг .06em — над каждым блоком в шторках.</span>
        </div>
      </KItem>
    </KSection>
  );
}

/* ── 3. Иконки ────────────────────────────────────────────────── */
const ICON_NAMES = Object.keys(window).filter((k) => /^Icon[A-Z]/.test(k)).sort();
function KIcons() {
  return (
    <KSection id="icons" title="Иконки" note="Один набор: линия 1.7–1.8, скруглённые концы, сетка 24. Цвет наследуется от currentColor.">
      <KItem name={`Набор · ${ICON_NAMES.length} шт.`} code="icons.jsx" w="full">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(88px,1fr))', gap: 4 }}>
          {ICON_NAMES.map((n) => {
            const I = window[n];
            return (
              <div key={n} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 7, padding: '12px 4px', borderRadius: 14, background: 'var(--p-ctrl)' }}>
                <I size={22} style={{ color: 'var(--p-ink)' }} />
                <span style={{ fontSize: 9.5, color: 'var(--p-mute)', textAlign: 'center', wordBreak: 'break-word' }}>{n.replace('Icon', '')}</span>
              </div>
            );
          })}
        </div>
      </KItem>
      <KItem name="Состояния" code="filled / picon" w="half">
        <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
          <IconHeart size={24} style={{ color: 'var(--p-mute)' }} />
          <IconHeart size={24} filled style={{ color: POP.coral }} />
          <IconBookmark size={24} style={{ color: 'var(--p-mute)' }} />
          <IconBookmark size={24} filled style={{ color: POP.grape }} />
          <IconVerified size={20} style={{ color: POP.sky }} />
          <button className="picon" style={{ background: 'var(--p-ctrl)' }}><IconSettings size={20} style={{ color: 'var(--p-ink)' }} /></button>
          <button className="picon" style={{ background: CTRL.accentSoft }}><IconFilter size={20} style={{ color: CTRL.accent }} /></button>
        </div>
      </KItem>
    </KSection>
  );
}

/* ── 4. Кнопки ────────────────────────────────────────────────── */
const kGhost = { height: 40, padding: '0 16px', borderRadius: 13, border: 'none', cursor: 'pointer', background: 'var(--p-ctrl)', color: 'var(--p-ink)', fontFamily: "'Quicksand',sans-serif", fontWeight: 800, fontSize: 14 };
function KButtons() {
  return (
    <KSection id="buttons" title="Кнопки" note="Коралл — главное действие, виноград — второстепенное, нейтральная поверхность — всё остальное. Нажатие: scale .955.">
      <KItem name="Главные" code="CALUI.primary(c)" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <button className="pbtn tap" style={{ ...CALUI.primary(POP.coral), marginTop: 0 }}>Записаться</button>
          <button className="pbtn tap" style={{ ...CALUI.primary(POP.grape), marginTop: 0 }}>Сохранить практику</button>
          <button className="pbtn tap" style={{ ...CALUI.primary(POP.leaf), marginTop: 0 }}>Готово</button>
        </div>
      </KItem>
      <KItem name="Вторичные и отмена" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="pbtn tap" style={{ ...kGhost, flex: 1 }}>Подписаться</button>
            <button className="pbtn tap" style={{ ...kGhost, flex: 1, background: TINT.sky, color: POP.sky }}>Написать</button>
          </div>
          <button className="pbtn tap" style={{ ...CALUI.cancel, marginTop: 0, boxShadow: 'none', background: 'var(--p-ctrl)' }}>Отмена</button>
          <button className="pbtn tap" style={{ ...kGhost, background: TINT.coral, color: POP.coral }}>Удалить</button>
        </div>
      </KItem>
      <KItem name="Компактные" code="chip · picon" w="half">
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <button className="pbtn tap" style={{ ...kGhost, height: 34, fontSize: 12.5, background: POP.sky, color: '#fff' }}>Все</button>
          <button className="pbtn tap" style={{ ...kGhost, height: 34, fontSize: 12.5 }}>Записи</button>
          <button className="picon" style={{ background: 'var(--p-ctrl)' }}><IconPlus size={20} style={{ color: 'var(--p-ink)' }} /></button>
          <button className="picon" style={{ background: POP.coral, color: '#fff' }}><IconPlus size={20} /></button>
          <button className="pbtn tap" style={{ ...kGhost, height: 34, fontSize: 12.5, display: 'inline-flex', alignItems: 'center', gap: 6 }}><IconShareVK size={16} />Поделиться</button>
        </div>
      </KItem>
      <KItem name="Отключено и загрузка" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <button disabled style={{ ...CALUI.primary(POP.coral), marginTop: 0, background: 'var(--p-ctrl)', color: 'var(--p-faint)', boxShadow: 'none', cursor: 'not-allowed' }}>Мест нет</button>
          <button style={{ ...CALUI.primary(POP.coral), marginTop: 0, opacity: .65 }}>Отправляем…</button>
        </div>
      </KItem>
    </KSection>
  );
}

/* ── 5. Поля и переключатели ──────────────────────────────────── */
function KControls() {
  const [on1, setOn1] = uK(true);
  const [on2, setOn2] = uK(false);
  const [seg, setSeg] = uK('upcoming');
  const [tag, setTag] = uK('утро');
  return (
    <KSection id="controls" title="Поля и переключатели" note="Единая высота 46 для полей, 52 для главных кнопок, 38 для чипов-переключателей.">
      <KItem name="Поле ввода" code="CALUI.input" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div><span style={CALUI.label}>Название</span><input style={CALUI.input} defaultValue="Утренняя крийя" /></div>
          <div><span style={CALUI.label}>Описание</span><textarea style={{ ...CALUI.input, height: 84, padding: 14, resize: 'none', lineHeight: 1.45 }} placeholder="О чём эта практика…" /></div>
          <div style={{ display: 'flex', gap: 10 }}>
            <div style={{ flex: 1 }}><span style={CALUI.label}>Дата</span><input type="date" style={CALUI.input} defaultValue="2026-08-12" /></div>
            <div style={{ flex: 1 }}><span style={CALUI.label}>Время</span><input type="time" style={CALUI.input} defaultValue="07:30" /></div>
          </div>
        </div>
      </KItem>
      <KItem name="Переключатели" code="CSwitch" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          {[['Напоминание', on1, setOn1, POP.leaf], ['Открытая запись', on2, setOn2, POP.sky]].map(([l, v, s, c]) => (
            <div key={l} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
              <span style={{ fontWeight: 700, fontSize: 14.5, color: 'var(--p-ink)' }}>{l}</span>
              <CSwitch on={v} onChange={s} color={c} />
            </div>
          ))}
          <div>
            <span style={CALUI.label}>Сегмент</span>
            <div style={{ display: 'flex', gap: 6 }}>
              {[['upcoming', 'Предстоящие'], ['recording', 'Записи']].map(([k, l]) => (
                <button key={k} className="tap pbtn" onClick={() => setSeg(k)} style={{
                  flex: 1, height: 38, border: 'none', cursor: 'pointer', borderRadius: 11,
                  fontFamily: "'Quicksand',sans-serif", fontWeight: 800, fontSize: 12.5,
                  background: seg === k ? CTRL.accent : 'var(--p-ctrl)', color: seg === k ? '#fff' : 'var(--p-mute)',
                  boxShadow: seg === k ? `0 5px 14px -7px ${CTRL.accent}` : 'none', transition: 'all .14s ease',
                }}>{l}</button>
              ))}
            </div>
          </div>
        </div>
      </KItem>
      <KItem name="Поиск" w="half">
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, height: 46, borderRadius: 13, background: 'var(--p-ctrl)', padding: '0 14px' }}>
          <IconSearch size={19} style={{ color: 'var(--p-faint)' }} />
          <input placeholder="Поиск по практикам" style={{ ...CALUI.input, height: 'auto', background: 'transparent', padding: 0, fontWeight: 600 }} />
        </div>
      </KItem>
      <KItem name="Теги-фильтры" code="PFeedFilter" w="half" bare>
        <div style={{ padding: '16px 0 8px' }}>
          <PFeedFilter activeTag={tag} setActiveTag={setTag} openSheet={() => {}} anyFilter={!!tag} resetAll={() => setTag(null)} hasSheetFilter={false} />
        </div>
      </KItem>
    </KSection>
  );
}

/* ── 6. Аватары ───────────────────────────────────────────────── */
function KAvatars() {
  return (
    <KSection id="avatars" title="Аватары" note="Фото — для людей, эмодзи в круге — для комментариев и коротких списков. Стопки перекрываются на 8px.">
      <KItem name="Профиль" code="PAvatar" w="half">
        <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
          <PAvatar size={80} />
          <PAvatar size={56} dot />
          <PAvatar size={44} ring={false} />
          <PAvatar size={34} ring={false} src={avaFor('kira')} />
        </div>
      </KItem>
      <KItem name="Комментарий и участники" code="PCommentAvatar · TeacherStack" w="half">
        <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
          <PCommentAvatar handle="anna" />
          <PCommentAvatar handle="lotos" size={44} />
          <TeacherStack leads={(window.TEACHERS || []).slice(0, 3)} size={30} />
          <AudStack ids={Object.keys(window.C_AUD || {}).slice(0, 4)} size={30} />
        </div>
      </KItem>
    </KSection>
  );
}

/* ── 7. Чипы, статистика, значки ──────────────────────────────── */
function KChips() {
  const s = PROFILE.stats;
  return (
    <KSection id="chips" title="Чипы и метрики" note="Чип = подложка --t-* + текст --c-* того же тона. Никаких обводок.">
      <KItem name="Чипы" code="PChip" w="half">
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <PChip emoji="🔥" label="Хатха" tint={TINT.coral} pop={POP.coral} />
          <PChip emoji="🌙" label="Вечерняя" tint={TINT.sky} pop={POP.sky} />
          <PChip emoji="🌬️" label="Пранаяма" tint={TINT.mint} pop={POP.mint} />
          <PChip emoji="🎧" label="Аудио" tint={TINT.grape} pop={POP.grape} />
          <PChip emoji="📍" label="Студия" tint={TINT.bubble} pop={POP.bubble} />
          <PChip emoji="✓" label="Записан" tint={TINT.leaf} pop={POP.leaf} />
        </div>
      </KItem>
      <KItem name="Метрики" code="PStat" w="half">
        <div style={{ display: 'flex' }}>
          <PStat n={s.posts} l="записей" hue={POP.coral} />
          <PStat n={s.followers} l="учеников" hue={POP.sky} />
          <PStat n={s.following} l="подписок" hue={POP.grape} />
          <PStat n="128 ч" l="практики" hue={POP.leaf} />
        </div>
      </KItem>
      <KItem name="Хайлайты" code="PHilite" w="half">
        <div className="hrow" style={{ display: 'flex', gap: 12, overflowX: 'auto', scrollbarWidth: 'none', paddingBottom: 2 }}>
          {HILITES.map(([l, e, p, t]) => <PHilite key={l} label={l} emoji={e} pop={p} tint={t} />)}
        </div>
      </KItem>
      <KItem name="Значки занятия" code="KindChips" w="half">
        <KindChips p={(window.MY_CLASSES || [])[0] || { tone: 'a', place: 'studio', dur: 60, kind: 'video' }} />
      </KItem>
    </KSection>
  );
}

/* ── 8. Карточки ──────────────────────────────────────────────── */
function KCards() {
  const post = FEED.find((p) => Array.isArray(p.media) && p.text) || FEED.find((p) => Array.isArray(p.media));
  const mp = (window.MY_PRACTICES || [])[0];
  return (
    <KSection id="cards" title="Карточки" note="Радиус 16–24, тень только у поднятых поверхностей. Обложка — фото с градиентом-фолбэком.">
      <KItem name="Запись в ленте" code="PPostCard" w="half" bare>
        <PPostCard post={post} onLike={() => {}} onTag={() => {}} activeTag={null} />
      </KItem>
      <KItem name="Практика" code="MPracticeCard" w="half">
        {mp && <MPracticeCard p={mp} onToggleSave={() => {}} onOpen={() => {}} />}
      </KItem>
      <KItem name="Обложка занятия" code="ClassCover" w="half">
        <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
          <ClassCover image="morning" size={62} />
          <ClassCover image="studio" size={62} radius={20} />
          <ClassCover image="candle" size={44} radius={14} />
        </div>
      </KItem>
      <KItem name="Медиа в записи" code="PAudio · PVideo" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <PAudio audio={{ dur: '12:40' }} />
          <PVideo image="forest" dur="8:15" />
        </div>
      </KItem>
    </KSection>
  );
}

/* ── 9. Навигация ─────────────────────────────────────────────── */
function KNav() {
  const [tab, setTab] = uK('feed');
  const cref = uKr(null);
  return (
    <KSection id="nav" title="Навигация" note="Верхняя панель и вкладки — липкие, нижняя навигация — стекло с blur(16).">
      <KItem name="Верхняя панель" code="PTopBar" w="half" bare>
        <div style={{ position: 'relative', height: 100 }}><PTopBar onMenu={() => {}} /></div>
      </KItem>
      <KItem name="Вкладки профиля" code="PTabs" w="half" bare>
        <div style={{ position: 'relative', paddingTop: 6 }}><PTabs tab={tab} setTab={setTab} onCreate={() => {}} createRef={cref} createOpen={false} /></div>
      </KItem>
      <KItem name="Нижняя навигация" code="PBottomNav" w="full" bare>
        <div style={{ position: 'relative', height: 92 }}><PBottomNav /></div>
      </KItem>
    </KSection>
  );
}

/* ── 10. Оверлеи ──────────────────────────────────────────────── */
function KOverlays() {
  return (
    <KSection id="overlays" title="Шторки и обратная связь" note="Шторка: радиус 24 сверху, «ручка» 38×5, кнопка действия закреплена внизу. Затемнение — 42% + blur(2).">
      <KItem name="Шторка" code="CALUI.card + grab" w="half" bare>
        <div style={{ position: 'relative', background: 'var(--p-ctrl)', paddingTop: 26 }}>
          <div style={{ ...CALUI.card, padding: '2px 18px 18px' }}>
            <div style={CALUI.grab} />
            <div style={{ fontFamily: "'Quicksand',sans-serif", fontWeight: 800, fontSize: 19, color: 'var(--p-ink)', margin: '6px 0 14px' }}>Утренняя крийя</div>
            <span style={CALUI.label}>Когда</span>
            <input style={CALUI.input} defaultValue="Завтра, 07:30" />
            <button className="pbtn tap" style={CALUI.primary(POP.coral)}>Записаться</button>
            <button className="pbtn tap" style={{ ...CALUI.cancel, boxShadow: 'none', background: 'var(--p-ctrl)' }}>Отмена</button>
          </div>
        </div>
      </KItem>
      <KItem name="Тост · пустое состояние" w="half">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18, alignItems: 'center' }}>
          <div className="ptoast" style={{ background: 'rgba(20,14,30,0.92)', color: '#fff', padding: '11px 18px', borderRadius: 14, fontWeight: 700, fontSize: 13.5 }}>✓ Теперь вы вместе</div>
          <div style={{ textAlign: 'center', padding: '18px 10px' }}>
            <div style={{ fontSize: 30 }}>🪷</div>
            <div style={{ fontFamily: "'Quicksand',sans-serif", fontWeight: 800, fontSize: 15.5, color: 'var(--p-ink)', marginTop: 8 }}>Здесь пока пусто</div>
            <div style={{ fontSize: 13.5, color: 'var(--p-mute)', marginTop: 4 }}>Добавьте первую практику — она появится тут.</div>
          </div>
        </div>
      </KItem>
    </KSection>
  );
}

/* ── страница ─────────────────────────────────────────────────── */
const NAV = [['color', 'Цвет'], ['type', 'Типографика'], ['icons', 'Иконки'], ['buttons', 'Кнопки'],
['controls', 'Поля'], ['avatars', 'Аватары'], ['chips', 'Чипы'], ['cards', 'Карточки'], ['nav', 'Навигация'], ['overlays', 'Шторки']];

function UIKit() {
  const [dark, setDark] = uK(false);
  window.__kdark = dark;
  return (
    <div className={'kpage' + (dark ? ' kdark' : '')}>
      <header className="ktop">
        <div className="kbrand"><span className="kdot" />Yoga Loka · UI Kit</div>
        <nav className="knav">{NAV.map(([id, l]) => <a key={id} href={'#' + id}>{l}</a>)}</nav>
        <button className="kthemebtn" onClick={() => setDark((v) => !v)}>{dark ? '☀️ Светлая' : '🌙 Тёмная'}</button>
      </header>
      <main className="kmain">
        <div className="kintro">
          <h1>Библиотека компонентов</h1>
          <p>Живые элементы из приложения: те же файлы, те же токены. Тема переключается классом <code>.dark</code> на контейнере <code>.ylp</code> — все компоненты наследуют её без правок.</p>
        </div>
        <KColors /><KType /><KIcons /><KButtons /><KControls /><KAvatars /><KChips /><KCards /><KNav /><KOverlays />
      </main>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<UIKit />);
