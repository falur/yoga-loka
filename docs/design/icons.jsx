// icons.jsx — простые линейные иконки (currentColor). Экспорт в window.
const _i = (paths, vb = '0 0 24 24', sw = 1.7) => ({ size = 22, fill, style, ...p }) => (
  <svg width={size} height={size} viewBox={vb} fill={fill || 'none'}
       stroke="currentColor" strokeWidth={sw} strokeLinecap="round" strokeLinejoin="round"
       style={style} {...p}>{paths}</svg>
);

const IconHeart = ({ size = 22, filled, style }) => (
  <svg width={size} height={size} viewBox="0 0 24 24"
       fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.7"
       strokeLinecap="round" strokeLinejoin="round" style={style}>
    <path d="M12 20.5S3.5 14.8 3.5 9.2A4.7 4.7 0 0 1 12 6.3a4.7 4.7 0 0 1 8.5 2.9c0 5.6-8.5 11.3-8.5 11.3Z"/>
  </svg>
);
const IconComment = _i(<path d="M21 11.5a8.5 8.5 0 0 1-12.3 7.6L3.5 20.5l1.5-5A8.5 8.5 0 1 1 21 11.5Z"/>);
const IconShare = _i(<><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M12 15V3"/><path d="m7.5 7.5 4.5-4.5 4.5 4.5"/></>);
// VK-style «поделиться» — объёмная закрашенная стрелка вперёд
const IconShareVK = ({ size = 22, style, ...p }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
       strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" style={style} {...p}>
    <path d="M14 4 L22 11 L14 18 L14 14.2 C10.5 14.2 7 15.6 4 19.3 C4.2 12.7 7.4 8.3 14 7.8 L14 4 Z"/>
  </svg>
);
const IconBookmark = ({ size = 22, filled, style }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill={filled ? 'currentColor' : 'none'}
       stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" style={style}>
    <path d="M6 4h12a1 1 0 0 1 1 1v15l-7-4-7 4V5a1 1 0 0 1 1-1Z"/>
  </svg>
);
const IconHome = _i(<><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-9"/></>);
const IconSearch = _i(<><circle cx="11" cy="11" r="6.5"/><path d="m20 20-3.6-3.6"/></>);
const IconPlus = _i(<><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><path d="M12 8.5v7M8.5 12h7"/></>);
const IconBell = _i(<><path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z"/><path d="M10 20a2 2 0 0 0 4 0"/></>);
const IconMenu = _i(<><path d="M4 7h16M4 12h16M4 17h16"/></>);
const IconSettings = _i(<><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M5 5l2 2M17 17l2 2M2 12h3M19 12h3M5 19l2-2M17 7l2-2"/></>);
const IconCheck = _i(<path d="m5 12.5 4.5 4.5L19 7"/>, '0 0 24 24', 2.2);
const IconGrid = _i(<><rect x="4" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"/></>);
const IconList = _i(<><path d="M4 6h16M4 12h16M4 18h10"/></>);
const IconSpark = _i(<path d="M12 3c.6 3.8 1.4 4.6 5.2 5.2-3.8.6-4.6 1.4-5.2 5.2-.6-3.8-1.4-4.6-5.2-5.2C10.6 7.6 11.4 6.8 12 3Z"/>, '0 0 24 18');
const IconChevron = _i(<path d="m9 5 7 7-7 7"/>, '0 0 24 24', 2);
const IconClock = _i(<><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8"/></>);
const IconVideo = _i(<><rect x="3" y="6" width="13" height="12" rx="2.5"/><path d="m16 10 4.5-2.6v9.2L16 14"/></>);
const IconAudio = _i(<><path d="M4 9.5h3l4-3.5v12l-4-3.5H4Z"/><path d="M15 9.2a4 4 0 0 1 0 5.6M17.6 7a7 7 0 0 1 0 10"/></>);
// секундомер — отметка «в практике есть таймеры»
const IconTimer = _i(<><path d="M9.5 2.5h5"/><path d="M12 2.5v2.2"/><circle cx="12" cy="13.5" r="7.5"/><path d="M12 13.5V9.5"/><path d="m18 8 1.4-1.4"/></>);
// стопка слоёв — «количество частей в практике»
const IconLayers = _i(<><path d="M12 3 21 7.8 12 12.6 3 7.8 12 3Z"/><path d="M3.4 12 12 16.6 20.6 12"/><path d="M3.4 16.2 12 20.8 20.6 16.2"/></>);
const IconEye = _i(<><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.7"/></>);
const IconCalendar = _i(<><rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/></>);
const IconSend = _i(<><path d="M20 4 3.5 11.5 11 13l2 7L20 4Z"/></>);
const IconBack = _i(<path d="m15 5-7 7 7 7"/>, '0 0 24 24', 2);
const IconFlame = _i(<path d="M12 3s5 4 5 9a5 5 0 0 1-10 0c0-2 1-3 1-3s0 2 1.5 2.5C11 13 10 9 12 3Z"/>);
const IconLotus = _i(<><path d="M12 12c-2-3-2-6 0-9 2 3 2 6 0 9Z"/><path d="M12 12c2.5-2 5.5-2.5 8-1-1.5 3-4 4.5-7 4"/><path d="M12 12c-2.5-2-5.5-2.5-8-1 1.5 3 4 4.5 7 4"/></>);
const IconUser = _i(<><circle cx="12" cy="8" r="3.6"/><path d="M5.5 20c0-3.6 2.9-6.3 6.5-6.3S18.5 16.4 18.5 20"/></>);
const IconUsers = _i(<><circle cx="9" cy="8" r="3.2"/><path d="M3 19c0-3.2 2.7-5.6 6-5.6S15 15.8 15 19"/><path d="M16 5.2a3.2 3.2 0 0 1 0 6.1"/><path d="M17.5 13.6c2.3.6 3.9 2.6 3.9 5.4"/></>);
const IconPin = _i(<><path d="M9.5 3.5h5l-.6 5 3.1 3.2H7l3.1-3.2-.6-5Z"/><path d="M12 11.7V20"/></>, '0 0 24 24', 1.8);
const IconFlag = _i(<><path d="M5.5 21V4"/><path d="M5.5 4.5c4-2 7 2 11 0v9c-4 2-7-2-11 0"/></>);
const IconGlobe = _i(<><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.6 2.4 4 5.7 4 9s-1.4 6.6-4 9c-2.6-2.4-4-5.7-4-9s1.4-6.6 4-9Z"/></>);
const IconLock = _i(<><rect x="5" y="10.5" width="14" height="9.5" rx="2.4"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/></>);
const IconEdit = _i(<><path d="M14.5 5.5 18.5 9.5"/><path d="M4 20l1-4L16 5a2 2 0 0 1 3 3L8 19l-4 1Z"/></>, '0 0 24 24', 1.8);
const IconTrash = _i(<><path d="M4 6.5h16"/><path d="M9.5 6V4.5h5V6"/><path d="M6.5 6.5 7.3 19a1.5 1.5 0 0 0 1.5 1.4h6.4a1.5 1.5 0 0 0 1.5-1.4l.8-12.5"/><path d="M10 10v6M14 10v6"/></>, '0 0 24 24', 1.8);
const IconVerified = ({ size = 16, style }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" style={style}>
    <path fill="currentColor" d="M12 2.5 14 4l2.6-.4.9 2.5 2.3 1.3-.6 2.6 1.4 2.2-1.9 1.8.2 2.6-2.6.5-1.3 2.3-2.4-1-2.3 1.3-1.5-2.1-2.6-.2-.4-2.6-2.2-1.4.8-2.5L4 9.3l.6-2.6 2.5-.6L8.3 3.7 11 4l1-1.5Z"/>
    <path d="m8.5 12 2.4 2.4 4.6-5" stroke="#fff" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"/>
  </svg>
);

Object.assign(window, {
  IconHeart, IconComment, IconShare, IconShareVK, IconBookmark, IconHome, IconSearch, IconPlus,
  IconBell, IconMenu, IconSettings, IconCheck, IconGrid, IconList, IconSpark,
  IconChevron, IconClock, IconVideo, IconAudio, IconTimer, IconLayers, IconEye, IconCalendar, IconSend, IconBack, IconFlame, IconLotus, IconUser, IconUsers, IconVerified, IconPin, IconFlag, IconGlobe, IconLock, IconEdit, IconTrash,
});
