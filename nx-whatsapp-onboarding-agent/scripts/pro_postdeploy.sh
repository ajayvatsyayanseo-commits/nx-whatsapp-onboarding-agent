#!/usr/bin/env sh
# Post-deploy checks for the Tutor "Pro Mode" web flow on the CloudPanel server.
#
# Run from the deployed site root (the directory that contains public/ and
# nx-whatsapp-onboarding-agent/). The CloudPanel deploy workflow invokes it as:
#   cd "$DEPLOY_PATH" && sh nx-whatsapp-onboarding-agent/scripts/pro_postdeploy.sh
#
# It does NOT touch the server .env. It:
#   1. Lints the deployed PHP (fails the deploy if syntax is broken).
#   2. Finds the active .env (same order as load_onboarding_env()).
#   3. Creates PRO_DIR / PRO_CV_DIR if configured, and reports writability.
#   4. Prints the PHP upload limits (the CV upload needs ~8M).
set -eu

echo "== PHP syntax check =="
php -l public/index.php
php -l public/pro.php

echo "== Locate active .env =="
ENVFILE=""
for c in public/.env .env nx-whatsapp-onboarding-agent/.env; do
  if [ -f "$c" ]; then ENVFILE="$c"; break; fi
done
echo "env file: ${ENVFILE:-none found}"

echo "== Ensure Pro data dirs exist and are writable =="
if [ -n "${ENVFILE:-}" ]; then
  for key in PRO_DIR PRO_CV_DIR; do
    line=$(grep -E "^[[:space:]]*${key}=" "$ENVFILE" | head -1 || true)
    [ -n "$line" ] || continue
    val=${line#*=}
    # strip surrounding whitespace and optional quotes
    val=$(printf '%s' "$val" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//")
    [ -n "$val" ] || continue
    mkdir -p "$val" 2>/dev/null || true
    chmod 700 "$val" 2>/dev/null || true
    if [ -w "$val" ]; then
      echo "ok   $key=$val (writable by $(id -un))"
    else
      echo "WARN $key=$val is NOT writable by $(id -un) — Pro uploads/tokens will fail; fix ownership."
    fi
  done
else
  echo "skip: no .env found; PRO_DIR/PRO_CV_DIR will default to the system temp dir."
fi

echo "== PHP upload limits (CV upload needs ~8M) =="
php -r 'printf("upload_max_filesize=%s post_max_size=%s\n", ini_get("upload_max_filesize"), ini_get("post_max_size"));'
echo "If either value is below 8M, raise it in CloudPanel (Site > PHP settings) so CV uploads are accepted."

echo "Pro post-deploy checks complete."
