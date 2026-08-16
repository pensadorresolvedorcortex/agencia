#!/usr/bin/env python3
"""Build a byte-for-byte reproducible installable plugin ZIP."""
import argparse
import hashlib
import pathlib
import re
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "jeito-performance-premium"
ENTRY = PLUGIN / "jeito-performance-premium.php"


def version():
    match = re.search(r"^ \* Version: ([0-9.]+)$", ENTRY.read_text(), re.MULTILINE)
    if not match:
        raise SystemExit("Plugin version header not found.")
    return match.group(1)


def build(output):
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for source in sorted(path for path in PLUGIN.rglob("*") if path.is_file()):
            relative = source.relative_to(ROOT).as_posix()
            info = zipfile.ZipInfo(relative, (2020, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    return hashlib.sha256(output.read_bytes()).hexdigest()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=pathlib.Path)
    args = parser.parse_args()
    output = args.output or ROOT / f"jeito-performance-premium-{version()}.zip"
    digest = build(output)
    print(f"{digest}  {output}")


if __name__ == "__main__":
    main()
