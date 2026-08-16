#!/usr/bin/env python3
"""Reproducible, dependency-free metrics for the supplied HAR and archives."""
import json
import pathlib
import sys
import zipfile
import re
from collections import defaultdict
from email import policy
from email.parser import BytesParser
from html.parser import HTMLParser


ROOT = pathlib.Path(__file__).resolve().parents[1]


class DomCounter(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.tags = defaultdict(int)
        self.classes = defaultdict(int)

    def handle_starttag(self, tag, attrs):
        self.tags[tag] += 1
        for name, value in attrs:
            if name == "class" and value:
                for token in value.split():
                    self.classes[token] += 1


def parse_mhtml():
    message = BytesParser(policy=policy.default).parsebytes((ROOT / "01.mhtml").read_bytes())
    html = next((part.get_content() for part in message.walk() if part.get_content_type() == "text/html"), "")
    counter = DomCounter()
    counter.feed(html)
    builder_classes = sum(count for name, count in counter.classes.items() if name.startswith("bt_bb_"))
    return {
        "decoded_html_bytes": len(html.encode("utf-8")),
        "elements": sum(counter.tags.values()),
        "sections": counter.tags["section"],
        "builder_class_references": builder_classes,
        "top_tags": dict(sorted(counter.tags.items(), key=lambda item: item[1], reverse=True)[:12]),
        "class_names": sorted(counter.classes),
    }


def archive_member_size(archive, member):
    with zipfile.ZipFile(ROOT / archive) as package:
        return package.getinfo(member).file_size


def css_media_inventory(css):
    """Return top-level @media byte ranges without trying to interpret selectors."""
    blocks = []
    index = 0
    depth = 0
    quote = None
    comment = False
    while index < len(css):
        pair = css[index:index + 2]
        if comment:
            if pair == "*/":
                comment = False
                index += 2
                continue
        elif quote:
            if css[index] == "\\":
                index += 2
                continue
            if css[index] == quote:
                quote = None
        elif pair == "/*":
            comment = True
            index += 2
            continue
        elif css[index] in "\"'":
            quote = css[index]
        elif css[index] == "{":
            depth += 1
        elif css[index] == "}":
            depth -= 1
        elif depth == 0 and css.startswith("@media", index):
            opening = css.find("{", index)
            if opening < 0:
                break
            condition = css[index + 6:opening].strip()
            cursor, nested = opening + 1, 1
            inner_quote, inner_comment = None, False
            while cursor < len(css) and nested:
                inner_pair = css[cursor:cursor + 2]
                if inner_comment:
                    if inner_pair == "*/":
                        inner_comment = False
                        cursor += 2
                        continue
                elif inner_quote:
                    if css[cursor] == "\\":
                        cursor += 2
                        continue
                    if css[cursor] == inner_quote:
                        inner_quote = None
                elif inner_pair == "/*":
                    inner_comment = True
                    cursor += 2
                    continue
                elif css[cursor] in "\"'":
                    inner_quote = css[cursor]
                elif css[cursor] == "{":
                    nested += 1
                elif css[cursor] == "}":
                    nested -= 1
                cursor += 1
            blocks.append({"condition": condition, "bytes": cursor - index})
            index = cursor
            continue
        index += 1
    desktop = [block for block in blocks if "min-width" in block["condition"]]
    mobile = []
    for block in blocks:
        match = re.search(r"max-width\s*:\s*(\d+)px", block["condition"])
        if match and int(match.group(1)) <= 768:
            mobile.append(block)
    return {
        "top_level_blocks": len(blocks),
        "top_level_bytes": sum(block["bytes"] for block in blocks),
        "desktop_candidate_blocks": len(desktop),
        "desktop_candidate_bytes": sum(block["bytes"] for block in desktop),
        "mobile_max_768_blocks": len(mobile),
        "mobile_max_768_bytes": sum(block["bytes"] for block in mobile),
        "conditions": blocks,
    }


def source_maps(dom):
    with zipfile.ZipFile(ROOT / "aiko.zip") as package:
        theme_modules = {}
        for info in package.infolist():
            if info.filename.startswith(("aiko/assets/scss/", "aiko/framework/assets/scss/")) and info.filename.endswith(".scss"):
                theme_modules[info.filename.removeprefix("aiko/")] = info.file_size
        style_css = package.read("aiko/style.css").decode("utf-8")
    with zipfile.ZipFile(ROOT / "bold-page-builder.zip") as package:
        manifest = package.read("bold-page-builder/css/front_end/content_elements.css").decode("utf-8")
        manifest = re.sub(r'/\*.*?\*/', '', manifest, flags=re.DOTALL)
        imports = re.findall(r'@import\s+url\(["\']([^"\']+\.css)["\']\)', manifest)
        builder_modules = {}
        classes = set(dom["class_names"])
        for name in imports:
            member = "bold-page-builder/css/front_end/" + name
            size = package.getinfo(member).file_size
            stem = name.removesuffix(".css")
            candidates = {"bt_bb_" + stem, "bt_bb_" + stem.rstrip("s")}
            core = stem in {"base", "grid", "sections", "screens", "animations"}
            used = core or any(any(cls.startswith(candidate) for candidate in candidates) for cls in classes)
            builder_modules[name] = {"source_bytes": size, "likely_used_in_snapshot": used}
    return {
        "aiko_scss": dict(sorted(theme_modules.items(), key=lambda item: item[1], reverse=True)),
        "aiko_media": css_media_inventory(style_css),
        "builder_css_imports": builder_modules,
    }


def main():
    har = json.loads((ROOT / "GTmetrix-www.studioprivilege.com.br-20260815T145916-O8d1Lhax.har").read_text())
    resources = {}
    totals = defaultdict(lambda: {"requests": 0, "decoded_bytes": 0, "transfer_bytes": 0})
    for entry in har["log"]["entries"]:
        url = entry["request"]["url"]
        mime = entry["response"]["content"].get("mimeType", "unknown").split(";", 1)[0]
        decoded = entry["response"]["content"].get("size", 0)
        transferred = entry["response"].get("_transferSize", entry["response"].get("bodySize", 0))
        totals[mime]["requests"] += 1
        totals[mime]["decoded_bytes"] += max(decoded, 0)
        totals[mime]["transfer_bytes"] += max(transferred, 0)
        for key in ("ea034.css", "9d2aa.css", "agencia-desenvolvimento-de-sites.webp", "admin-ajax.php"):
            if key in url:
                resources.setdefault(key, []).append({
                    "decoded_bytes": entry["response"]["content"].get("size", -1),
                    "transfer_bytes": entry["response"].get("_transferSize", entry["response"].get("bodySize", -1)),
                    "time_ms": entry["time"],
                })
    dom = parse_mhtml()
    maps = source_maps(dom)
    dom.pop("class_names")
    report = {
        "inputs": {
            "aiko_style_bytes": archive_member_size("aiko.zip", "aiko/style.css"),
            "builder_content_css_bytes": archive_member_size("bold-page-builder.zip", "bold-page-builder/css/front_end/content_elements.crush.css"),
        },
        "har": resources,
        "mhtml_dom": dom,
        "source_maps": maps,
        "totals_by_mime": totals,
    }
    json.dump(report, sys.stdout, indent=2, sort_keys=True)
    print()


if __name__ == "__main__":
    main()
