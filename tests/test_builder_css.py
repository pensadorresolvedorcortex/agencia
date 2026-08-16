#!/usr/bin/env python3
import subprocess,tempfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as d:
 out=Path(d)/'builder-home.css'
 subprocess.run(['python3',str(root/'tools/build_builder_home_css.py'),'--output',str(out)],check=True)
 assert out.read_bytes()==(root/'jeito-performance-premium/assets/builder-home.css').read_bytes()
print('Builder Home CSS reproducibility test passed.')
