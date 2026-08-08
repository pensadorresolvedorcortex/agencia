#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin="$repo_root/blog-privilege-ai/blog-privilege.php"

assert_contains() {
    local expected="$1"
    if ! grep -Fq "$expected" "$plugin"; then
        printf 'Contrato SEO ausente: %s\n' "$expected" >&2
        exit 1
    fi
}

assert_contains "Version: 4.4.1"
assert_contains "'_yoast_wpseo_focuskw'"
assert_contains "'_yoast_wpseo_title'"
assert_contains "'_yoast_wpseo_metadesc'"
assert_contains "'_yoast_wpseo_linkdex'"
assert_contains "'_yoast_wpseo_content_score'"
assert_contains "return array_slice(array_values(\$tags), 0, 8);"
assert_contains "count(\$words) >= 400"
assert_contains "Google Search Central sobre conteúdo útil"
assert_contains "esc_url(home_url('/'))"

if grep -Fq "'digitais','marketing'" "$plugin"; then
    printf 'A palavra marketing não pode ser removida do slug da frase-chave.\n' >&2
    exit 1
fi

if grep -Fq "Indexable_Builder" "$plugin"; then
    printf 'Uma dependência interna incompatível do Yoast foi encontrada.\n' >&2
    exit 1
fi

php -l "$plugin" >/dev/null
php -l "$repo_root/blog-privilege-ai/uninstall.php" >/dev/null

printf 'Contrato SEO e sintaxe PHP: OK\n'
