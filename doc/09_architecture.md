# 09. Architecture

**Part of:** [Architecture (Clean Architecture)](README.md#architecture-clean-architecture) — this step goes beyond the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process) and is its natural extension towards implementation.

**Purpose:** translate the tactical model from the Code step (`08_*`) into an implementation structure — layers, module boundaries, deployment topology, and the technical constraints that follow from decisions already made at the modelling level. This document does not re-model the domain; it only decides how the four Bounded Contexts from `07_define_context_map.md` get built and run.

**Key question:** *given the model we already have, how is it actually structured, deployed, and made to run?*

---

## 1. Style: "Distributed Monolith"

One codebase, one Symfony kernel, one Composer project — but deployed as **N independently running processes**. Each Bounded Context is not a single process but several: an HTTP process, one or more broker consumer processes, and an outbox relay process — each independently deployable, scalable, and restartable.

This isn't an accident of convenience: `07_define_context_map.md` §2 already establishes that **no context ever queries another live** — every cross-context relationship is event-driven replication. A "Distributed Monolith" is the physical enforcement of a decision the model already made. The shared codebase keeps a solo project tractable (one repo, one dependency tree, one place to refactor a shared technical concern); the independent-process deployment keeps the Bounded Context boundaries real rather than aspirational.

**What this buys us:**
* Each Bounded Context can be scaled, restarted, and fail independently (e.g. Kitchen under a Friday-night order spike doesn't need Resource Management to scale with it).
* A future split into genuinely separate repositories/services is a deployment change, not an architectural one — the boundaries are already where they need to be.

**What this costs us:**
* Cross-context domain events must travel over a real broker, not an in-process event dispatcher (§6).
* No Bounded Context imports another context's code directly — the only path between contexts is published domain events. Nothing in the language enforces this (no `deptrac`, no `phpat`, no CI check — see §2); it's an accepted convention-only risk for a demo project, not a solved problem.

---

## 2. Directory structure

One top-level directory per Bounded Context, each internally layered per Clean Architecture:

```
src/
  GuestService/
    Domain/
    Application/
    Infrastructure/
    UI/
  Kitchen/
    Domain/
    Application/
    Infrastructure/
    UI/
  ResourceManagement/
    Domain/
    Application/
    Infrastructure/
    UI/
  PizzeriaLifecycle/
    Domain/
    Application/
    Infrastructure/
    UI/
  Shared/
    ...
```

* **`Domain/`** — aggregates, entities, value objects, domain services, domain events (`08_<context>_*.md`). No framework dependencies.
* **`Application/`** — command/query handlers, orchestration. Depends on `Domain/` only.
* **`Infrastructure/`** — Doctrine repositories, broker publishers/consumers, HTTP controllers' framework wiring. Depends inward, per the Clean Architecture dependency rule.
* **`UI/`** — HTTP entrypoints (controllers, request/response DTOs) and CLI commands specific to this context.
* **`Shared/`** — cross-cutting *technical* plumbing only (event bus abstraction, base Doctrine types, base Messenger-equivalent wiring). This is **not** a DDD Shared Kernel — `07_define_context_map.md` §5 explicitly rules that out at the domain-model level. Nothing here carries domain behaviour or domain language; if a class in `Shared/` starts looking like it encodes a business rule, it's misplaced. **Only `Infrastructure/` and `UI/` may reference `Shared/`** — `Domain/` and `Application/` never do, which follows from their own dependency rules above but is worth stating explicitly: no class in `Shared/` is ever imported from a context's business logic, only from its technical wiring.

**Boundary enforcement: convention only.** Nothing in the codebase (no `deptrac`, no `phpat`, no CI check) stops `GuestService/` from importing a class out of `Kitchen/Domain` directly. This is a deliberate, accepted simplification for a demo project — not an oversight. Revisit if this ever stops being a demo project.

---

## 3. Deployment model

One Docker image, built once from the single codebase. Each deployment of that image is parameterised by the **command** it runs at container start — nothing else differs between deployments.

| Bounded Context | HTTP process | Consumer process(es) | Outbox relay process |
|---|---|---|---|
| Guest Service | serves `GuestService/UI` routes | consumer(s) for its inbound integration events | relays its outbox to the broker |
| Kitchen | serves `Kitchen/UI` routes | consumer(s) for its inbound integration events | relays its outbox to the broker |
| Resource Management | serves `ResourceManagement/UI` routes | consumer(s) for its inbound integration events | relays its outbox to the broker |
| Pizzeria Lifecycle | serves `PizzeriaLifecycle/UI` routes | consumer(s) for its inbound integration events | relays its outbox to the broker |

Each Bounded Context therefore runs as at least three container instances (its HTTP server, its consumer(s), and its outbox relay), all from the same image, differentiated only by the startup command and an environment variable selecting which context's routes/handlers get bootstrapped.

**HTTP runtime: one persistent-worker PHP runtime, shared by all four contexts, not PHP-FPM.** PHP itself is single-threaded per request/message — that's true regardless of how the code is split into modules, and isn't by itself a reason to containerise per context (concurrency in PHP always comes from running more processes, monolith or not). The real reason for one container set per context is independent scaling and failure isolation (§1), not the threading model. **Which specific runtime (RoadRunner, Swoole, FrankenPHP, ...) isn't decided — see §6** — this document uses RoadRunner as a concrete, working example wherever it needs one, not as a commitment.

A persistent-worker runtime changes one thing PHP-FPM developers can take for granted: **the PHP worker process stays alive across requests.** This means:
* No mutable static or global state — it will leak between unrelated requests handled by the same worker.
* The DI container must either be stateless across requests or explicitly reset between them.

This is a constraint on how `Infrastructure/` and `UI/` code is written in every context, regardless of which specific runtime is eventually picked — not a per-context decision.

**Same runtime for the consumer and outbox relay processes, whichever one is chosen.** RoadRunner's `jobs` plugin is the working example of what this requires: a generic broker-consumer mechanism (AMQP/Kafka/SQS drivers dispatching to PHP workers) — designed in RoadRunner's own docs around intra-app background task queuing, but the consumption mechanism itself is agnostic to payload meaning. The same plugin can back both a context's internal background jobs and its inbound integration-event consumer(s), without needing a second, separate broker-client library. Whichever runtime §6 lands on needs this same property, or the "one runtime for everything" simplification above stops holding.

---

## 4. Persistence

Classic aggregates + repositories — **not event sourcing**. Nothing in `08_*` requires reconstructing aggregate state from a full event history; adding that machinery now would be complexity without a driving requirement.

**One database connection per Bounded Context**, in practice at least one separate schema per context, even where contexts happen to share a physical database server. This is the storage-level enforcement of the same rule as §1: no live cross-context queries. A separate schema makes an accidental cross-context `JOIN` a hard error, not just a convention someone could quietly break.

Domain events are recorded and published separately from aggregate persistence — see the outbox pattern in §6.

---

## 5. Inter-context communication

Every cross-context relationship in `07_define_context_map.md` is, and remains, event-driven replication — nothing here changes that. What this section adds is the delivery guarantee around it:

* **Outbox pattern, on publish.** An aggregate's state change and the domain event(s) it produces are recorded in the same local transaction; a separate process delivers events from that outbox to the broker. This avoids the dual-write problem (aggregate saved, event lost on crash before publish, or vice versa).
* **Inbox pattern, confirmed today for synchronous HTTP input.** Used to deduplicate retried HTTP requests (e.g. a client retry after a timed-out response) via an idempotency key. Broker consumption does **not** reuse this technique — see "Idempotent consumption" below: every current consumer is safe under redelivery without an inbox-shaped dedup table.

The specific runtime used to talk to the broker(s) below isn't decided yet (§3 uses RoadRunner's `jobs` plugin only as a working example of what that runtime needs to support — see §6). This section documents the contract every context is built against, independent of that choice: **publish happens through the outbox, after commit; consumption of HTTP input is deduplicated through the inbox; nothing crosses a context boundary except through this path.**

**Broker assignment: Kafka for broadcast replication, RabbitMQ for point-to-point tasks.** `05_connect_message_flows.md` §0 and `07_define_context_map.md` §5 — both now finished and reviewed — settle this by showing two genuinely different traffic shapes, not one:

* **Kafka** carries every relationship that follows the **Open Host Service + Published Language** pattern (`07_define_context_map.md` §5) — one publisher, multiple independent consumers, each building or rebuilding its own local read-model replica (`05_connect_message_flows.md` §0). This is the bulk of the system's cross-context traffic: Pizzeria Lifecycle's status broadcast (5 independent consumers), and Resource Management's table/waiter/chef/menu facts (2–3 independent consumers each), plus the narrower reverse-traffic replicas (visit counts, table occupancy, readiness data). Kafka's per-consumer-group offsets and retained log are a direct fit for "N consumers independently replaying the same stream," including rebuilding a replica from scratch if one is ever reset.
* **RabbitMQ** carries the two relationships that are genuinely point-to-point and task-shaped, not broadcast — the "one genuine exception" `07_define_context_map.md` §5 already calls out plus its one unlisted sibling: Guest Service ↔ Kitchen order fulfilment (`OrderSentToKitchen`, `OrderAccepted`, `OrderReadyForPickup` — a bespoke Customer-Supplier exchange between exactly two contexts, `07_define_context_map.md` §3) and Kitchen → Resource Management's `ChefFinishedPizza` trigger (single consumer, checked once against one aggregate instance, `07_define_context_map.md` §3). Neither has replay value; both are closer to a routed job than a replicated stream.

This also directly serves this project's own stated goals (`01_understand.md` §3, §6: "both synchronous and asynchronous communication," "different inter-service communication strategies (sync/async, messaging, event-driven)") — two broker technologies, each matched to the traffic shape it actually suits, is exactly the variety this portfolio project set out to demonstrate. It doesn't cost a second runtime: §3 already establishes RoadRunner's `jobs` plugin (or whatever runtime §6 eventually settles on) as broker-agnostic — the same mechanism dispatches both AMQP and Kafka consumption to PHP workers.

**Kafka message key = the id of the aggregate the event is about** (e.g. `chefId` on `resource-management.chef`, `menuItemId` on `resource-management.menu`) — every OHS/Published Language topic above is a per-aggregate-type stream carrying that aggregate's full lifecycle, and Kafka only orders messages within a partition. An unkeyed (or randomly keyed) message can land on any partition, so two events for the same aggregate — e.g. `ChefHired` then `ChefTerminated` — could be consumed out of order by a multi-partition consumer group, corrupting the replica this same section already relies on being safe under redelivery below. Keying by aggregate id pins that aggregate's whole event history to one partition, so per-aggregate ordering holds regardless of partition count or consumer parallelism. This also gives the still-open `cleanup.policy=compact` question (§6) the key it would need — settling the key doesn't settle that question, but removes one of its prerequisites.

**Idempotent consumption: no broker-level inbox needed anywhere, traced case by case.** At-least-once delivery means every inbound integration event must be safe under redelivery. Checked against every consumer in this system rather than assumed:

* **Every Kafka-consumed event** (the broadcast/replica traffic above) is already safe by construction: DN-2 (`design_notes/dn_0002.md`) already requires every derived count or sum fed by another aggregate's events to be tracked by a keyed set/map, never a bare `+=` accumulator — redelivery of the same ID is then a no-op. Every other replica field is a plain last-write-wins upsert (e.g. `TableCapacityChanged` sets `capacity`), also naturally idempotent.
* **`ChefFinishedPizza` → `FinalizeChefTermination`** (RabbitMQ): already safe. `Chef.status` gates the transition to `Terminating` only (`08_resource_management_aggregates.md` §4, invariant 3); a redelivery arrives after `status` is already `Terminated`, fails the guard, and is silently ignored.
* **`OrderAccepted`** (RabbitMQ, Kitchen → Guest Service): transient GUI relay, never persisted (`05_connect_message_flows.md` §0) — a duplicate relay is cosmetic, not a correctness issue.
* **`OrderSentToKitchen` → `AcceptOrder`** (RabbitMQ): was **not** safe — `08_kitchen_aggregates.md` §1 had no existence check, so a redelivery would have spawned a second `KitchenOrder` and duplicate `PizzaTask`s for the same order. Fixed directly in that document (§1, invariant 2): `AcceptOrder` now no-ops if a `KitchenOrder` for the (deterministic) `kitchenOrderId` already exists — the same DN-2 discipline, extended one step earlier, to aggregate creation itself.

No separate broker-level inbox/dedup table is needed today: DN-2's keyed-tracking discipline plus per-aggregate state guards, with the one `AcceptOrder` fix above, cover every consumer this system currently has. Revisit this conclusion whenever a new integration event is added — the pattern to check is the same one used here (does the triggered command's own identity or state already make a repeat a no-op?), not a default "add an inbox" reflex.

