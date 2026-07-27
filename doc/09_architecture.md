# 09. Architecture

**Part of:** [Architecture (Clean Architecture)](README.md#architecture-clean-architecture) — this step goes beyond the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process) and is its natural extension towards implementation.

**Purpose:** translate the tactical model from the Code step (`08_*`) into an implementation structure — layers, module boundaries, deployment topology, and the technical constraints that follow from decisions already made at the modelling level. This document does not re-model the domain; it only decides how the four Bounded Contexts from `07_define_context_map.md` get built and run.

**Key question:** *given the model we already have, how is it actually structured, deployed, and made to run?*

---

## 1. Style: "Distributed Monolith"

One codebase, one Symfony kernel, one Composer project — but deployed as **N independently running processes**, one set per Bounded Context.

This isn't an accident of convenience: `07_define_context_map.md` §2 already establishes that **no context ever queries another live** — every cross-context relationship is event-driven replication. A "Distributed Monolith" is the physical enforcement of a decision the model already made. The shared codebase keeps a solo project tractable (one repo, one dependency tree, one place to refactor a shared technical concern); the independent-process deployment keeps the Bounded Context boundaries real rather than aspirational.

**What this buys us:**
* Each Bounded Context can be scaled, restarted, and fail independently (e.g. Kitchen under a Friday-night order spike doesn't need Resource Management to scale with it).
* A future split into genuinely separate repositories/services is a deployment change, not an architectural one — the boundaries are already where they need to be.

**What this costs us:**
* Cross-context domain events must travel over a real broker, not an in-process event dispatcher (§6).
* Nothing in the language stops one context's code from importing another's directly — see §3 on why that's an accepted risk here, not a solved problem.

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
* **`Shared/`** — cross-cutting *technical* plumbing only (event bus abstraction, base Doctrine types, base Messenger-equivalent wiring). This is **not** a DDD Shared Kernel — `07_define_context_map.md` §5 explicitly rules that out at the domain-model level. Nothing here carries domain behaviour or domain language; if a class in `Shared/` starts looking like it encodes a business rule, it's misplaced.

**Boundary enforcement: convention only.** Nothing in the codebase (no `deptrac`, no `phpat`, no CI check) stops `GuestService/` from importing a class out of `Kitchen/Domain` directly. This is a deliberate, accepted simplification for a demo project — not an oversight. Revisit if this ever stops being a demo project.

---

## 3. Deployment model

One Docker image, built once from the single codebase. Each deployment of that image is parameterised by the **command** it runs at container start — nothing else differs between deployments.

| Bounded Context | HTTP process | Consumer process(es) |
|---|---|---|
| Guest Service | RoadRunner serving `GuestService/UI` routes | consumer(s) for its inbound integration events |
| Kitchen | RoadRunner serving `Kitchen/UI` routes | consumer(s) for its inbound integration events |
| Resource Management | RoadRunner serving `ResourceManagement/UI` routes | consumer(s) for its inbound integration events |
| Pizzeria Lifecycle | RoadRunner serving `PizzeriaLifecycle/UI` routes | consumer(s) for its inbound integration events |

Each Bounded Context therefore runs as at least two container instances (its HTTP server + its consumer(s)), all from the same image, differentiated only by the startup command and an environment variable selecting which context's routes/handlers get bootstrapped.

**HTTP runtime: RoadRunner, not PHP-FPM.** PHP itself is single-threaded per request/message — that's true regardless of how the code is split into modules, and isn't by itself a reason to containerise per context (concurrency in PHP always comes from running more processes, monolith or not). The real reason for one container set per context is independent scaling and failure isolation (§1), not the threading model.

RoadRunner does change one thing PHP-FPM developers can take for granted: **the PHP worker process stays alive across requests.** This means:
* No mutable static or global state — it will leak between unrelated requests handled by the same worker.
* The DI container must either be stateless across requests or explicitly reset between them.

This is a constraint on how `Infrastructure/` and `UI/` code is written in every context, not a per-context decision.

---

## 4. Persistence

Classic aggregates + repositories — **not event sourcing**. Nothing in `08_*` requires reconstructing aggregate state from a full event history; adding that machinery now would be complexity without a driving requirement.

**One database connection per Bounded Context**, in practice at least one separate schema per context, even where contexts happen to share a physical database server. This is the storage-level enforcement of the same rule as §1: no live cross-context queries. A separate schema makes an accidental cross-context `JOIN` a hard error, not just a convention someone could quietly break.

Domain events are recorded and published separately from aggregate persistence — see the outbox pattern in §6.

---

## 5. Inter-context communication

Every cross-context relationship in `07_define_context_map.md` is, and remains, event-driven replication — nothing here changes that. What this section adds is the delivery guarantee around it:

* **Outbox pattern, on publish.** An aggregate's state change and the domain event(s) it produces are recorded in the same local transaction; a separate process delivers events from that outbox to the broker. This avoids the dual-write problem (aggregate saved, event lost on crash before publish, or vice versa).
* **Inbox pattern, on synchronous HTTP input only.** Used to deduplicate retried HTTP requests (e.g. a client retry after a timed-out response) via an idempotency key. It is deliberately **not** assumed for broker consumption — see Open Questions.

The specific broker(s), and the exact library used to talk to them, are not decided yet — see §7. This section documents the contract every context is built against, independent of that choice: **publish happens through the outbox, after commit; consumption of HTTP input is deduplicated through the inbox; nothing crosses a context boundary except through this path.**

---

## 6. Open Questions

* **Broker choice(s) — Kafka vs RabbitMQ, or both.** Current thinking leans towards Kafka for the log-replay-friendly, one-to-many "Open Host Service + Published Language" relationships (`07_define_context_map.md` §5) that drive view/replica synchronisation, and RabbitMQ for more task-like, point-to-point process communication — but this split, and even whether both are needed, is **deliberately deferred** until the detailed message-flow diagram between contexts exists. Decide there, not here.
* **Symfony Messenger.** Under consideration as the abstraction layer over whichever broker(s) are chosen, but it has known friction points with Kafka specifically. Deferred pending either a workaround or an alternative.
* **Idempotent consumption from the broker.** At-least-once delivery from Kafka/RabbitMQ means a consumer could see the same domain event twice. Unlike the HTTP inbox (§5), no pattern is assumed here yet — it may turn out that the domain operation is naturally idempotent and needs no extra bookkeeping, or it may need its own de-duplication mechanism. Decide once the message-flow diagram is in hand, together with the broker choice above.
