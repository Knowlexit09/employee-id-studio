#!/usr/bin/env bash
set -euo pipefail
find laravel-overlay -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
node --check laravel-overlay/resources/js/id-studio-designer.js
node --check demo/studio-designer.js
python3 - <<'PY'
from html.parser import HTMLParser
from pathlib import Path
import re
class IDs(HTMLParser):
 def __init__(self): super().__init__(); self.ids=[]
 def handle_starttag(self, tag, attrs):
  for k,v in attrs:
   if k=='id': self.ids.append(v)
p=Path('demo/index.html')
h=p.read_text();parser=IDs();parser.feed(h)
assert len(parser.ids)==len(set(parser.ids)), 'Duplicate demo IDs'
js=Path('demo/studio-designer.js').read_text()
for key in re.findall(r"\$\('([^']+)'\)",js):
 assert key in parser.ids, f'Missing demo control: {key}'
assert all(x in h for x in ['id="id-canvas"','id="save-template"','id="prop-fontSize"','data-side="back"'])
print('All template controls found; no duplicate IDs')
PY
