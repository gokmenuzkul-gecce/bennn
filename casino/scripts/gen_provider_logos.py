#!/usr/bin/env python3
"""Generate modern, transparent provider logo SVGs into the served frontend dir.

Style: a glassy gradient monogram mark (outlined, translucent fill) plus a white
wordmark. Fully transparent background so the logos sit on any surface.
"""
import os

DIRS = [
    "/workspace/project/frontend/Default/provider-logos",
    "/workspace/project/casino/public/frontend/Default/provider-logos",
]

# slug: (label, primary, accent, glyph)
BRANDS = {
    "pragmatic":         ("PRAGMATIC",     "#f43f5e", "#fb7185", "slot"),
    "pgsoft":            ("PG SOFT",       "#0ea5e9", "#22d3ee", "dice"),
    "amatic":            ("AMATIC",        "#f59e0b", "#fbbf24", "crown"),
    "amusnet":           ("AMUSNET",       "#a855f7", "#e879f9", "star"),
    "egt":               ("EGT",           "#22c55e", "#4ade80", "bolt"),
    "novomatic":         ("NOVOMATIC",     "#ef4444", "#f87171", "slot"),
    "evolution":         ("EVOLUTION",     "#fb7185", "#fda4af", "card"),
    "playngo":           ("PLAY'N GO",     "#eab308", "#facc15", "star"),
    "igtech":            ("IGTECH",        "#06b6d4", "#67e8f9", "bolt"),
    "playtech":          ("PLAYTECH",      "#22c55e", "#86efac", "chip"),
    "greentube":         ("GREENTUBE",     "#2dd4bf", "#5eead4", "diamond"),
    "wazdan":            ("WAZDAN",        "#fb923c", "#fdba74", "bolt"),
    "gamomat":           ("GAMOMAT",       "#a78bfa", "#c4b5fd", "dice"),
    "isoftbet":          ("ISOFTBET",      "#60a5fa", "#93c5fd", "chip"),
    "aristocrat":        ("ARISTOCRAT",    "#fbbf24", "#fcd34d", "crown"),
    "casino-technology": ("C-TECHNOLOGY",  "#2dd4bf", "#5eead4", "chip"),
    "igrosoft":          ("IGROSOFT",      "#a78bfa", "#c4b5fd", "slot"),
    "netent":            ("NETENT",        "#60a5fa", "#93c5fd", "star"),
    "skywind":           ("SKYWIND",       "#22d3ee", "#67e8f9", "bolt"),
    "mainama":           ("MAINAMA",       "#f472b6", "#f9a8d4", "diamond"),
    "ka-gaming":         ("KA GAMING",     "#fb923c", "#fdba74", "dice"),
    "vision":            ("VISION",        "#818cf8", "#a5b4fc", "star"),
    "playgt":            ("PLAYGT",        "#2dd4bf", "#5eead4", "chip"),
    "cq9":               ("CQ9 GAMING",    "#e879f9", "#f0abfc", "slot"),
    "gdgames":           ("GD GAMES",      "#4ade80", "#86efac", "diamond"),
    "betsoft":           ("BETSOFT",       "#fb7185", "#fda4af", "card"),
    "netgame":           ("NETGAME",       "#60a5fa", "#93c5fd", "bolt"),
}

# Glyphs drawn inside the mark box (x 10..86, y 22..98), centre (48, 60).
G = {
    "card": '<rect x="36" y="38" width="24" height="44" rx="5" fill="none" stroke="url(#g)" stroke-width="3"/>'
            '<path d="M48 50 C53 58 58 62 58 67 A6 6 0 0 1 38 67 C38 62 43 58 48 50 Z" fill="url(#g)"/>',
    "chip": '<circle cx="48" cy="60" r="21" fill="none" stroke="url(#g)" stroke-width="3"/>'
            '<circle cx="48" cy="60" r="13" fill="none" stroke="url(#g)" stroke-width="3" stroke-dasharray="6 5"/>'
            '<circle cx="48" cy="60" r="5" fill="url(#g)"/>',
    "dice": '<rect x="34" y="46" width="28" height="28" rx="7" fill="none" stroke="url(#g)" stroke-width="3"/>'
            '<circle cx="42" cy="54" r="2.6" fill="url(#g)"/><circle cx="54" cy="54" r="2.6" fill="url(#g)"/>'
            '<circle cx="48" cy="60" r="2.6" fill="url(#g)"/><circle cx="42" cy="66" r="2.6" fill="url(#g)"/>'
            '<circle cx="54" cy="66" r="2.6" fill="url(#g)"/>',
    "diamond": '<path d="M48 38 L68 60 L48 82 L28 60 Z" fill="none" stroke="url(#g)" stroke-width="3"/>'
               '<path d="M48 47 L59 60 L48 73 L37 60 Z" fill="url(#g)"/>',
    "star": '<path d="M48 36 L56 54 L75 57 L61 70 L64 89 L48 79 L32 89 L35 70 L21 57 L40 54 Z" fill="url(#g)"/>',
    "bolt": '<path d="M53 34 L32 62 L46 62 L43 86 L66 56 L50 56 Z" fill="url(#g)"/>',
    "crown": '<path d="M28 76 L34 46 L45 60 L48 40 L51 60 L62 46 L68 76 Z" fill="none" stroke="url(#g)" stroke-width="3" stroke-linejoin="round"/>'
             '<circle cx="34" cy="43" r="3" fill="url(#g)"/><circle cx="48" cy="37" r="3" fill="url(#g)"/><circle cx="62" cy="43" r="3" fill="url(#g)"/>',
    "slot": '<rect x="30" y="44" width="36" height="32" rx="6" fill="none" stroke="url(#g)" stroke-width="3"/>'
            '<rect x="36" y="51" width="6" height="18" rx="2" fill="url(#g)"/>'
            '<rect x="45" y="51" width="6" height="18" rx="2" fill="url(#g)" opacity="0.8"/>'
            '<rect x="54" y="51" width="6" height="18" rx="2" fill="url(#g)" opacity="0.55"/>',
}

TPL = """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} 120" role="img" aria-label="{label}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{p}"/><stop offset="1" stop-color="{a}"/>
    </linearGradient>
    <linearGradient id="t" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#c7d2e0"/>
    </linearGradient>
  </defs>
  <rect x="10" y="22" width="76" height="76" rx="22" fill="url(#g)" opacity="0.12"/>
  <rect x="10" y="22" width="76" height="76" rx="22" fill="none" stroke="url(#g)" stroke-width="2.5"/>
  {glyph}
  <text x="100" y="74" font-family="'Inter','Segoe UI',Arial,Helvetica,sans-serif" font-size="{fs}"
        font-weight="800" letter-spacing="1.5" fill="url(#t)">{label}</text>
</svg>
"""


def font_size(label):
    n = len(label)
    if n <= 6:
        return 32
    if n <= 9:
        return 28
    if n <= 12:
        return 24
    return 21


def width_for(label):
    return int(100 + len(label) * (font_size(label) * 0.66) + 16)


def main():
    for d in DIRS:
        os.makedirs(d, exist_ok=True)
    count = 0
    for slug, (label, primary, accent, glyph) in BRANDS.items():
        svg = TPL.format(
            w=width_for(label), label=label, p=primary, a=accent,
            glyph=G[glyph], fs=font_size(label),
        )
        for d in DIRS:
            with open(os.path.join(d, slug + ".svg"), "w", encoding="utf-8") as fh:
                fh.write(svg)
        count += 1
    print("yazildi:", count, "logo")


if __name__ == "__main__":
    main()
