#!/usr/bin/env python3
"""Rebuild the opt-in Aiko Home stylesheet from the audited theme archive."""
from __future__ import annotations

import argparse
import hashlib
import subprocess
import tempfile
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
AIKO_SHA256 = "7619fac210fdaf70720cd2ce75b9a866f2f7db719e2b87aaf2e671f3c524752e"
SASS_PACKAGE = "sass@1.102.0"
REMOVED_USES = {
    "aiko/framework/assets/scss/framework-loader.scss": ("@use 'woocommerce';",),
    "aiko/assets/scss/theme-loader.scss": ("@use 'woocommerce';", "@use 'single-product';"),
}


def build(source: Path, output: Path) -> None:
    digest = hashlib.sha256(source.read_bytes()).hexdigest()
    if digest != AIKO_SHA256:
        raise RuntimeError(f"Unexpected Aiko archive SHA-256: {digest}")
    with tempfile.TemporaryDirectory() as directory:
        work = Path(directory)
        with zipfile.ZipFile(source) as archive:
            archive.extractall(work)
        for relative, statements in REMOVED_USES.items():
            path = work / relative
            text = path.read_text(encoding="utf-8-sig")
            for statement in statements:
                if text.count(statement) != 1:
                    raise RuntimeError(f"Expected one {statement!r} in {relative}")
                text = text.replace(statement, "")
            path.write_text(text, encoding="utf-8")
        output.parent.mkdir(parents=True, exist_ok=True)
        subprocess.run(
            ["npx", "--yes", SASS_PACKAGE, "--quiet", "--no-source-map", "--style=compressed", "style.scss", str(output)],
            cwd=work / "aiko",
            check=True,
        )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=Path, default=ROOT / "aiko.zip")
    parser.add_argument("--output", type=Path, default=ROOT / "jeito-performance-premium/assets/aiko-home.css")
    args = parser.parse_args()
    build(args.source, args.output)
    print(f"{args.output.stat().st_size} {args.output}")


if __name__ == "__main__":
    main()
