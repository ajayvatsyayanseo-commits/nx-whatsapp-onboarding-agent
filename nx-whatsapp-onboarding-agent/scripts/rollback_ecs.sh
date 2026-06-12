#!/usr/bin/env bash
set -euo pipefail

ENVIRONMENT="${1:?environment is required}"
SERVICE="${2:?service is required}"
TARGET="${3:-}"
REASON="${4:?reason is required}"

: "${ECS_CLUSTER:?ECS_CLUSTER is required}"
: "${ECS_WEB_SERVICE:?ECS_WEB_SERVICE is required}"
: "${ECS_WORKER_SERVICE:?ECS_WORKER_SERVICE is required}"
: "${ECS_WEB_TASK_FAMILY:?ECS_WEB_TASK_FAMILY is required}"
: "${ECS_WORKER_TASK_FAMILY:?ECS_WORKER_TASK_FAMILY is required}"

rollback_one() {
  local service_type="$1"
  local service_name family
  if [ "$service_type" = "web" ]; then
    service_name="$ECS_WEB_SERVICE"
    family="$ECS_WEB_TASK_FAMILY"
  else
    service_name="$ECS_WORKER_SERVICE"
    family="$ECS_WORKER_TASK_FAMILY"
  fi

  local task_definition="$TARGET"
  if [ -z "$task_definition" ]; then
    task_definition="$(aws ecs list-task-definitions --family-prefix "$family" --sort DESC --max-items 2 --query 'taskDefinitionArns[1]' --output text)"
  elif [[ "$task_definition" != arn:* && "$task_definition" =~ ^[0-9]+$ ]]; then
    task_definition="${family}:${task_definition}"
  fi

  if [ -z "$task_definition" ] || [ "$task_definition" = "None" ]; then
    echo "No rollback task definition found for $service_type" >&2
    exit 1
  fi

  aws ecs update-service --cluster "$ECS_CLUSTER" --service "$service_name" --task-definition "$task_definition" >/dev/null
  echo "Rolled back $service_type to $task_definition"
}

case "$SERVICE" in
  web) rollback_one web ;;
  worker) rollback_one worker ;;
  both) rollback_one web; rollback_one worker ;;
  *) echo "Unknown service: $SERVICE" >&2; exit 1 ;;
esac

echo "$(date -u +"%Y-%m-%dT%H:%M:%SZ") environment=$ENVIRONMENT service=$SERVICE reason=$REASON" > /tmp/nxtutors-rollback-audit.log
