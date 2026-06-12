#!/usr/bin/env bash
set -euo pipefail

php artisan migrate --path=nx-whatsapp-onboarding-agent/database/migrations
