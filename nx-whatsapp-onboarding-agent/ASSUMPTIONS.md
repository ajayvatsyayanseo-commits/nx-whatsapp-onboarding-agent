# Assumptions here

- The provided `register` schema exists in the NXtutors Laravel app and has a normal primary key usable by Eloquent.
- The website can authenticate with `phone` plus `password` hash, or can be adapted to treat `phone` as the login identifier.
- `force_password_reset` does not currently exist, so the package adds it safely if the `register` table is present.
- `c_password` is not required for authentication. It is kept null to avoid plaintext password storage.
- `status` accepts string values such as `active` and `pending_review`. If the legacy app expects numeric statuses, adapt `RegisterSchemaMapper`.
- `otp_status=1` means verified. If the legacy app uses different values, adapt `RegisterSchemaMapper`.
- WhatsApp phone ownership is verified by sending an OTP to the same WhatsApp sender number.
- Tutor document uploads will eventually store media in encrypted S3; this scaffold accepts media IDs/text placeholders until the S3 adapter is connected.
- `document_number` may be nullable for students, and uniqueness should apply only where present.
- Production terms URLs are owned by NXtutors legal/product teams. The Adobe URL is local-only placeholder data.
- Aurora PostgreSQL is the production database target. MySQL compatibility is not a goal for new migrations.
- Redis/ElastiCache is available for cache, rate limiting, and queues.
- Existing Laravel app logging can be configured to use the package PII masker in processors/formatters.
- Human support teams have or will build an internal queue consuming `human_handoff_tickets`.
- Terraform modules require account-specific VPC, KMS, subnet, domain, and IAM inputs before use.
- LLM extraction is optional and disabled by default. PHP validators remain the source of truth.
