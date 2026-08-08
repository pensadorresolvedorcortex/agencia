#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source_dir="$repo_root/blog-privilege-ai"
dist_dir="$repo_root/dist"
archive="$dist_dir/blog-privilege-ai.zip"

for required_file in blog-privilege.php README.txt uninstall.php; do
    if [[ ! -f "$source_dir/$required_file" ]]; then
        printf 'Arquivo obrigatório ausente: %s\n' "$source_dir/$required_file" >&2
        exit 1
    fi
done

mkdir -p "$dist_dir"
rm -f "$archive"

(
    cd "$repo_root"
    zip -qr "$archive" blog-privilege-ai \
        -x '*/.DS_Store' '*/Thumbs.db' '*/.git*'
)

printf 'Plugin criado em: %s\n' "$archive"
