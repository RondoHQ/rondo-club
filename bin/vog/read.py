"""Read a VOG locally. Never rewrite the source or make network requests."""
import json
import logging
import resource
import sys
from pathlib import Path

if sys.platform.startswith('linux'):
    resource.setrlimit(resource.RLIMIT_AS, (256 * 1024 * 1024,) * 2)
resource.setrlimit(resource.RLIMIT_CPU, (8, 8))
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'vendor/vog-python'))
logging.disable(logging.CRITICAL)

try:
    from pypdf import PdfReader
    from pypdf._crypt_providers import crypt_provider
    if crypt_provider[0] == 'local_crypt_fallback':
        raise RuntimeError('crypto_unavailable')
    if len(sys.argv) == 2 and sys.argv[1] == '--health':
        print(json.dumps({'available': True}))
        sys.exit(0)
    reader = PdfReader(sys.argv[1])
    count = len(reader.pages)
    if count < 1 or count > 5:
        raise ValueError('page_limit')
    text = reader.pages[0].extract_text()
    if len(text.encode('utf-8')) > 128 * 1024:
        raise ValueError('text_limit')
    print(json.dumps({'pages': count, 'text': text}, ensure_ascii=True))
except Exception:
    # Parser errors can contain document text and must never enter logs.
    print(json.dumps({'error': 'pdf_unreadable'}))
    sys.exit(1)
