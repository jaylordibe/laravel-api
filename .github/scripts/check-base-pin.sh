#!/usr/bin/env bash
#
# Fails unless every reference to the base image is pinned by digest, and all of them
# to the SAME digest.
#
#     .github/scripts/check-base-pin.sh [repository root]
#
# `jaylordibe/laravel-php:8.5` moves on every weekly rebuild, so a tag reference makes
# the same commit build a different image tomorrow — including one whose entrypoint
# contract this template no longer matches. Dependabot proposes each digest bump as a
# pull request, and CI scans and boots it before it can merge. One script, called by
# test.yml (before the image build) and by the staging deploy, so the rule cannot
# drift between them.
set -euo pipefail

cd "${1:-.}"

FILES=(Dockerfile docker-compose.yml)

for file in "${FILES[@]}"; do
    refs=$(grep -oE 'jaylordibe/laravel-php:[^[:space:]"]+' "$file" || true)
    if [ -z "$refs" ]; then
        echo "::error file=${file}::no jaylordibe/laravel-php reference found — the pin check would pass vacuously"
        exit 1
    fi
    while IFS= read -r ref; do
        if ! grep -qE '^jaylordibe/laravel-php:[^@]+@sha256:[0-9a-f]{64}$' <<<"$ref"; then
            echo "::error file=${file}::${ref} is not pinned by digest. Use jaylordibe/laravel-php:<tag>@sha256:<digest> (docker buildx imagetools inspect jaylordibe/laravel-php:<tag>)."
            exit 1
        fi
        echo "pinned: ${file} -> ${ref}"
    done <<<"$refs"
done

# One base for development, CI and production. Compared on the digest alone, so the
# same image under two spellings of the tag is not reported as a mismatch.
digests=$(grep -ohE 'jaylordibe/laravel-php:[^[:space:]"]+@sha256:[0-9a-f]{64}' "${FILES[@]}" \
    | sed -E 's/.*@(sha256:[0-9a-f]{64})$/\1/' | sort -u)
if [ "$(wc -l <<<"$digests")" -ne 1 ]; then
    echo "::error::Dockerfile and docker-compose.yml pin different base images:"
    printf '%s\n' "$digests"
    exit 1
fi
echo "one base image: ${digests}"
