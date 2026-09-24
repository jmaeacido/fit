"""Generate FIT brand favicons into public/."""
from __future__ import annotations

import io
import os
import struct
from PIL import Image, ImageDraw

OUT = os.path.join(os.path.dirname(__file__), "..", "public")
os.makedirs(OUT, exist_ok=True)

BG = (37, 52, 41, 255)  # #253429
FG = (248, 249, 245, 255)  # #f8f9f5
ACCENT = (118, 169, 70, 255)  # #76a946


def draw_icon(size: int, radius_ratio: float = 0.18, pad_ratio: float = 0.16) -> Image.Image:
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    r = max(1, int(size * radius_ratio))
    draw.rounded_rectangle([0, 0, size - 1, size - 1], radius=r, fill=BG)

    pad = size * pad_ratio
    w = size - 2 * pad
    h = size - 2 * pad
    left, top = pad, pad

    def p(x: float, y: float) -> tuple[float, float]:
        return (left + x * w, top + y * h)

    # Simplified t-shirt: high-contrast silhouette readable at 16px.
    pts = [
        p(0.38, 0.10),
        p(0.42, 0.17),
        p(0.50, 0.21),
        p(0.58, 0.17),
        p(0.62, 0.10),
        p(0.78, 0.20),
        p(0.98, 0.32),
        p(0.90, 0.46),
        p(0.74, 0.38),
        p(0.76, 0.90),
        p(0.24, 0.90),
        p(0.26, 0.38),
        p(0.10, 0.46),
        p(0.02, 0.32),
        p(0.22, 0.20),
    ]
    draw.polygon(pts, fill=FG)

    if size >= 64:
        neck = [p(0.40, 0.12), p(0.44, 0.19), p(0.50, 0.23), p(0.56, 0.19), p(0.60, 0.12)]
        draw.line(neck, fill=ACCENT, width=max(1, size // 64))

    return img


def write_ico(path: str, images: list[Image.Image]) -> None:
    """Write a multi-size ICO with embedded PNG frames."""
    blobs: list[bytes] = []
    for im in images:
        buf = io.BytesIO()
        im.save(buf, format="PNG")
        blobs.append(buf.getvalue())

    n = len(images)
    offset = 6 + 16 * n
    out = bytearray()
    out += struct.pack("<HHH", 0, 1, n)
    data = b""
    for im, blob in zip(images, blobs):
        w, h = im.size
        out += struct.pack(
            "<BBBBHHII",
            w if w < 256 else 0,
            h if h < 256 else 0,
            0,
            0,
            1,
            32,
            len(blob),
            offset + len(data),
        )
        data += blob
    out += data
    with open(path, "wb") as f:
        f.write(out)


def main() -> None:
    specs = {
        "favicon-16x16.png": (16, 0.22, 0.10),
        "favicon-32x32.png": (32, 0.22, 0.12),
        "apple-touch-icon.png": (180, 0.18, 0.16),
        "android-chrome-192x192.png": (192, 0.18, 0.16),
        "android-chrome-512x512.png": (512, 0.18, 0.16),
    }

    for name, (size, radius, pad) in specs.items():
        im = draw_icon(size, radius, pad)
        path = os.path.join(OUT, name)
        im.save(path, "PNG", optimize=True)
        print(f"wrote {name} {im.size}")

    write_ico(
        os.path.join(OUT, "favicon.ico"),
        [
            draw_icon(16, 0.22, 0.10),
            draw_icon(32, 0.22, 0.12),
            draw_icon(48, 0.20, 0.14),
        ],
    )
    print("wrote favicon.ico")

    svg = """<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" role="img" aria-label="FIT">
  <style>
    .bg { fill: #253429; }
    .shirt { fill: #f8f9f5; }
    @media (prefers-color-scheme: dark) {
      .bg { fill: #f8f9f5; }
      .shirt { fill: #253429; }
    }
  </style>
  <rect class="bg" width="32" height="32" rx="7"/>
  <path class="shirt" d="M12.16 3.2c.64 1.28 1.92 2.24 3.84 2.24s3.2-.96 3.84-2.24l5.12 3.2 2.56 3.84-2.56 4.48-5.12-2.56.64 15.36H11.52l.64-15.36-5.12 2.56L4.48 10.24l2.56-3.84 5.12-3.2z"/>
</svg>
"""
    with open(os.path.join(OUT, "favicon.svg"), "w", encoding="utf-8") as f:
        f.write(svg)
    print("wrote favicon.svg")

    manifest = """{
  "name": "FIT",
  "short_name": "FIT",
  "description": "Find your fit. Choose your size and favorite shirt designs.",
  "icons": [
    {
      "src": "/android-chrome-192x192.png",
      "sizes": "192x192",
      "type": "image/png"
    },
    {
      "src": "/android-chrome-512x512.png",
      "sizes": "512x512",
      "type": "image/png"
    }
  ],
  "theme_color": "#253429",
  "background_color": "#f8f9f5",
  "display": "standalone"
}
"""
    with open(os.path.join(OUT, "site.webmanifest"), "w", encoding="utf-8") as f:
        f.write(manifest)
    print("wrote site.webmanifest")


if __name__ == "__main__":
    main()
