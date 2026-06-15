#!/usr/bin/env bash
set -euo pipefail

STRONG=false
if [ "${1:-}" = "--strong" ]; then
  STRONG=true
fi

: "${APP_URL:?APP_URL is required}"

host_from_url() {
  local url="$1"
  url="${url#http://}"
  url="${url#https://}"
  url="${url%%/*}"
  url="${url%%:*}"
  echo "$url"
}

app_host="$(host_from_url "$APP_URL")"
if [ -n "$app_host" ] && ! getent hosts "$app_host" >/dev/null 2>&1; then
  echo "Smoke test skipped: APP_URL host does not resolve yet: $app_host" >&2
  echo "Create the DNS record for $app_host after the ALB is ready, then rerun smoke tests." >&2
  exit 0
fi

curl --fail --silent --show-error "$APP_URL/health/live" >/dev/null
curl --fail --silent --show-error "$APP_URL/health/ready" >/dev/null

if [ -n "${WEBHOOK_VERIFY_URL:-}" ]; then
  webhook_host="$(host_from_url "$WEBHOOK_VERIFY_URL")"
  if [ -n "$webhook_host" ] && getent hosts "$webhook_host" >/dev/null 2>&1; then
    curl --fail --silent --show-error "$WEBHOOK_VERIFY_URL" >/dev/null || {
      echo "Webhook verification route smoke test failed" >&2
      exit 1
    }
  else
    echo "Webhook verification smoke test skipped: host does not resolve yet: $webhook_host" >&2
  fi
fi

if [ "$STRONG" = true ]; then
  curl --fail --silent --show-error "$APP_URL/api/nx-whatsapp-onboarding/health" >/dev/null
fi

echo "Smoke tests passed."
