#!/usr/bin/env python3
import hashlib
import pathlib
import subprocess
import sys
import tempfile
import zipfile

root = pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as directory:
    rebuilt = pathlib.Path(directory) / "first.zip"
    second = pathlib.Path(directory) / "second.zip"
    subprocess.run([sys.executable, str(root / "tools/build_package.py"), "--output", str(rebuilt)], check=True)
    subprocess.run([sys.executable, str(root / "tools/build_package.py"), "--output", str(second)], check=True)
    assert hashlib.sha256(rebuilt.read_bytes()).digest() == hashlib.sha256(second.read_bytes()).digest()
    with zipfile.ZipFile(rebuilt) as package:
        names = package.namelist()
        assert "jeito-performance-premium/jeito-performance-premium.php" in names
        assert not any(name.startswith("jeito-performance-premium/jeito-performance-premium/") for name in names)
        assert not any("__pycache__" in name for name in names)
print("Reproducible package tests passed.")
