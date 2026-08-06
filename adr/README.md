# Architecture Decision Records

Formal records of significant architecture decisions made during design — broader-reaching and more permanent than the lightweight [Design Notes](../doc/design_notes/README.md), one file per decision, `NNNN-title-slug.md`. Each follows Context / Decision / Consequences, and records alternatives considered, not just the outcome.

← Back to [`doc/README.md`](../doc/README.md).

* [ADR-0001: Message broker selection — Kafka and RabbitMQ, split by traffic shape](0001-message-broker-selection.md) — Kafka for the broadcast/replica-synchronisation relationships, RabbitMQ for the two point-to-point task exchanges. Accepted 2026-08-06.
