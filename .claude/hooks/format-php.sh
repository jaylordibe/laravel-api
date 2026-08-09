#!/usr/bin/env bash
#
# PostToolUse formatter. Runs `php artisan app:format` after any .php edit so a
# change is never handed back unformatted — CLAUDE.md treats formatting as the
# mandatory closing step, and doing it here means nobody has to remember.
#
# The container name is derived from SERVICE_NAME in .env rather than hardcoded.
# A forked project renames the service, and a hardcoded `laravel-api` would then
# silently stop formatting anything: `docker exec` fails, the `|| true` swallows
# it, and every edit from that point on is unformatted with no signal at all.
# Only SERVICE_NAME is read — never a credential.
#
# Never fails the tool call. A stopped container is a normal state (the guard
# and the gates already tell the developer when the stack must be up), and a
# formatter that blocks edits when Docker is down is worse than one that skips.

set -uo pipefail

if ! command -v jq >/dev/null 2>&1; then
  exit 0
fi

payload=$(cat)
file_path=$(printf '%s' "$payload" | jq -r '.tool_input.file_path // ""')

case "$file_path" in
  *.php) ;;
  *) exit 0 ;;
esac

project_directory=${CLAUDE_PROJECT_DIR:-.}
service_name=laravel

if [ -f "$project_directory/.env" ]; then
  configured_service_name=$(
    sed -n 's/^[[:space:]]*SERVICE_NAME[[:space:]]*=[[:space:]]*//p' "$project_directory/.env" |
      tail -n 1 |
      tr -d '"'"'"'[:space:]'
  )
  if [ -n "$configured_service_name" ]; then
    service_name=$configured_service_name
  fi
fi

docker exec "${service_name}-api" php artisan app:format >/dev/null 2>&1 || true

exit 0
