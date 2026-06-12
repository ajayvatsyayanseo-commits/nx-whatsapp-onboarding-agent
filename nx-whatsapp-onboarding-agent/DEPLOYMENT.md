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

## Meta Webhook URL

After ALB/domain deployment, set the Meta webhook callback URL to:

```text
https://your-domain/whatsapp/onboarding/webhook
```

Use the same `META_WHATSAPP_VERIFY_TOKEN` stored in Secrets Manager.

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