---

## 6. Open Questions

* ~~Broker choice(s) — Kafka vs RabbitMQ, or both.~~ **Resolved, see §5 "Broker assignment."** The detailed message-flow diagram this was deferred to (`05_connect_message_flows.md`) is now finished and reviewed; it confirms the original leaning — Kafka for the broadcast/replica-synchronisation relationships, RabbitMQ for the two point-to-point task exchanges.
* ~~Idempotent consumption from the broker.~~ **Resolved, see §5 "Idempotent consumption."** Traced case by case against every consumer in the system: DN-2's keyed-tracking discipline plus per-aggregate state guards already cover everything, given one fix to `08_kitchen_aggregates.md`'s `AcceptOrder` invariant — no broker-level inbox table needed anywhere today.
* **Specific HTTP/worker runtime — RoadRunner vs. an alternative (Swoole, FrankenPHP, ...).** What's decided (§3): every context runs on the *same* persistent-worker runtime — no per-context exploration of different HTTP technologies. What's not decided: which one. RoadRunner is used throughout §3 as a concrete working example because it's the current leading candidate, not because it's been chosen. Note this deliberately narrows `01_understand.md`'s original product goal of "exploration of different HTTP servers/technologies within one system" down to "explore one, uniformly" — a single shared runtime is what the independently-deployed-processes model (§1) actually needs; running genuinely different HTTP technologies per context would add operational variance this project has no reason to take on. **Deliberately decided after a first implementation attempt and real testing, not on paper before any code exists** — a deliberately stronger bar than "before implementation begins," so the choice is grounded in how each candidate actually behaves under this project's own code, not just documentation claims.
  * **Research on file for that later decision** (not a conclusion, no candidate eliminated): with the broker split now locked in (ADR-0001), the concrete requirement this runtime has to satisfy is dispatching *both* Kafka and RabbitMQ consumption to the same PHP worker pool the HTTP process uses (§3's "one client library, no bespoke build" bar). RoadRunner's `jobs` plugin covers this natively today with a single configuration-driven API across RabbitMQ, Kafka, SQS, Beanstalk, and NATS drivers (https://docs.roadrunner.dev/docs/queues-and-jobs/overview-queues, https://docs.roadrunner.dev/docs/queues-and-jobs/kafka). FrankenPHP's worker mode is HTTP-request-shaped only — queue consumption means wrapping each message as a synthetic HTTP request, or its dedicated `frankenphp-queue` extension, explicitly documented as experimental and not production-ready (https://frankenphp.dev/docs/worker/, https://github.com/dunglas/frankenphp-queue). Swoole is actively maintained and a legitimate PHP-FPM alternative, but has no comparable first-party unified multi-broker job plugin — `php-amqplib`/`rdkafka` would need to be wired in by hand inside coroutine-based consumers. None of this substitutes for actually trying a candidate against this project's own code.
* **Kafka topic cleanup policy for replica topics — `delete` (time/size retention) vs. `compact` (log compaction, keyed by aggregate id).** Not decided. The Kafka topics that exist today (`resource-management.menu`, `resource-management.chef` — provisioned explicitly by `.docker/kafka/create-topics.sh`, since broker-side auto-create does not reliably fire for consumer-only metadata requests) are exactly the replica-synchronisation traffic §5 describes: a full local copy of another context's aggregate state, safe under any redelivery per §5's "Idempotent consumption" tracing. `compact` retains only the latest message per key, forever, independent of `retention.ms` — a closer structural match to "rebuilding a replica from scratch" (§5's stated reason for choosing Kafka here) than `delete`-based retention, which only preserves full history for as long as `retention.ms`/`retention.bytes` happens to allow. Against that: it changes replay semantics — only the latest value per key survives, not the full event sequence — which is fine for the last-write-wins replica fields §5 already describes, but hasn't been checked field-by-field the way idempotent consumption was. The dev topics created today use the client default (`cleanup.policy=delete`, unconfigured `retention.ms`) as a placeholder, not a decision. Revisit once a context actually publishes to these topics (there's no producer yet — only Kitchen's consumer side exists).
