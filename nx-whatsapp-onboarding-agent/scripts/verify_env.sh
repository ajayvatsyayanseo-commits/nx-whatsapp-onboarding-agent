#!/usr/bin/env bash
set -euo pipefail

required=(
  APP_ENV
  APP_KEY
  DB_CONNECTION
  DB_HOST
  DB_DATABASE
  DB_USERNAME
  REDIS_HOST
  QUEUE_CONNECTION
  META_WHATSAPP_VERIFY_TOKEN
  META_WHATSAPP_APP_SECRET
  META_WHATSAPP_ACCESS_TOKEN
  META_WHATSAPP_PHONE_NUMBER_ID
  TERMS_STUDENT_URL
  PRIVACY_STUDENT_URL
  TERMS_TUTOR_URL
  PRIVACY_TUTOR_URL
  STUDENT_DASHBOARD_URL
  TUTOR_DASHBOARD_URL
)

missing=0
for key in "${required[@]}"; do
  if [ -z "${!key:-}" ]; then
    echo "Missing required environment variable: $key" >&2
    missing=1
  fi
done

if [ "$missing" -ne 0 ]; then
  exit 1
fi

if [ "${APP_ENV:-}" = "production" ] && [ "${TERMS_ALLOW_LOCAL_PLACEHOLDER:-false}" = "true" ]; then
  echo "TERMS_ALLOW_LOCAL_PLACEHOLDER must be false in production." >&2
  exit 1
fi

echo "Environment shape looks valid."
