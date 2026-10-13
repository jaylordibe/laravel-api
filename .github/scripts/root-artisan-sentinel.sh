#!/usr/bin/env bash
#
# Proves no artisan boot in a container runs as root.
#
#     .github/scripts/root-artisan-sentinel.sh install <container>   # created, not started
#     .github/scripts/root-artisan-sentinel.sh check <container> <label>
#
# The entrypoint and the healthcheck run as root, and every artisan boot `require`s
# bootstrap/cache/packages.php — a file www-data can write. Booting artisan as root
# would let anything with www-data execution plant PHP that root runs. A uid-33 owner
# on a cached file cannot show this (the ownership fix chowns whatever root wrote),
# so `install` wraps packages.php to record the effective uid of every process that
# loads it, and `check` fails if any was root — or if none was recorded at all.
#
# The record file is created up front, owned by root and writable by everyone. If the
# first writer created it, a later writer running as a different user could be
# refused (fs.protected_regular in sticky /tmp, or root without DAC_OVERRIDE under
# dropped capabilities), crash before recording, and a root boot would go unseen.
set -euo pipefail

action="${1:?usage: $0 install|check <container> [label]}"
container="${2:?usage: $0 install|check <container> [label]}"

case "$action" in
    install)
        dir="$(mktemp -d)"
        trap 'rm -rf "${dir:?}"' EXIT
        cat > "${dir}/packages.php" <<'PHP'
<?php
file_put_contents('/tmp/boot-uids', posix_geteuid() . "\n", FILE_APPEND);
return require __DIR__ . '/packages.orig.php';
PHP
        docker cp "${container}:/var/www/html/bootstrap/cache/packages.php" "${dir}/packages.orig.php"
        docker cp "${dir}/packages.orig.php" "${container}:/var/www/html/bootstrap/cache/packages.orig.php"
        docker cp "${dir}/packages.php" "${container}:/var/www/html/bootstrap/cache/packages.php"
        # As a tar stream, so the file's owner is set in the archive: `docker cp` of a
        # plain file keeps the host user's uid.
        python3 -c '
import io, sys, tarfile
archive = tarfile.open(fileobj=sys.stdout.buffer, mode="w|")
entry = tarfile.TarInfo("boot-uids")
entry.uid, entry.gid, entry.mode, entry.size = 0, 0, 0o666, 0
archive.addfile(entry, io.BytesIO(b""))
archive.close()
' | docker cp - "${container}:/tmp"
        ;;
    check)
        label="${3:-$container}"
        uids="$(docker cp "${container}:/tmp/boot-uids" - 2>/dev/null | tar -xO || true)"
        echo "${label}: artisan booted as uid(s): $(tr '\n' ' ' <<<"$uids")"
        if [ -z "$uids" ]; then
            echo "::error::${label} never loaded the packages.php sentinel — this check proves nothing"
            exit 1
        fi
        if grep -qx '0' <<<"$uids"; then
            echo "::error::${label} booted artisan as root — www-data-writable bootstrap/cache PHP ran as root"
            exit 1
        fi
        ;;
    *)
        echo "usage: $0 install|check <container> [label]" >&2
        exit 2
        ;;
esac
