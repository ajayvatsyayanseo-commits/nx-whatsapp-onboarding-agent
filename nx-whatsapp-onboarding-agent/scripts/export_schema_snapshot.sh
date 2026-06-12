#!/usr/bin/env bash
set -euo pipefail

php artisan schema:dump --database=pgsql --prune
