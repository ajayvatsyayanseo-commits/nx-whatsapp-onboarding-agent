# NXtutors WhatsApp Onboarding Agent Deployment

This guide covers production-grade CI/CD and AWS deployment for the Laravel module in `nx-whatsapp-onboarding-agent/`.

## Target AWS Architecture

- ALB public HTTPS endpoint for Meta WhatsApp webhooks.
- ECS Fargate web service for Laravel HTTP traffic.
- ECS Fargate worker service for queues.
- ECR for immutable Docker images.
- Aurora PostgreSQL with RDS Proxy.
- ElastiCache Redis.
- SQS queues and DLQs.
- S3 buckets for WhatsApp media and sanitized analytics exports.
- AWS Secrets Manager for runtime secrets.
- CloudWatch logs, metrics, dashboards, and alarms.
- AWS Glue/Athena for onboarding analytics.
- Terraform for infrastructure.

## GitHub Actions Files

```text
.github/workflows/ci.yml
.github/workflows/deploy-dev.yml
.github/workflows/deploy-staging.yml
.github/workflows/deploy-prod.yml
.github/workflows/terraform-plan.yml
.github/workflows/rollback.yml
.github/actions/setup-php-composer/action.yml
```

## AWS Prerequisites

Create or identify:

- AWS accounts or separate AWS environments for `dev`, `staging`, and `prod`.
- VPC with at least two public and two private subnets.
- Route 53 hosted zone for webhook domain.
- ACM certificate for the webhook domain.
- Terraform state S3 bucket and DynamoDB lock table per environment.
- GitHub OIDC IAM roles for plan and deploy.
- AWS Secrets Manager entries for runtime secrets.

Production should use GitHub Environment protection rules so `prod` deploys require manual approval.

## GitHub OIDC Setup

Use OIDC. Do not create long-lived AWS access keys for GitHub Actions.

Required workflow permissions:

```yaml
permissions:
  id-token: write
  contents: read
```

Terraform module:

```text
infra/terraform/modules/iam-github-oidc
```

Create separate roles where possible:

- `AWS_TERRAFORM_PLAN_ROLE_ARN`
- `AWS_DEV_DEPLOY_ROLE_ARN`
- `AWS_STAGING_DEPLOY_ROLE_ARN`
- `AWS_PROD_DEPLOY_ROLE_ARN`

The plan role should be read mostly. The deploy role needs ECR push, ECS update, ECS run-task, IAM pass-role for ECS task roles, S3 state access, and Terraform-managed resource permissions.

## GitHub Environments

Create these GitHub Environments:

```text
dev
staging
prod
```

Recommended protection:

- `dev`: no manual approval.
- `staging`: optional reviewer approval.
- `prod`: required reviewers and deployment branch/tag restrictions.

## Required GitHub Variables

Set these as GitHub Environment variables for each environment:

```text
AWS_REGION
AWS_TERRAFORM_PLAN_ROLE_ARN
AWS_DEV_DEPLOY_ROLE_ARN
AWS_STAGING_DEPLOY_ROLE_ARN
AWS_PROD_DEPLOY_ROLE_ARN
ECR_REPOSITORY
ECS_CLUSTER
ECS_WEB_SERVICE
ECS_WORKER_SERVICE
ECS_WEB_TASK_FAMILY
ECS_WORKER_TASK_FAMILY
ECS_SUBNET_IDS
ECS_SECURITY_GROUP_IDS
APP_URL
WEBHOOK_VERIFY_URL
SYNTHETIC_WEBHOOK_URL
```

`ECS_SUBNET_IDS` and `ECS_SECURITY_GROUP_IDS` should be comma-separated values for one-off migration tasks.

## Runtime Secrets

Store runtime secrets in AWS Secrets Manager, not GitHub secrets:

```text
APP_KEY
ONBOARDING_AGENT_INTERNAL_SECRET
DB_HOST
DB_NAME
DB_USERNAME
DB_PASSWORD
META_WHATSAPP_ACCESS_TOKEN
META_WHATSAPP_APP_SECRET
META_WHATSAPP_VERIFY_TOKEN
META_WHATSAPP_PHONE_NUMBER_ID
TERMS_STUDENT_URL
TERMS_TUTOR_URL
STUDENT_DASHBOARD_URL
TUTOR_DASHBOARD_URL
DASHBOARD_SIGNING_KEY
S3_MEDIA_BUCKET
S3_ANALYTICS_BUCKET
```

