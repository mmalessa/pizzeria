# ADR-0001: Message broker selection — Kafka and RabbitMQ, split by traffic shape

**Status:** Accepted, 2026-08-06

## Context

`doc/09_architecture.md` §5 commits every cross-Bounded-Context relationship to event-driven replication — no context ever queries another live (`doc/05_connect_message_flows.md` §0, `doc/07_define_context_map.md` §2). That leaves open *which* broker technology carries that traffic. `doc/09_architecture.md` §6 originally deferred the choice: "current thinking leans towards Kafka for the log-replay-friendly, one-to-many... relationships... and RabbitMQ for more task-like, point-to-point process communication — but this split... is deliberately deferred until the detailed message-flow diagram between contexts exists."

That diagram (`doc/05_connect_message_flows.md`) and the finalised context map (`doc/07_define_context_map.md`) are now both finished and reviewed. Read together, they show two genuinely different traffic shapes, not a spectrum:

* **Broadcast, one publisher → several independent consumers**, each building or rebuilding its own local read-model replica (`doc/05_connect_message_flows.md` §0). This is the bulk of the system's cross-context traffic and matches the **Open Host Service + Published Language** pattern that `doc/07_define_context_map.md` §5 identifies for almost every relationship: Pizzeria Lifecycle's status broadcast (5 independent consumers), and Resource Management's table/waiter/chef/menu facts (2–3 independent consumers each), plus narrower reverse-traffic replicas (visit counts, table occupancy, readiness data).
* **Point-to-point, single consumer, task-shaped**, with no replay value — the "one genuine exception" `doc/07_define_context_map.md` §5 calls out, plus its one unlisted sibling: Guest Service ↔ Kitchen order fulfilment (`OrderSentToKitchen`, `OrderAccepted`, `OrderReadyForPickup` — a bespoke Customer-Supplier exchange between exactly two contexts) and Kitchen → Resource Management's `ChefFinishedPizza` trigger (checked once against a single aggregate instance).

Separately, `doc/01_understand.md` §3 and §6 list "both synchronous and asynchronous communication" and "different inter-service communication strategies (sync/async, messaging, event-driven)" as explicit product/portfolio goals for this project — it isn't only a technical-fit question here, using two broker technologies is part of what the project set out to demonstrate.

## Decision

Use **two** brokers, assigned by traffic shape, not by context:

* **Kafka** for every Open Host Service + Published Language relationship — the broadcast/replica-synchronisation traffic listed above. Per-consumer-group offsets and a retained log are a direct fit for "N consumers independently replaying the same stream," including rebuilding a replica from scratch if one is ever reset.
* **RabbitMQ** for the two point-to-point task exchanges — Guest Service ↔ Kitchen order fulfilment, and Kitchen → Resource Management's `ChefFinishedPizza`. Neither has replay value; both are closer to a routed job than a replicated stream.

This assignment is recorded in `doc/09_architecture.md` §5 ("Broker assignment"), which is the authoritative, living description — this ADR captures *why*, not the day-to-day detail.

### Alternatives considered

* **Kafka only.** Simpler operationally (one broker to run and learn) — the point-to-point traffic would get its own topic and a single consumer group instead of a routed queue. Rejected: workable but a poorer idiomatic fit for task-shaped traffic, and forgoes the sync/async variety `doc/01_understand.md` explicitly wants demonstrated.
* **RabbitMQ only.** Also simpler operationally — RabbitMQ's fanout exchanges can broadcast to multiple queues too. Rejected: loses log retention/replay, which several of this system's read-model replicas rely on conceptually as a way to rebuild themselves from scratch (`doc/05_connect_message_flows.md` §0).

## Consequences

* **Positive:** each broker is used for exactly the traffic shape it's naturally suited to; the project gets to demonstrate genuine sync/async and messaging variety, one of its own stated goals, rather than defaulting to a single technology for uniformity's sake. No second runtime is needed — `doc/09_architecture.md` §3 already establishes RoadRunner's `jobs` plugin (or whatever runtime is eventually chosen) as broker-agnostic, dispatching both AMQP and Kafka consumption to the same PHP workers. The wire format was already broker-agnostic (JSON + JSON Schema, `doc/09_integration_contracts.md` §1), so this decision cost nothing there.
* **Negative:** two brokers to deploy, operate, and learn instead of one — real operational surface for a solo project, accepted here because the variety is a deliberate goal, not incidental complexity. Two broker client libraries/adapters instead of one.
* **Follow-on decisions this unblocked:** idempotent consumption per broker (`doc/09_architecture.md` §5, "Idempotent consumption" — resolved: no inbox-shaped dedup table needed anywhere today, given one fix to `doc/08_kitchen_aggregates.md`'s `AcceptOrder` invariant) and the integration-event envelope shape (`doc/09_integration_contracts.md` §2 — resolved: `eventId`, `eventType`, `schemaVersion`, `occurredAt`, `correlationId`, `payload`).
* **Still open, deliberately not resolved here:** the specific HTTP/worker runtime used to talk to either broker (`doc/09_architecture.md` §6) — orthogonal to this decision. Deliberately deferred past even "before implementation begins," to after a first implementation attempt and real testing, per the project owner's call — the choice should be grounded in how each candidate behaves under this project's own code, not documentation claims alone. `doc/09_architecture.md` §6 carries research on file for that later decision (RoadRunner's `jobs` plugin natively covers this decision's now-locked dual-broker requirement today; FrankenPHP's queue story is either a synthetic-HTTP-request workaround or an experimental extension; Swoole has no comparable first-party unified multi-broker plugin) — not a conclusion, no candidate eliminated.
