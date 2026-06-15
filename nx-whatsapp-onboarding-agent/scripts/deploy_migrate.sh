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

build_container_environment_json() {
  local names=(
    APP_KEY APP_ENV APP_DEBUG APP_URL LOG_CHANNEL LOG_LEVEL
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    REDIS_HOST REDIS_PORT REDIS_PASSWORD REDIS_CONNECTION
    CACHE_STORE CACHE_DRIVER SESSION_DRIVER QUEUE_CONNECTION
    WHATSAPP_ONBOARDING_DB_CONNECTION WHATSAPP_ONBOARDING_REDIS_CONNECTION WHATSAPP_ONBOARDING_QUEUE
    WHATSAPP_ONBOARDING_ENABLED WHATSAPP_SIGNUP_ENABLED WHATSAPP_STUDENT_SIGNUP_ENABLED WHATSAPP_TUTOR_SIGNUP_ENABLED
    WHATSAPP_CREATE_REAL_PROFILE WHATSAPP_ONBOARDING_PAUSED WHATSAPP_OUTBOUND_PAUSED
    META_WHATSAPP_VERIFY_TOKEN META_WHATSAPP_APP_SECRET META_WHATSAPP_ACCESS_TOKEN META_WHATSAPP_PHONE_NUMBER_ID
    META_WHATSAPP_API_VERSION META_GRAPH_BASE_URL META_WHATSAPP_INTERACTIVE_ENABLED META_REQUIRE_TEMPLATE_OUTSIDE_SESSION
    META_WHATSAPP_TEMPLATE_LANGUAGE META_TEMPLATE_SIGNUP_RESUME META_TEMPLATE_OTP_MESSAGE META_TEMPLATE_PROFILE_CREATED META_TEMPLATE_HUMAN_HANDOFF
    TERMS_STUDENT_URL PRIVACY_STUDENT_URL TERMS_TUTOR_URL PRIVACY_TUTOR_URL TERMS_VERSION TERMS_ALLOW_LOCAL_PLACEHOLDER
    STUDENT_DASHBOARD_URL TUTOR_DASHBOARD_URL WHATSAPP_ONBOARDING_LOGIN_URL STUDENT_CHANGE_PASSWORD_URL TUTOR_CHANGE_PASSWORD_URL
    DASHBOARD_MAGIC_LOGIN_ENABLED DASHBOARD_SIGNING_KEY WHATSAPP_ONBOARDING_SECURE_LINK_TTL_MINUTES
    NXTUTORS_LEGACY_WEBSITE_MODE NXTUTORS_LOGIN_IDENTIFIER NXTUTORS_STUDENT_JOIN_AS NXTUTORS_TUTOR_JOIN_AS
    NXTUTORS_STUDENT_USER_TYPE NXTUTORS_TUTOR_USER_TYPE NXTUTORS_STATUS_ACTIVE_VALUE NXTUTORS_OTP_VERIFIED_VALUE
    NXTUTORS_USER_ID_MODE NXTUTORS_MYSQL_USER_ID_LOCK_NAME NXTUTORS_USER_ID_MAX_RETRIES
    NXTUTORS_PHONE_STORAGE_FORMAT NXTUTORS_STORE_C_PASSWORD NXTUTORS_FORCE_PASSWORD_RESET_IF_COLUMN_EXISTS
    NXTUTORS_TUTOR_REQUIRE_DOCUMENTS_BEFORE_CREATE
    WHATSAPP_STUDENT_STATUS WHATSAPP_TUTOR_STATUS WHATSAPP_OTP_STATUS_VERIFIED WHATSAPP_TUTOR_DOCUMENTS_REQUIRE_REVIEW
    WHATSAPP_USER_ID_PREFIX_STUDENT WHATSAPP_USER_ID_PREFIX_TUTOR WHATSAPP_USER_ID_RANDOM_LENGTH
    WHATSAPP_ONBOARDING_APP_VERSION WHATSAPP_ONBOARDING_FLOW_VERSION WHATSAPP_ONBOARDING_STATE_MACHINE_VERSION
    WHATSAPP_ONBOARDING_MESSAGE_TEMPLATE_VERSION WHATSAPP_ONBOARDING_ROUTE_PREFIX
    HASH_DRIVER WHATSAPP_ONBOARDING_TEMP_PASSWORD_LENGTH WHATSAPP_ONBOARDING_FORCE_RESET_COLUMN
    WHATSAPP_ONBOARDING_OTP_LENGTH WHATSAPP_ONBOARDING_OTP_TTL_MINUTES WHATSAPP_ONBOARDING_OTP_MAX_ATTEMPTS
    WHATSAPP_ONBOARDING_OTP_RESEND_COOLDOWN_SECONDS WHATSAPP_ONBOARDING_CONVERSATION_TTL_MINUTES
    WHATSAPP_ONBOARDING_REDIS_TTL_SECONDS WHATSAPP_ONBOARDING_MAX_INVALID_ATTEMPTS
    WHATSAPP_ONBOARDING_MAX_INVALID_ATTEMPTS_PER_FIELD WHATSAPP_ONBOARDING_MIN_AGE_YEARS
    WHATSAPP_ONBOARDING_MAX_WEBHOOK_BYTES WHATSAPP_ONBOARDING_MAX_MESSAGE_LENGTH
    WHATSAPP_ONBOARDING_INBOUND_PER_PHONE_MINUTE WHATSAPP_ONBOARDING_INBOUND_PER_IP_MINUTE
    WHATSAPP_ONBOARDING_BLOCK_INJECTION_PATTERNS WHATSAPP_ONBOARDING_INDIA_PINCODE
    WHATSAPP_ONBOARDING_MASK_PII_LOGS WHATSAPP_ONBOARDING_ENCRYPT_SENSITIVE_DRAFTS
    WHATSAPP_ONBOARDING_MAX_MESSAGES_PER_PHONE_HOUR WHATSAPP_ONBOARDING_MAX_MESSAGES_GLOBAL_MINUTE
    WHATSAPP_ONBOARDING_MAX_MEDIA_DOWNLOAD_MB META_CIRCUIT_BREAKER_FAILURE_THRESHOLD META_CIRCUIT_BREAKER_COOLDOWN_SECONDS
    WHATSAPP_ONBOARDING_LLM_ENABLED WHATSAPP_ONBOARDING_LLM_DAILY_TOKEN_BUDGET
    LLM_PROVIDER_API_KEY OPENAI_API_KEY LLM_MAX_CALLS_PER_CONVERSATION LLM_MAX_TOKENS_PER_CALL LLM_TIMEOUT_MS
    LLM_DAILY_BUDGET_INR LLM_DAILY_BUDGET_USD LLM_LATENCY_BREAKER_MS LLM_ERROR_RATE_BREAKER_PERCENT
    MEDIA_STORAGE_DRIVER WHATSAPP_ONBOARDING_LOCAL_MEDIA_PATH WHATSAPP_ONBOARDING_MEDIA_DB_VALUE
    AWS_S3_MEDIA_BUCKET WHATSAPP_ONBOARDING_S3_MEDIA_BUCKET WHATSAPP_ONBOARDING_ANALYTICS_BUCKET
    AWS_S3_MEDIA_PREFIX WHATSAPP_ONBOARDING_MEDIA_MAX_KB WHATSAPP_ONBOARDING_DEGREE_ALLOWS_PDF
    AWS_SECRETS_MANAGER_PREFIX WHATSAPP_ONBOARDING_EVENTBRIDGE_BUS WHATSAPP_ONBOARDING_LOG_CHANNEL
    WHATSAPP_ONBOARDING_AUDIT_ENABLED WHATSAPP_ONBOARDING_METRICS_NAMESPACE WHATSAPP_ONBOARDING_TRACE_SAMPLE_RATE
    WHATSAPP_ONBOARDING_INCOMPLETE_DRAFT_RETENTION_DAYS WHATSAPP_ONBOARDING_RAW_WEBHOOK_RETENTION_DAYS
    WHATSAPP_ONBOARDING_STORE_RAW_WEBHOOK_PAYLOAD WHATSAPP_ONBOARDING_S3_RAW_PAYLOAD_EXPORT_ENABLED
    WHATSAPP_ONBOARDING_ANALYTICS_DISK WHATSAPP_ONBOARDING_ANALYTICS_PREFIX
    WHATSAPP_ONBOARDING_DRIFT_MIN_COMPLETION_RATE WHATSAPP_ONBOARDING_DRIFT_MAX_HANDOFF_RATE
    WHATSAPP_ONBOARDING_DRIFT_MAX_META_FAILURES SYNTHETIC_WEBHOOK_SECRET
  )

  local json
  json='[]'

  local name value
  for name in "${names[@]}"; do
    value="${!name:-}"
    if [ -n "$value" ]; then
      json="$(jq --arg name "$name" --arg value "$value" '. + [{name: $name, value: $value}]' <<< "$json")"
    fi
  done

  echo "$json"
}

