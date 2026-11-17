#!/usr/bin/env bash
set -euo pipefail

# Static checks, no Composer dependencies needed.
find app bootstrap config database routes tests public -name '*.php' -print0 | xargs -0 -r -n1 php -l >/dev/null
node --check resources/js/id-studio-designer.js
node --check demo/studio-designer.js
python3 - <<'PY'
from html.parser import HTMLParser
from pathlib import Path
import re

class IDs(HTMLParser):
    def __init__(self):
        super().__init__()
        self.ids = []
    def handle_starttag(self, tag, attrs):
        for key, value in attrs:
            if key == 'id':
                self.ids.append(value)

html = Path('demo/index.html').read_text()
parser = IDs()
parser.feed(html)
assert len(parser.ids) == len(set(parser.ids)), 'Duplicate demo element IDs'
js = Path('demo/studio-designer.js').read_text()
for key in re.findall(r"\$\('([^']+)'\)", js):
    assert key in parser.ids, f'Missing demo control: {key}'
assert all(token in html for token in ['id="id-canvas"', 'id="save-template"', 'id="prop-fontSize"', 'data-side="back"'])
print('PHP/JS syntax checks and demo markup assertions passed')
PY
