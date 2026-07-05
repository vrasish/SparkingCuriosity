#!/usr/bin/env python3
"""Stamp Science Fables logo (bottom-right) on every page."""

import os
import sys

import fitz


def main() -> int:
    if len(sys.argv) < 4:
        print("Usage: brand_pdf.py <pdf_path> <logo_path> <copyright_text> [compact|standard]", file=sys.stderr)
        return 1

    pdf_path, logo_path, copyright_text = sys.argv[1:4]
    mode = sys.argv[4].lower() if len(sys.argv) > 4 else "standard"
    compact = mode == "compact"

    if not os.path.isfile(pdf_path):
        print(f"PDF not found: {pdf_path}", file=sys.stderr)
        return 1
    if not os.path.isfile(logo_path):
        print(f"Logo not found: {logo_path}", file=sys.stderr)
        return 1

    doc = fitz.open(pdf_path)
    logo_pix = fitz.Pixmap(logo_path)
    aspect = logo_pix.height / max(logo_pix.width, 1)
    logo_pix = None

    if compact:
        side_margin = 4
        bottom_margin = 5
        target_w = min(doc[0].rect.width * 0.08, 58)
    else:
        side_margin = 12
        bottom_margin = 16
        target_w = min(doc[0].rect.width * 0.14, 95)

    for page_index in range(doc.page_count):
        page = doc[page_index]
        rect = page.rect
        target_h = target_w * aspect
        x1 = rect.x1 - side_margin
        y1 = rect.y1 - bottom_margin
        x0 = x1 - target_w
        y0 = y1 - target_h
        logo_rect = fitz.Rect(x0, y0, x1, y1)

        page.insert_image(logo_rect, filename=logo_path, overlay=True)

    tmp_path = pdf_path + ".branded.tmp.pdf"
    doc.save(tmp_path, garbage=4, deflate=True)
    doc.close()
    os.replace(tmp_path, pdf_path)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
