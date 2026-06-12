#!/usr/bin/env bash
set -euo pipefail

php artisan db:seed --class=NxTutors\\WhatsAppOnboarding\\Database\\Seeders\\DemoConversationSeeder
