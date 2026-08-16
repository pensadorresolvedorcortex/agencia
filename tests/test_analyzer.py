#!/usr/bin/env python3
import json
import pathlib
import subprocess
import sys

root = pathlib.Path(__file__).resolve().parents[1]
result = subprocess.run(
    [sys.executable, str(root / "tools/analyze_inputs.py")],
    check=True,
    capture_output=True,
    text=True,
)
report = json.loads(result.stdout)
assert report["inputs"]["aiko_style_bytes"] == 920153
assert report["har"]["ea034.css"][0]["decoded_bytes"] == 810676
assert report["mhtml_dom"]["elements"] == 2036
assert report["mhtml_dom"]["sections"] == 22
assert len(report["source_maps"]["aiko_scss"]) == 47
assert len(report["source_maps"]["builder_css_imports"]) == 32
assert report["source_maps"]["builder_css_imports"]["grid.css"]["likely_used_in_snapshot"] is True
media = report["source_maps"]["aiko_media"]
assert media["top_level_blocks"] == 73
assert media["desktop_candidate_blocks"] == 0
assert media["mobile_max_768_blocks"] == 38
print("Analyzer fixture tests passed.")
