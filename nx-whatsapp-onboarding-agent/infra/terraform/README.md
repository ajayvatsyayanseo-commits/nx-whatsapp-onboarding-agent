# Terraform

This directory contains reusable Terraform for the NXtutors WhatsApp Onboarding Agent.

Use the environment wrappers:

```text
envs/dev
envs/staging
envs/prod
```

Before applying:

1. Create a Terraform state S3 bucket and DynamoDB lock table.
2. Uncomment/update the environment `backend.tf`.
3. Copy `terraform.tfvars.example` to `terraform.tfvars`.
4. Fill account-specific values such as VPC/subnets, ACM certificate, domain, bucket names, GitHub org/repo, and any externally managed secret ARNs.

Do not commit real `terraform.tfvars` files.
