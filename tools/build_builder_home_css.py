#!/usr/bin/env python3
"""Rebuild the audited Bold Builder Home CSS candidate."""
import argparse, hashlib, subprocess, tempfile, zipfile
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
SOURCE_SHA256 = "aa1f40b1a5050ebc86836cb6e79438e7fd0adf3e3f145d564c5251e05e5c5fb2"
REMOVED = ("service_items","tabs","accordions","video","price_list","latest_post","progress_bar","open_map","cost_calculator","counter","twitter_feed","insta_feed","widgets","countdown","css_image_grid","ie","magnific-popup")
def build(source, output):
    if hashlib.sha256(source.read_bytes()).hexdigest() != SOURCE_SHA256: raise RuntimeError("Unexpected Bold Builder archive SHA-256")
    with tempfile.TemporaryDirectory() as d:
        w=Path(d)
        with zipfile.ZipFile(source) as z: z.extractall(w)
        css=w/'bold-page-builder/css/front_end/content_elements.css'
        text=css.read_text()
        for name in REMOVED:
            line=f'@import url("{name}.css");'
            if text.count(line)!=1: raise RuntimeError(f"Expected one import for {name}")
            text=text.replace(line,'')
        css.write_text(text)
        subprocess.run(['composer','require','css-crush/css-crush:5.0.1','--working-dir',str(w),'--no-interaction','--quiet'],check=True)
        runner=w/'build.php'
        runner.write_text("<?php require __DIR__.'/vendor/autoload.php'; csscrush_file($argv[1], ['minify'=>true,'output_file'=>$argv[2]]);")
        subprocess.run(['php',str(runner),str(css),str(output)],check=True)
        generated=css.parent/output.name
        output.parent.mkdir(parents=True,exist_ok=True)
        generated.replace(output)
def main():
    p=argparse.ArgumentParser(); p.add_argument('--source',type=Path,default=ROOT/'bold-page-builder.zip'); p.add_argument('--output',type=Path,default=ROOT/'jeito-performance-premium/assets/builder-home.css'); a=p.parse_args(); build(a.source,a.output); print(a.output.stat().st_size,a.output)
if __name__=='__main__': main()
