#!/usr/bin/env python3
"""Generate clean white wordmark SVGs for casino providers.

The strip renders every *.svg in this directory, so each brand gets a crisp
white wordmark that reads well on the dark background. Output is written to
both the served path (casino/public/...) and the repo-root mirror.
"""
import html
import os

PROVIDERS = {
    "playngo": "PLAY'N GO",
    "egt": "EGT",
    "evolution": "EVOLUTION",
    "isoftbet": "iSOFTBET",
    "gamomat": "GAMOMAT",
    "playtech": "PLAYTECH",
    "amatic": "AMATIC",
    "aristocrat": "ARISTOCRAT",
    "casino-technology": "CT GAMING",
    "greentube": "GREENTUBE",
    "igrosoft": "IGROSOFT",
    "netent": "NETENT",
    "igtech": "IGTECH",
    "novomatic": "NOVOMATIC",
    "pragmatic": "PRAGMATIC PLAY",
    "skywind": "SKYWIND",
    "mainama": "MAINAMA",
    "ka-gaming": "KA GAMING",
    "wazdan": "WAZDAN",
    "vision": "VISION",
    "playgt": "PLAYGT",
    "cq9": "CQ9",
    "gdgames": "GD GAMES",
    "betsoft": "BETSOFT",
    "netgame": "NETGAME",
    "pgsoft": "PG SOFT",
    "amusnet": "AMUSNET",
}

HEIGHT = 120
FONT_SIZE = 32
LETTER_SPACING = 2.2


def make_svg(name: str) -> str:
    label = html.escape(name)
    # Approximate advance width of the uppercase wordmark at this size/weight.
    text_w = sum(0.68 if ch not in "I' " else 0.34 for ch in name) * FONT_SIZE
    text_w += LETTER_SPACING * max(len(name) - 1, 0)
    pad = 10
    width = int(text_w + pad * 2)
    baseline = HEIGHT / 2 + FONT_SIZE * 0.35
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {HEIGHT}" role="img" aria-label="{label}">
  <defs>
    <linearGradient id="w" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffffff"/>
      <stop offset="1" stop-color="#c3ccdb"/>
    </linearGradient>
  </defs>
  <text x="{pad}" y="{baseline:.1f}" font-family="'Plus Jakarta Sans','Segoe UI',Arial,Helvetica,sans-serif"
        font-size="{FONT_SIZE}" font-weight="800" letter-spacing="{LETTER_SPACING}"
        fill="url(#w)">{label}</text>
</svg>
'''


def main() -> None:
    repo_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    targets = [
        os.path.join(repo_root, "casino", "public", "frontend", "Default", "provider-logos"),
        os.path.join(repo_root, "frontend", "Default", "provider-logos"),
    ]
    for out_dir in targets:
        os.makedirs(out_dir, exist_ok=True)
        for slug, name in PROVIDERS.items():
            with open(os.path.join(out_dir, slug + ".svg"), "w", encoding="utf-8") as fh:
                fh.write(make_svg(name))
        print(f"wrote {len(PROVIDERS)} logos to {out_dir}")


if __name__ == "__main__":
    main()
