"""Reproducibly install only PDF.js browser assets. Optional argument: cached tgz."""
import base64
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import sys
import tarfile
import urllib.request

root = Path(__file__).resolve().parents[1]
lock = json.loads((root / 'scripts/pdfjs.lock.json').read_text())
data = Path(sys.argv[1]).read_bytes() if len(sys.argv) > 1 else urllib.request.urlopen(lock['url'], timeout=60).read()
assert 'sha512-' + base64.b64encode(hashlib.sha512(data).digest()).decode() == lock['integrity'], 'PDF.js integrity mismatch'
out = root / 'public/vendor/pdfjs'
with tarfile.open(fileobj=io.BytesIO(data), mode='r:gz') as archive:
    for entry in archive.getmembers():
        path = PurePosixPath(entry.name)
        assert path.parts[0] == 'package' and '..' not in path.parts
        relative = PurePosixPath(*path.parts[1:])
        name = str(relative)
        if entry.isfile() and (name in ['build/pdf.mjs', 'build/pdf.worker.mjs', 'web/pdf_viewer.css', 'LICENSE'] or name.startswith(('cmaps/', 'standard_fonts/', 'wasm/'))):
            target = out.joinpath(*relative.parts)
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(archive.extractfile(entry).read())
(out / 'version.json').write_text(json.dumps(lock, indent=2))
print('Bundled PDF.js ' + lock['version'] + ' with verified SHA-512 integrity')
