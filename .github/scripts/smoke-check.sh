#!/usr/bin/env bash
#
# Post-deploy smoke check: passes only when GET <base-url>/api/health/ready
# answers HTTP 200 with the readiness body HealthController returns.
#
#   .github/scripts/smoke-check.sh <base-url>
#
# The single definition both deploy workflows run, so production and staging
# cannot drift. tests/Feature/SmokeCheckScriptFeatureTest.php exercises it
# against a stub server.
#
# Outcomes:
#   200 + {"success":true,...,"status":"ready"}   pass
#   connection failure, timeout, 5xx               retry (the build may still be starting)
#   200 with any other body                        retry, then fail
#   3xx, 4xx, anything else                        fail at once
#
# Redirects are never followed: a readiness probe that lands on a login page or
# another host has not proved this deployment is up. Every attempt is bounded
# by a connection timeout and a total timeout, so a hung server cannot stall
# the job until the runner's own timeout.
#
# Tunable for tests; the defaults are what deployments use:
#   SMOKE_ATTEMPTS (30), SMOKE_INTERVAL_SECONDS (2),
#   SMOKE_CONNECT_TIMEOUT_SECONDS (5), SMOKE_MAX_TIME_SECONDS (10)
set -uo pipefail

base_url="${1:-}"
attempts="${SMOKE_ATTEMPTS:-30}"
interval="${SMOKE_INTERVAL_SECONDS:-2}"
connect_timeout="${SMOKE_CONNECT_TIMEOUT_SECONDS:-5}"
max_time="${SMOKE_MAX_TIME_SECONDS:-10}"

if [ -z "$base_url" ]; then
  echo '::error::no deployment URL was given, so the deploy cannot be verified.'
  exit 1
fi

url="${base_url%/}/api/health/ready"
body_file="$(mktemp)"
trap 'rm -f "$body_file"' EXIT

# The body HealthController::ready() returns when every dependency answers.
# Matched as compact JSON, which is how Laravel encodes it.
is_ready_body() {
  grep -q '"success":true' "$body_file" && grep -q '"status":"ready"' "$body_file"
}

last=""
for attempt in $(seq 1 "$attempts"); do
  : > "$body_file"
  # `|| code=000` stays a separate assignment: curl prints its -w output before
  # exiting, so `$(curl ... || echo 000)` would concatenate two codes.
  code=$(curl --silent --show-error --proto '=http,https' \
    --connect-timeout "$connect_timeout" --max-time "$max_time" \
    --max-redirs 0 --output "$body_file" --write-out '%{http_code}' \
    "$url" 2>/dev/null) || code=000

  case "$code" in
    200)
      if is_ready_body; then
        echo "Ready after ${attempt} attempt(s): ${url}"
        exit 0
      fi
      status="HTTP 200 with an unexpected body"
      ;;
    000|5??)
      status="HTTP ${code}"
      ;;
    *)
      echo "::error::smoke check failed: ${url} answered HTTP ${code}; expected 200 with a ready status."
      exit 1
      ;;
  esac

  if [ "$status" != "$last" ]; then
    echo "attempt ${attempt}: ${status}"
    last="$status"
  fi

  if [ "$attempt" -lt "$attempts" ]; then
    sleep "$interval"
  fi
done

echo "::error::smoke check failed after ${attempts} attempt(s): last result ${last} from ${url}."
exit 1
