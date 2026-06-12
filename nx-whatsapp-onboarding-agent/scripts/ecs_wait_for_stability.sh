#!/usr/bin/env bash
set -euo pipefail

: "${ECS_CLUSTER:?ECS_CLUSTER is required}"
: "${ECS_WEB_SERVICE:?ECS_WEB_SERVICE is required}"
: "${ECS_WORKER_SERVICE:?ECS_WORKER_SERVICE is required}"

aws ecs wait services-stable --cluster "$ECS_CLUSTER" --services "$ECS_WEB_SERVICE"
aws ecs wait services-stable --cluster "$ECS_CLUSTER" --services "$ECS_WORKER_SERVICE"

echo "ECS services are stable."