`ONBOARDING_AGENT_INTERNAL_SECRET` is mandatory in production — see [Internal Handoff From Lead Intake](#internal-handoff-from-lead-intake).

The ECS task definition reads these using the `secrets_arns` map in Terraform.

## Terraform Backend Setup

Each environment has a backend template:

```text
infra/terraform/envs/dev/backend.tf
infra/terraform/envs/staging/backend.tf
infra/terraform/envs/prod/backend.tf
```

Before first apply:

1. Create the S3 state bucket.
2. Create the DynamoDB lock table.
3. Uncomment and update the backend block.
4. Run `terraform init`.

Never commit real `terraform.tfvars`; commit only `terraform.tfvars.example`.

## Terraform Commands

Dev example:

```bash
cd nx-whatsapp-onboarding-agent/infra/terraform/envs/dev
cp terraform.tfvars.example terraform.tfvars
terraform init
terraform plan
terraform apply
```

Repeat with `staging` or `prod` environment folders.

## CI Workflow

`ci.yml` runs on pull requests and pushes:

1. Checkout.
2. Setup PHP and Composer.
3. Composer validate.
4. Composer install with cache.
5. PHP lint.
6. PHPStan or Psalm if installed.
7. Laravel Pint or PHPCS if installed.
8. PHPUnit/Pest tests.
9. Migration dry-run if a Laravel `artisan` entrypoint exists.
10. Composer audit.
11. Secret scanning.
12. Docker build test.
13. Upload test reports.
14. Enforce coverage if a coverage report and threshold are configured.

The workflow creates only test `.env` files. It never prints production `.env` values.

## Dev Deployment

Trigger:

- Push to `develop`.
- Manual `workflow_dispatch`.

Flow:

1. Assume AWS dev deploy role through OIDC.
2. Build Docker image.
3. Push ECR tags: git SHA, branch, `dev`.
4. Optionally run Terraform apply.
5. Register ECS task definitions.
6. Update web and worker ECS services.
7. Run migration one-off ECS task.
8. Wait for service stability.
9. Smoke test `/health/live`, `/health/ready`, and webhook verify URL when configured.

## Staging Deployment

Trigger:

- Push to `main`.
- Manual `workflow_dispatch`.

Same as dev, with the `staging` GitHub Environment and stronger smoke tests.

## Production Deployment

Trigger:

- Manual `workflow_dispatch`.
- Release publish.

Production deploy uses the `prod` GitHub Environment. Configure required reviewers in GitHub so production has manual approval before the job receives environment access.

Flow:

1. Assume AWS prod deploy role through OIDC.
2. Reuse a staging-tested immutable image tag.
3. Promote `prod` ECR tag.
4. Run Terraform plan.
5. Apply only when `run_terraform=true`.
6. Classify migrations:
   - `safe`
   - `requires_approval`
   - `blocked`
7. Create pre-deploy checkpoint.
8. Deploy ECS web and worker services.
9. Run migrations with `--force` through one-off ECS task.
10. Wait for service stability.
11. Run smoke tests.
12. Run synthetic internal webhook test if configured.
13. Publish deployment summary with image, SHA, migration status, and rollback instructions.

## Migration Safety

Script:

```text
scripts/deploy_migrate.sh
```

Classification:

- `safe`: normal additive migrations.
- `requires_approval`: detected drop/rename/truncate/delete style migrations.
- `blocked`: detected destructive changes against the legacy `register` table.

Production refuses `requires_approval` unless the workflow input explicitly allows it. `blocked` fails.

## Rollback

Workflow:

```text
.github/workflows/rollback.yml
```

Inputs:

```text
environment
service
image_tag
task_definition_revision
reason
```

Rollback updates ECS to the previous task definition or a specified revision/tag, runs smoke tests, and writes a rollback summary.

Manual CLI helper:

```bash
bash nx-whatsapp-onboarding-agent/scripts/rollback_ecs.sh prod web "" "reason"
```

## Smoke Tests

Script:

```text
scripts/smoke_test.sh
```

Checks:

- `/health/live`
- `/health/ready`
- webhook verification URL when configured
- `/api/nx-whatsapp-onboarding/health` in strong mode

## Internal Handoff From Lead Intake

The NXtutors WhatsApp system runs two agents against the **same** Meta WhatsApp
phone number:

1. **Lead Intake Agent** — owns the public Meta webhook for the shared number.
2. **WhatsApp Onboarding Agent** — this service.

Only one webhook URL can be registered with Meta for a number, so the onboarding
agent **must not** be the public Meta webhook receiver. Pointing both agents at
Meta causes a webhook ownership conflict and both stop working.

### Flow

```text
Meta WhatsApp Cloud API
  -> Lead Intake Agent public webhook (verifies X-Hub-Signature-256)
  -> Lead Intake detects a signup/onboarding message
  -> Lead Intake POSTs an internal handoff to Onboarding with
     header X-NXTUTORS-INTERNAL-SECRET (not a Meta signature)
  -> Onboarding validates the internal secret, detects the role,
     and returns reply_text (it does NOT send WhatsApp itself)
  -> Lead Intake sends exactly ONE WhatsApp reply to the user
```

Onboarding never sends a WhatsApp message for a handoff request; it returns
`reply_text` and lead-intake sends the single reply. This prevents both
containers replying to the same user at the same time.

### Handoff request contract

`POST /whatsapp/onboarding/webhook`

A request is treated as a lead-intake handoff when **either** the
`X-NXTUTORS-INTERNAL-SECRET` header is present **or** the JSON body has
`source = "lead_intake_agent"`. Field aliases are normalized:

- phone: `wa_phone` | `phone` | `from`
- text: `message_text` | `text` | `body`
- message id: `wa_message_id` | `message_id` | `id`

```json
{
  "source": "lead_intake_agent",
  "wa_message_id": "wamid.HBg...",
  "wa_phone": "919999999999",
  "message_text": "I want to register as tutor",
  "timestamp": "1717000000",
  "message_type": "text",
  "raw_payload": {}
}
```

The onboarding agent keeps a per-phone conversation state (file-backed in the
standalone `public/index.php` runtime), so each forwarded message advances the
signup flow (role → fields → review → terms → done) instead of restarting.

Accepted response:

```json
{
  "status": "accepted",
  "mode": "lead_intake_handoff",
  "wa_message_id": "wamid.HBg...",
  "wa_phone": "919999999999",
  "detected_role": "tutor",
  "handled": true,
  "reply_text": "Great, let's create your tutor profile. What is your full name?"
}
```

Forwarded response (out of context):

When a message arrives and the phone is **not** in an onboarding flow and the
text is **not** a signup intent (e.g. a generic question, "hi", a message after
the signup already completed, or after the user cancelled), onboarding does not
own it and returns `handled:false` / `forward_to_lead_intake:true` with a null
`reply_text`. Lead-intake must then answer the message with its own logic.

```json
{
  "status": "forwarded",
  "mode": "lead_intake_handoff",
  "wa_message_id": "wamid.HBg...",
  "wa_phone": "919999999999",
  "detected_role": "unknown",
  "handled": false,
  "forward_to_lead_intake": true,
  "reply_text": null
}
```

Response codes:

- `200/202` accepted — body carries `reply_text` for lead-intake to send, and
  `handled:true`.
- `200` forwarded — onboarding does not own this message; body has
  `handled:false`, `forward_to_lead_intake:true`, `reply_text:null`. Lead-intake
  should handle the message with its own flow (do not send the onboarding reply).
- `200` duplicate — repeated `wa_message_id`; body is
  `{"status":"duplicate","mode":"lead_intake_handoff","reply_text":null}`. The
  flow is not restarted and no reply is sent.
- `401` unauthorized — wrong or missing internal secret on a handoff:
  `{"status":"unauthorized","reason":"invalid_internal_secret"}`.
- `503` misconfigured — `ONBOARDING_AGENT_INTERNAL_SECRET` is not set on the
  server in production:
  `{"status":"error","reason":"server_internal_secret_not_configured"}`. The
  service never silently accepts handoffs without a secret.

### Required environment variables

```text
ONBOARDING_AGENT_INTERNAL_SECRET   # REQUIRED. Shared secret lead-intake must send.
APP_ENV=production                 # Enables strict 503-on-missing-secret behaviour.
ONBOARDING_HANDOFF_ENABLED=true    # Optional kill switch for the handoff route.

# For genuine Meta webhook verification (defence in depth if pointed at directly):
META_WHATSAPP_APP_SECRET           # or META_APP_SECRET

# Only if onboarding sends direct WhatsApp messages in non-handoff paths:
META_WHATSAPP_ACCESS_TOKEN         # or META_ACCESS_TOKEN
META_WHATSAPP_PHONE_NUMBER_ID      # or META_PHONE_NUMBER_ID

# Optional: idempotency, conversation state, and captured leads for the
# standalone public/index.php runtime. All default to subfolders under a temp
# dir if unset. For more than one replica/task, point these at a SHARED volume
# (e.g. EFS) so a user's messages reach the same conversation state regardless of
# which task handles them — or run a single web task. Otherwise the signup flow
# can lose state across replicas. The Laravel package form uses Redis/DB instead.
ONBOARDING_IDEMPOTENCY_DIR=/var/run/nxtutors-onboarding/idemp
ONBOARDING_SESSION_DIR=/var/run/nxtutors-onboarding/sessions
ONBOARDING_LEADS_DIR=/var/run/nxtutors-onboarding/leads
```

### Real `register` account creation (standalone runtime)

When `WHATSAPP_CREATE_REAL_PROFILE=true` **and** a database is configured,
`public/index.php` creates the real website `register` row directly via PDO at
the end of signup (after `I AGREE`) and returns login credentials (login page,
masked email, one-time temporary password, dashboard, checklist). The temporary
password is stored only as a bcrypt hash (`password_hash`, Laravel-compatible),
`c_password` is left empty, and `force_password_reset` is set when the column
exists. The insert is schema-introspected (`SHOW COLUMNS` / `information_schema`)
so it only writes columns that actually exist in the legacy table, and it guards
against duplicate `email`/`phone` before inserting.

Required environment for direct creation:

```text
WHATSAPP_CREATE_REAL_PROFILE=true
DB_CONNECTION=mysql            # the live website DB (mysql|pgsql|sqlite)
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...                # or DB_NAME
DB_USERNAME=...                # or DB_USER
DB_PASSWORD=...
WHATSAPP_ONBOARDING_REGISTER_TABLE=register   # optional, defaults to "register"
WHATSAPP_STUDENT_STATUS=t      # value written to register.status for students
WHATSAPP_TUTOR_STATUS=t        # value written to register.status for tutors
WHATSAPP_OTP_STATUS_VERIFIED=t # value written to register.otp_status
WHATSAPP_USER_TYPE_STUDENT=student     # optional override (default: student)
WHATSAPP_USER_TYPE_TUTOR=Individual    # optional override (default: Individual)
WHATSAPP_USER_ID_PREFIX_STUDENT=NXS
WHATSAPP_USER_ID_PREFIX_TUTOR=NXT
WHATSAPP_ONBOARDING_TEMP_PASSWORD_LENGTH=12
WHATSAPP_ONBOARDING_LOGIN_URL=https://www.nxtutors.com/login
STUDENT_DASHBOARD_URL=https://www.nxtutors.com/user/dashboard
TUTOR_DASHBOARD_URL=https://www.nxtutors.com/teacher/dashboard
```

`public/index.php` reads these from the container environment via `getenv()` (it
does not load the Laravel `.env`), so set them in the ECS task definition /
container env, not only in the package `.env`.

Whether or not direct creation is enabled, every completed signup is also written
to `ONBOARDING_LEADS_DIR` (one JSON file per lead plus an appended `leads.jsonl`)
as an audit record and as the fallback when `WHATSAPP_CREATE_REAL_PROFILE` is off
or a DB write fails (the user still gets a confirmation; the lead is not lost).

Generate a strong secret and store it in Secrets Manager for **both** agents:

```bash
openssl rand -hex 32
```

### curl examples

Valid handoff (returns `reply_text`):

```bash
curl -X POST "$ONBOARDING_URL/whatsapp/onboarding/webhook" \
  -H "Content-Type: application/json" \
  -H "X-NXTUTORS-INTERNAL-SECRET: $ONBOARDING_AGENT_INTERNAL_SECRET" \
  -d '{
    "source": "lead_intake_agent",
    "wa_message_id": "wamid.test123",
    "wa_phone": "919999999999",
    "message_text": "I want to register as tutor",
    "timestamp": "1234567890",
    "message_type": "text"
  }'
```

Invalid secret (returns `401`):

```bash
curl -i -X POST "$ONBOARDING_URL/whatsapp/onboarding/webhook" \
  -H "Content-Type: application/json" \
  -H "X-NXTUTORS-INTERNAL-SECRET: wrong-secret" \
  -d '{"source":"lead_intake_agent","wa_message_id":"wamid.x","message_text":"signup"}'
# HTTP/1.1 401 Unauthorized
# {"status":"unauthorized","reason":"invalid_internal_secret"}
```

Handoff health:

```bash
curl -s "$ONBOARDING_URL/health/internal-handoff"
# {"status":"ok","internal_handoff":{"onboarding_agent_internal_secret_configured":true,"handoff_route_enabled":true}}
```

### Lead-intake side

Lead-intake must, for each forwarded signup/onboarding message:

1. POST the handoff to `$ONBOARDING_URL/whatsapp/onboarding/webhook` with the
   `X-NXTUTORS-INTERNAL-SECRET` header set to the shared secret. Forward **every**
   message from a user who is in (or starting) signup, not only the first — the
   onboarding agent keeps the conversation state and needs each reply to advance.
2. On a `200/202 accepted` response (`handled:true`), send the returned
   `reply_text` to the user as the single WhatsApp reply.
3. On `forwarded` (`handled:false` / `forward_to_lead_intake:true`), onboarding
   does not own this message — handle it with lead-intake's own logic and send
   that reply instead.
4. On `duplicate`, send nothing.
5. On `401`/`503`, alert — the secret is wrong/missing or onboarding is
   misconfigured.

## Health Endpoints

The onboarding agent exposes:

```text
/health                    overall status + handoff/whatsapp check summary
/health/live               liveness
/health/ready              readiness
/health/db                 database connectivity (503 if configured and down)
/health/whatsapp           Meta credential configuration status
/health/internal-handoff   secret configured + handoff route enabled
/health/deep               full dependency check (Laravel form, auth-protected)
```

## Meta Webhook URL

The onboarding agent is **not** the public Meta webhook for the shared number.
Keep the Meta callback URL pointed at the **lead-intake** agent. The
`GET /whatsapp/onboarding/webhook` verification endpoint here exists only for
defence in depth and standalone testing; if you ever register it directly, use
the same `META_WHATSAPP_VERIFY_TOKEN` stored in Secrets Manager:

```text
https://your-onboarding-domain/whatsapp/onboarding/webhook
```

## Rotate Meta Token

1. Generate a new Meta access token.
2. Update `META_WHATSAPP_ACCESS_TOKEN` in AWS Secrets Manager.
3. Force a new ECS deployment:

```bash
aws ecs update-service --cluster CLUSTER --service SERVICE --force-new-deployment
```

4. Run smoke tests.
5. Revoke the old token.

## Pause Onboarding

Fast runtime option:

```bash
php artisan nxtutors:onboarding:pause --reason="incident reason"
```

Environment flag option:

```text
WHATSAPP_ONBOARDING_PAUSED=true
WHATSAPP_OUTBOUND_PAUSED=true
```

Resume:

```bash
php artisan nxtutors:onboarding:resume
```

## Cost Controls

- Keep `WHATSAPP_ONBOARDING_LLM_ENABLED=false` unless explicitly needed.
- Use Aurora Serverless v2 min/max ACU per environment.
- Use low ECS desired counts in dev.
- Keep S3 lifecycle policies for media and analytics exports.
- Use CloudWatch alarms for queue backlog, Meta 429 spikes, and profile creation failures.
- Use `WHATSAPP_CREATE_REAL_PROFILE=false` in dev until end-to-end tests pass.

## Branch Protection

Recommended GitHub branch rules:

- Require PR reviews for `main`.
- Require `CI` workflow to pass.
- Require linear history or squash merges.
- Restrict direct pushes to `main`.
- Restrict production deployments to release tags or manual approved workflow.
- Enable secret scanning and Dependabot alerts.

## Production Checklist

- GitHub prod Environment has required reviewers.
- OIDC roles exist and no AWS access keys are stored in GitHub.
- Terraform state backend is configured.
- Secrets Manager contains runtime secrets.
- ALB HTTPS certificate is valid.
- Meta webhook URL is updated.
- `WHATSAPP_CREATE_REAL_PROFILE=true` only after staging succeeds.
- `TERMS_ALLOW_LOCAL_PLACEHOLDER=false`.
- `/health/live` and `/health/ready` pass.
- Rollback workflow tested in staging.
