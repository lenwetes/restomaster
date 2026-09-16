<svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" {{ $attributes }}>
  <defs>
    <linearGradient id="restoGlow" x1="20" y1="20" x2="180" y2="180" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#FF5436"/>
      <stop offset="100%" stop-color="#E11D48"/>
    </linearGradient>
    <linearGradient id="goldAccent" x1="60" y1="40" x2="140" y2="160" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#FDE047"/>
      <stop offset="100%" stop-color="#CA8A04"/>
    </linearGradient>
    <radialGradient id="plateInner" cx="100" cy="100" r="80" gradientUnits="userSpaceOnUse">
      <stop offset="60%" stop-color="#1C1917"/>
      <stop offset="100%" stop-color="#0C0A09"/>
    </radialGradient>
  </defs>

  <!-- Outer Ring with Artisan Terracotta & Gold Stitching -->
  <circle cx="100" cy="100" r="92" fill="url(#plateInner)" stroke="url(#restoGlow)" stroke-width="4"/>
  <circle cx="100" cy="100" r="85" stroke="#F59E0B" stroke-width="1.5" stroke-dasharray="6 4" opacity="0.7"/>

  <!-- Inner Plate Rim -->
  <circle cx="100" cy="100" r="74" fill="#1C1917" stroke="#292524" stroke-width="2"/>

  <!-- Gourmet Cloche (Campana de Restaurante) -->
  <!-- Cloche Handle / Knob -->
  <circle cx="100" cy="62" r="7" fill="url(#goldAccent)"/>
  <path d="M97 69H103V74H97V69Z" fill="url(#goldAccent)"/>

  <!-- Dome -->
  <path d="M52 118C52 86 73 74 100 74C127 74 148 86 148 118H52Z" fill="url(#restoGlow)"/>

  <!-- Cloche Rim & Base Plate -->
  <rect x="44" y="118" width="112" height="8" rx="4" fill="url(#goldAccent)"/>
  <rect x="36" y="128" width="128" height="4" rx="2" fill="#E7E5E4" opacity="0.8"/>

  <!-- Elegant Crossed Cutlery (Fork & Knife) Accent on Bottom -->
  <g transform="translate(100, 154) scale(0.75)">
    <!-- Fork -->
    <path d="M-22 -8L-14 8M-26 -8L-18 8M-18 -8L-18 8M-18 8L-18 20" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>
    <!-- Knife -->
    <path d="M18 -8C22 -4 22 2 18 8L18 20M18 -8L14 -8L14 8L18 8" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>
  </g>

  <!-- 3 Stars of Culinary Excellence on top -->
  <!-- Center Star -->
  <path d="M100 42L101.8 46.5L106.5 46.8L102.8 49.8L104 54.4L100 51.8L96 54.4L97.2 49.8L93.5 46.8L98.2 46.5Z" fill="#FACC15"/>
  <!-- Left Star -->
  <path d="M78 47L79.3 50.4L82.8 50.6L80.1 52.8L81 56.3L78 54.3L75 56.3L75.9 52.8L73.2 50.6L76.7 50.4Z" fill="#FACC15" opacity="0.8"/>
  <!-- Right Star -->
  <path d="M122 47L123.3 50.4L126.8 50.6L124.1 52.8L125 56.3L122 54.3L119 56.3L119.9 52.8L117.2 50.6L120.7 50.4Z" fill="#FACC15" opacity="0.8"/>

  <!-- Stylized "M" Crest in Center of Cloche -->
  <text x="100" y="106" fill="#FFFFFF" font-family="'Plus Jakarta Sans', sans-serif" font-size="22" font-weight="900" text-anchor="middle" letter-spacing="1">RM</text>
</svg>
