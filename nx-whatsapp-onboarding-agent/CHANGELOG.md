# Changelog

## Unreleased

- Fixed the shared-number webhook conflict: the onboarding agent now accepts secure internal handoffs from the lead-intake agent and is no longer expected to be the public Meta webhook receiver.
- Internal handoff detection by `X-NXTUTORS-INTERNAL-SECRET` header or `source = lead_intake_agent`; handoffs are authenticated against `ONBOARDING_AGENT_INTERNAL_SECRET` instead of a Meta signature.
- Returns `401 {"status":"unauthorized","reason":"invalid_internal_secret"}` for a wrong/missing internal secret, and `503` in production when the server secret is not configured (never silently accepted).
- Normalizes handoff field aliases (`wa_phone|phone|from`, `message_text|text|body`, `wa_message_id|message_id|id`), detects student/tutor/unknown role, and returns `reply_text` for lead-intake to send (no duplicate WhatsApp sending).
- Added idempotency on `wa_message_id` (duplicate handoffs return `reply_text: null`) and structured per-handoff logging (correlation_id, masked phone, source, mode, secret validity, role, reply presence, duplicate flag).
- Genuine Meta webhook requests still require a valid `X-Hub-Signature-256` (`META_WHATSAPP_APP_SECRET` or `META_APP_SECRET`).
- Read the internal/Meta secrets through config so they survive `php artisan config:cache`; added `META_*` env aliases.
- Added health endpoints `/health`, `/health/db`, `/health/whatsapp`, `/health/internal-handoff`; expanded contract and controller tests; documented the handoff architecture and curl examples.

## 0.2.0

- Added production hardening for AWS operations: policy guard, PII masking, STOP/UNSUBSCRIBE handling, pause/resume commands, health checks, circuit breakers, and retry/backoff controls.
- Added human handoff reason codes, admin notification adapters, audit events, structured logging context, metrics, tracing abstraction, and drift evaluation command.
- Added sanitized Glue/Athena analytics export, load-test scripts, AWS production architecture docs, and operational runbook.
- Updated terms/privacy URLs to NXtutors production links and now shows both Terms and Privacy links before acceptance.
- Hardened OTP delivery through approved WhatsApp template sending and masked the final login phone identifier.

## 0.1.0

- Initial Laravel package scaffold for NXtutors WhatsApp onboarding.
- Added webhook routes, controllers, signature verification, payload parsing, idempotent event storage, and queue jobs.
- Added deterministic finite state machine with separate student and tutor modules.
- Added PostgreSQL-safe migrations and safe legacy `register` compatibility indexes.
- Added secure temporary password generation, OTP hashing, terms guard, profile writers, and legacy schema mapper.
- Added centralized messages, README, local Docker scaffold, scripts, Terraform placeholders, and unit tests.
