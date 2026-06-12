#!/usr/bin/env bash
set -euo pipefail

STRONG=false
if [ "${1:-}" = "--strong" ]; then
  STRONG=true
fi

: "${APP_URL:?APP_URL is required}"

curl --fail --silent --show-error "$APP_URL/health/live" >/dev/null
curl --fail --silent --show-error "$APP_URL/health/ready" >/dev/null

if [ -n "${WEBHOOK_VERIFY_URL:-}" ]; then
  curl --fail --silent --show-error "$WEBHOOK_VERIFY_URL" >/dev/null || {
    echo "Webhook verification route smoke test failed" >&2
    exit 1
  }
fi

if [ "$STRONG" = true ]; then
  curl --fail --silent --show-error "$APP_URL/api/nx-whatsapp-onboarding/health" >/dev/null
fi

echo "Smoke tests passed."
