#!/usr/bin/env bash
set -euo pipefail

composer install
php artisan vendor:publish --tag=nx-whatsapp-onboarding-config --force || true
php artisan migrate
