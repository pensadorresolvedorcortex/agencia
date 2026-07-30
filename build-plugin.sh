#!/usr/bin/env bash

set -euo pipefail

readonly repo_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly plugin_dir="elfsight-age-verification-cc"
readonly output_file="${1:-${repo_dir}/dist/${plugin_dir}.zip}"

if [[ ! -d "${repo_dir}/${plugin_dir}" ]]; then
    printf 'Plugin source directory not found: %s\n' "${repo_dir}/${plugin_dir}" >&2
    exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
    printf 'The zip command is required to build the installable plugin.\n' >&2
    exit 1
fi

mkdir -p "$(dirname "${output_file}")"
rm -f "${output_file}"

(
    cd "${repo_dir}"
    zip -q -r "${output_file}" "${plugin_dir}"
)

printf 'Installable plugin created at %s\n' "${output_file}"
