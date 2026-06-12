#!/usr/bin/env bash
set -euo pipefail

ACTION="${1:-}"
IMAGE_URI="${2:-}"

required_env() {
  local name="$1"
  if [ -z "${!name:-}" ]; then
    echo "Missing required environment variable: $name" >&2
    exit 1
  fi
}

classify_migrations() {
  local migration_dir="nx-whatsapp-onboarding-agent/database/migrations"
  if [ ! -d "$migration_dir" ]; then
    echo "safe"
    return
  fi

  if grep -RIE "dropTable|dropColumn|renameColumn|DB::statement\\(['\\\"]\\s*drop|truncate\\s+table|delete\\s+from" "$migration_dir" >/dev/null; then
    echo "requires_approval"
    return
  fi

  if grep -RIE "Schema::dropIfExists\\(['\\\"]register|drop\\s+table\\s+register" "$migration_dir" >/dev/null; then
    echo "blocked"
    return
  fi

  echo "safe"
}

current_task_definition_json() {
  local family="$1"
  aws ecs describe-task-definition --task-definition "$family" --query taskDefinition --output json
}

register_task_definition() {
  local family="$1"
  local image="$2"

  current_task_definition_json "$family" \
    | jq --arg IMAGE "$image" '
      .containerDefinitions[0].image = $IMAGE
      | del(.taskDefinitionArn,.revision,.status,.requiresAttributes,.compatibilities,.registeredAt,.registeredBy)
    ' > "/tmp/${family}.json"

  aws ecs register-task-definition --cli-input-json "file:///tmp/${family}.json" --query 'taskDefinition.taskDefinitionArn' --output text
}

update_service() {
  local service_type="$1"
  local image="$2"

  required_env ECS_CLUSTER
  required_env ECS_WEB_SERVICE
  required_env ECS_WORKER_SERVICE
  required_env ECS_WEB_TASK_FAMILY
  required_env ECS_WORKER_TASK_FAMILY

  if [ "$service_type" = "web" ]; then
    local arn
    arn="$(register_task_definition "$ECS_WEB_TASK_FAMILY" "$image")"
    aws ecs update-service --cluster "$ECS_CLUSTER" --service "$ECS_WEB_SERVICE" --task-definition "$arn" >/dev/null
  elif [ "$service_type" = "worker" ]; then
    local arn
    arn="$(register_task_definition "$ECS_WORKER_TASK_FAMILY" "$image")"
    aws ecs update-service --cluster "$ECS_CLUSTER" --service "$ECS_WORKER_SERVICE" --task-definition "$arn" >/dev/null
  else
    echo "Unknown service type: $service_type" >&2
    exit 1
  fi
}

run_one_off_task() {
  local command="$1"
  required_env ECS_CLUSTER
  required_env ECS_WEB_TASK_FAMILY
  required_env ECS_SUBNET_IDS
  required_env ECS_SECURITY_GROUP_IDS

  local network
  network="awsvpcConfiguration={subnets=[$ECS_SUBNET_IDS],securityGroups=[$ECS_SECURITY_GROUP_IDS],assignPublicIp=DISABLED}"

  aws ecs run-task \
    --cluster "$ECS_CLUSTER" \
    --launch-type FARGATE \
    --task-definition "$ECS_WEB_TASK_FAMILY" \
    --network-configuration "$network" \
    --overrides "{\"containerOverrides\":[{\"name\":\"app\",\"command\":[\"${command}\"]}]}" \
    --query 'tasks[0].taskArn' \
    --output text
}

case "$ACTION" in
  classify)
    classify_migrations
    ;;
  checkpoint)
    echo "Creating pre-deploy checkpoint marker."
    date -u +"%Y-%m-%dT%H:%M:%SZ" > /tmp/nxtutors-predeploy-checkpoint.txt
    ;;
  register-task-definitions)
    required_env ECS_WEB_TASK_FAMILY
    required_env ECS_WORKER_TASK_FAMILY
    if [ -z "$IMAGE_URI" ]; then echo "Missing image URI" >&2; exit 1; fi
    register_task_definition "$ECS_WEB_TASK_FAMILY" "$IMAGE_URI" >/dev/null
    register_task_definition "$ECS_WORKER_TASK_FAMILY" "$IMAGE_URI" >/dev/null
    ;;
  update-service)
    SERVICE_TYPE="${2:-}"
    IMAGE="${3:-}"
    if [ -z "$SERVICE_TYPE" ] || [ -z "$IMAGE" ]; then echo "Usage: deploy_migrate.sh update-service web|worker IMAGE_URI" >&2; exit 1; fi
    update_service "$SERVICE_TYPE" "$IMAGE"
    ;;
  migrate)
    TASK_ARN="$(run_one_off_task migrate)"
    echo "Migration task started: $TASK_ARN"
    aws ecs wait tasks-stopped --cluster "$ECS_CLUSTER" --tasks "$TASK_ARN"
    EXIT_CODE="$(aws ecs describe-tasks --cluster "$ECS_CLUSTER" --tasks "$TASK_ARN" --query 'tasks[0].containers[0].exitCode' --output text)"
    if [ "$EXIT_CODE" != "0" ]; then
      echo "Migration task failed with exit code $EXIT_CODE" >&2
      exit 1
    fi
    ;;
  *)
    echo "Usage: deploy_migrate.sh classify|checkpoint|register-task-definitions IMAGE_URI|update-service web|worker IMAGE_URI|migrate" >&2
    exit 1
    ;;
esac
