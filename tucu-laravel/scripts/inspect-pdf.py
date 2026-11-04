"""Read-only inspection. Encrypted files remain quarantined for editorial review."""
import json
import sys
from pypdf import PdfReader

try:
    pdf = PdfReader(sys.argv[1])
    encrypted = pdf.is_encrypted
    pages = len(pdf.pages)
    # Force every page object to parse; do not guess counts from PDF text.
    for page in pdf.pages:
        _ = page.mediabox
    print(json.dumps({'page_count': pages, 'encrypted': encrypted,
                      'validation_status': 'review' if encrypted else 'valid'}))
except Exception:
    print(json.dumps({'page_count': None, 'encrypted': True, 'validation_status': 'review'}))