register_task_definition() {
  local family="$1"
  local image="$2"
  local mode="${3:-web}"
  local command_json
  if [ "$mode" = "worker" ]; then
    command_json='["worker"]'
  else
    command_json='["web"]'
  fi
  local environment_json
  environment_json="$(build_container_environment_json)"

  current_task_definition_json "$family" \
    | jq --arg IMAGE "$image" --argjson COMMAND "$command_json" --argjson ENVIRONMENT "$environment_json" '
      .containerDefinitions[0].image = $IMAGE
      | .containerDefinitions[0].command = $COMMAND
      | .containerDefinitions[0].environment = (
          ((.containerDefinitions[0].environment // [])
            | map(select(.name as $name | ($ENVIRONMENT | map(.name) | index($name) | not))))
          + $ENVIRONMENT
        )
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
    arn="$(register_task_definition "$ECS_WEB_TASK_FAMILY" "$image" web)"
    aws ecs update-service --cluster "$ECS_CLUSTER" --service "$ECS_WEB_SERVICE" --task-definition "$arn" >/dev/null
  elif [ "$service_type" = "worker" ]; then
    local arn
    arn="$(register_task_definition "$ECS_WORKER_TASK_FAMILY" "$image" worker)"
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
  network="awsvpcConfiguration={subnets=[$ECS_SUBNET_IDS],securityGroups=[$ECS_SECURITY_GROUP_IDS],assignPublicIp=${ECS_ASSIGN_PUBLIC_IP:-ENABLED}}"

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
    register_task_definition "$ECS_WEB_TASK_FAMILY" "$IMAGE_URI" web >/dev/null
    register_task_definition "$ECS_WORKER_TASK_FAMILY" "$IMAGE_URI" worker >/dev/null
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
