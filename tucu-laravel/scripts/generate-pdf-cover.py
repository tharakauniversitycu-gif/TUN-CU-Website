"""Render a PDF's first page as a bounded, high-quality JPEG cover."""
import sys
import json
from pathlib import Path

import pymupdf


source = Path(sys.argv[1])
destination = Path(sys.argv[2])

with pymupdf.open(source) as document:
    if document.needs_pass:
        raise RuntimeError("Password-protected PDFs cannot be used to generate covers.")
    if document.page_count < 1:
        raise RuntimeError("PDF has no pages.")

    page = document.load_page(0)
    bounds = page.rect
    if bounds.width <= 0 or bounds.height <= 0:
        raise RuntimeError("The first PDF page has invalid dimensions.")

    scale = min(3.0, 900 / bounds.width, 1200 / bounds.height)
    image = page.get_pixmap(
        matrix=pymupdf.Matrix(scale, scale),
        colorspace=pymupdf.csRGB,
        alpha=False,
    )
    image.save(destination, jpg_quality=88)
    print(json.dumps({"page_count": document.page_count}))
