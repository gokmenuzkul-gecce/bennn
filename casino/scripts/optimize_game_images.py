"""Normalize lobby cover art to the card's 300x400 ratio.

The lobby crops every cover into a 3:4 card, but 1,128 of the 2,383 stored
JPEGs are far larger than that (some 1024x1024 at ~1 MB). Decoding a
full-size bitmap just to downscale it to a card wastes both the download and
the decode on phones. This rewrites the oversized files in place to 300x400,
progressively encoded; already-small files are left untouched so re-runs are
cheap.

Usage: python3 scripts/optimize_game_images.py
"""
import glob
import os

from PIL import Image

ICO = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))), 'frontend', 'Default', 'ico')
TARGET_W, TARGET_H = 300, 400
TARGET_RATIO = TARGET_W / TARGET_H
SKIP_BYTES = 120 * 1024
SKIP_W, SKIP_H = 320, 420


def main():
    files = glob.glob(os.path.join(ICO, '*.jpg'))
    done = skipped = failed = 0
    before = after = 0

    for path in files:
        try:
            size0 = os.path.getsize(path)
            img = Image.open(path)
            w, h = img.size
            if size0 <= SKIP_BYTES and w <= SKIP_W and h <= SKIP_H:
                skipped += 1
                continue

            img = img.convert('RGB')
            src_ratio = w / h
            if src_ratio > TARGET_RATIO:
                crop_h = h
                crop_w = int(round(h * TARGET_RATIO))
                src_x = (w - crop_w) // 2
                src_y = 0
            else:
                crop_w = w
                crop_h = int(round(w / TARGET_RATIO))
                src_x = 0
                src_y = (h - crop_h) // 2

            cropped = img.crop((src_x, src_y, src_x + crop_w, src_y + crop_h))
            cropped.resize((TARGET_W, TARGET_H), Image.LANCZOS).save(
                path, 'JPEG', quality=80, optimize=True, progressive=True
            )
            after += os.path.getsize(path)
            before += size0
            done += 1
        except Exception as exc:  # noqa: BLE001 - report and continue
            failed += 1
            if failed <= 5:
                print('FAIL', os.path.basename(path), exc)

    print(f'optimize: {done} | atlandi: {skipped} | hata: {failed}')
    print(f'kaydedilen: {round(before / 1024 / 1024, 1)} MB -> {round(after / 1024 / 1024, 1)} MB')


if __name__ == '__main__':
    main()
