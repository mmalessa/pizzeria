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
* **Inbox pattern, confirmed today for synchronous HTTP input.** Used to deduplicate retried HTTP requests (e.g. a client retry after a timed-out response) via an idempotency key. Whether the same idempotency-key technique is *also* how broker consumption gets deduplicated is not decided yet — it depends on the broker choice — see Open Questions.

The specific broker(s) are not decided yet — see §6, and neither is the specific runtime used to talk to them (§3 uses RoadRunner's `jobs` plugin only as a working example of what that runtime needs to support). This section documents the contract every context is built against, independent of both choices: **publish happens through the outbox, after commit; consumption of HTTP input is deduplicated through the inbox; nothing crosses a context boundary except through this path.** Whatever deduplication broker consumption ends up needing (§6) sits alongside this, not in place of it.

---

## 6. Open Questions

* **Broker choice(s) — Kafka vs RabbitMQ, or both.** Current thinking leans towards Kafka for the log-replay-friendly, one-to-many "Open Host Service + Published Language" relationships (`07_define_context_map.md` §5) that drive view/replica synchronisation, and RabbitMQ for more task-like, point-to-point process communication — but this split, and even whether both are needed, is **deliberately deferred** until the detailed message-flow diagram between contexts exists. Decide there, not here.
* **Idempotent consumption from the broker.** At-least-once delivery from Kafka/RabbitMQ means a consumer could see the same domain event twice. No pattern is assumed here yet — it may turn out that the domain operation is naturally idempotent and needs no extra bookkeeping, or it may need its own de-duplication mechanism, quite possibly an inbox-shaped one (a local table of already-processed message IDs, the same idea as the HTTP inbox in §5, just keyed off the broker message rather than an HTTP request). Kafka's consumer-offset model may make this less necessary than an AMQP broker like RabbitMQ would. Decide once the broker choice and message-flow diagram above are in hand — not before.
* **Specific HTTP/worker runtime — RoadRunner vs. an alternative (Swoole, FrankenPHP, ...).** What's decided (§3): every context runs on the *same* persistent-worker runtime — no per-context exploration of different HTTP technologies. What's not decided: which one. RoadRunner is used throughout §3 as a concrete working example because it's the current leading candidate, not because it's been chosen. Note this deliberately narrows `01_understand.md`'s original product goal of "exploration of different HTTP servers/technologies within one system" down to "explore one, uniformly" — a single shared runtime is what the independently-deployed-processes model (§1) actually needs; running genuinely different HTTP technologies per context would add operational variance this project has no reason to take on. Decide the specific product before implementation begins, not here.
