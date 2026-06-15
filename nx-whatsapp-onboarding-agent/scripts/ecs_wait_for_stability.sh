#!/usr/bin/env bash
set -euo pipefail

: "${ECS_CLUSTER:?ECS_CLUSTER is required}"
: "${ECS_WEB_SERVICE:?ECS_WEB_SERVICE is required}"
: "${ECS_WORKER_SERVICE:?ECS_WORKER_SERVICE is required}"

print_service_status() {
  local service="$1"
  echo "ECS status for $service"
  aws ecs describe-services \
    --cluster "$ECS_CLUSTER" \
    --services "$service" \
    --query 'services[0].{desired:desiredCount,running:runningCount,pending:pendingCount,deployments:deployments[].{status:status,rollout:rolloutState,desired:desiredCount,running:runningCount,pending:pendingCount,failed:failedTasks},events:events[0:8].message}' \
    --output json
}

wait_for_service() {
  local service="$1"
  local max_attempts="${ECS_STABILITY_MAX_ATTEMPTS:-30}"
  local sleep_seconds="${ECS_STABILITY_POLL_SECONDS:-20}"

  for attempt in $(seq 1 "$max_attempts"); do
    print_service_status "$service"

    local stable
    stable="$(aws ecs describe-services \
      --cluster "$ECS_CLUSTER" \
      --services "$service" \
      --query 'services[0].runningCount == services[0].desiredCount && services[0].pendingCount == `0` && length(services[0].deployments[?status == `PRIMARY` && rolloutState == `COMPLETED` && runningCount == desiredCount && pendingCount == `0`]) == `1`' \
      --output text)"

    if [ "$stable" = "True" ]; then
      echo "$service is stable."
      return 0
    fi

    echo "$service is not stable yet. Attempt $attempt/$max_attempts. Waiting ${sleep_seconds}s..."
    sleep "$sleep_seconds"
  done

  echo "$service did not become stable in time." >&2
  print_service_status "$service" >&2
  return 1
}

wait_for_service "$ECS_WEB_SERVICE"
wait_for_service "$ECS_WORKER_SERVICE"

echo "ECS services are stable."
