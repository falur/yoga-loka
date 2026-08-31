// sun-yogi.jsx — тёплая «солнечная» иллюстрация: йог медитирует под солнышком.
// Плоская мультяшная сцена из простых форм. Экспорт в window: SunYogiScene.

function SunYogiScene({ style }) {
  // лучи солнца вокруг центра (200,84)
  const rays = [];
  for (let i = 0; i < 12; i++) {
    rays.push(
      <rect key={i} x="196" y="8" width="8" height="22" rx="4" fill="#FFD25C"
            transform={`rotate(${i * 30} 200 84)`} />
    );
  }
  return (
    <svg viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice"
         style={{ width: '100%', height: '100%', display: 'block', ...style }}>
      <defs>
        <linearGradient id="syScSky" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#FFE6B8" />
          <stop offset="1" stopColor="#FFF3DF" />
        </linearGradient>
        <radialGradient id="syScSun" cx="0.5" cy="0.45" r="0.6">
          <stop offset="0" stopColor="#FFEC9E" />
          <stop offset="0.65" stopColor="#FFC83D" />
          <stop offset="1" stopColor="#FFB020" />
        </radialGradient>
        <linearGradient id="syScHillA" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#A7DD8C" />
          <stop offset="1" stopColor="#8FD173" />
        </linearGradient>
        <linearGradient id="syScHillB" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#86CC6A" />
          <stop offset="1" stopColor="#6FBE56" />
        </linearGradient>
      </defs>

      {/* небо */}
      <rect width="400" height="200" fill="url(#syScSky)" />

      {/* облачка */}
      <g fill="#FFFFFF" opacity="0.78">
        <ellipse cx="64" cy="46" rx="26" ry="12" />
        <ellipse cx="86" cy="40" rx="18" ry="11" />
        <ellipse cx="338" cy="58" rx="22" ry="10" />
        <ellipse cx="356" cy="52" rx="15" ry="9" />
      </g>

      {/* лучи + солнце */}
      <g>{rays}</g>
      <circle cx="200" cy="84" r="44" fill="url(#syScSun)" />

      {/* холмы */}
      <ellipse cx="96" cy="226" rx="210" ry="64" fill="url(#syScHillA)" />
      <ellipse cx="320" cy="232" rx="220" ry="66" fill="url(#syScHillB)" />

      {/* небольшие холмики-кустики для глубины */}
      <circle cx="150" cy="196" r="14" fill="#7FC862" opacity="0.7" />
      <circle cx="262" cy="200" r="18" fill="#7FC862" opacity="0.6" />
    </svg>
  );
}

Object.assign(window, { SunYogiScene });
