#!/usr/bin/env python3
import subprocess
import tempfile
import importlib.util
from pathlib import Path

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("build_aiko_home_css", root / "tools/build_aiko_home_css.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
with tempfile.TemporaryDirectory() as directory:
    output = Path(directory) / "aiko-home.css"
    subprocess.run(["python3", str(root / "tools/build_aiko_home_css.py"), "--output", str(output)], check=True)
    assert output.read_bytes() == (root / "jeito-performance-premium/assets/aiko-home.css").read_bytes()
    invalid = Path(directory) / "invalid.zip"
    invalid.write_bytes(b"not the audited archive")
    try:
        module.build(invalid, output)
        raise AssertionError("Unexpected archive should be rejected")
    except RuntimeError as error:
        assert "Unexpected Aiko archive SHA-256" in str(error)
print("Aiko Home CSS reproducibility test passed.")
