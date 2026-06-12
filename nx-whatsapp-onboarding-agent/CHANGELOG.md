# Changelog

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
