#!/usr/bin/env python3
"""Stamp Sparking Curiosity logo (bottom-right) on every page."""

import os
import sys

import fitz


def main() -> int:
    if len(sys.argv) != 4:
        print("Usage: brand_pdf.py <pdf_path> <logo_path> <copyright_text>", file=sys.stderr)
        return 1

    pdf_path, logo_path, copyright_text = sys.argv[1:4]
    if not os.path.isfile(pdf_path):
        print(f"PDF not found: {pdf_path}", file=sys.stderr)
        return 1
    if not os.path.isfile(logo_path):
        print(f"Logo not found: {logo_path}", file=sys.stderr)
        return 1

    doc = fitz.open(pdf_path)
    logo_pix = fitz.Pixmap(logo_path)
    aspect = logo_pix.height / max(logo_pix.width, 1)

    for page_index in range(doc.page_count):
        page = doc[page_index]
        rect = page.rect
        margin = 5
        target_w = min(rect.width * 0.22, 170)
        target_h = target_w * aspect
        x1 = rect.x1 - margin
        y1 = rect.y1 - margin
        x0 = x1 - target_w
        y0 = y1 - target_h
        logo_rect = fitz.Rect(x0, y0, x1, y1)
        pad = 2

        # Tight white patch only — enough to read on dark art, flush to the corner.
        bg_rect = fitz.Rect(x0 - pad, y0 - pad, x1 + pad, y1 + pad)
        page.draw_rect(bg_rect, color=(1, 1, 1), fill=(1, 1, 1), overlay=True)
        page.insert_image(logo_rect, filename=logo_path, overlay=True)

    rewrite = getattr(doc, "rewrite_images", None)
    if callable(rewrite):
        try:
            rewrite(dpi_threshold=150, dpi_target=96, quality=75)
        except TypeError:
            try:
                rewrite()
            except Exception:
                pass

    tmp_path = pdf_path + ".branded.tmp.pdf"
    doc.save(tmp_path, garbage=4, deflate=True, clean=True)
    doc.close()
    os.replace(tmp_path, pdf_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
