# 09. Integration Contracts

**Part of:** [Architecture (Clean Architecture)](README.md#architecture-clean-architecture) — this step goes beyond the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process) and is its natural extension towards implementation.

**Purpose:** define how the *shape* of every integration event crossing a Bounded Context boundary is described, shared, and validated. `07_define_context_map.md` §5 already names **Published Language** as the pattern behind almost every relationship in this system — this document is that pattern's technical realization: a schema per event, and an enforced validation step on both ends of the wire. It complements `09_architecture.md` §5 (outbox on publish, broker assignment, and idempotent consumption on receipt — delivery guarantees generally) without repeating it — this document is about the *shape* of what's delivered, not the delivery mechanism itself.

**Key question:** *what does an integration event look like on the wire, where does that definition live, and what stops a malformed one from ever being sent or accepted?*

---

## 1. Format: JSON + JSON Schema

Considered alongside Avro and Protobuf; JSON + JSON Schema was chosen as the most universal option:

* **Broker-agnostic.** `09_architecture.md` §5 settles the broker assignment as Kafka **and** RabbitMQ, split by traffic shape — a single wire format needs to work across both. Avro is idiomatically tied to a Kafka-centric ecosystem (Confluent Schema Registry) and would fit the RabbitMQ half of that split poorly even on its own terms; JSON carries no such coupling to either broker.
* **No compile step.** Protobuf and Avro both generate code from a schema definition, which gives structural correctness "for free" but adds a build step and, for Avro particularly, thinner PHP tooling. JSON requires an explicit validation step regardless — which is exactly what's being required here anyway (§2), so there's no lost safety by not code-generating.
* **Human-readable**, which matters for a solo project where the person debugging a bad message and the person who defined its schema are the same person.

Protobuf remains a reasonable option to revisit later if message size/throughput ever becomes a real constraint — nothing here rules it out permanently, it's just not the starting choice.

---

## 2. Envelope shape

Every integration event on the wire is a small, uniform envelope wrapping a domain-specific payload — the same shape regardless of which broker carries it (§1's broker-agnostic choice extends to the envelope, not just the payload format):

```json
{
  "eventId": "b6e6c1e0-1a2b-4c3d-9e0f-1a2b3c4d5e6f",
  "eventType": "pizzeria.kitchen.order-ready-for-pickup",
  "schemaVersion": 1,
  "occurredAt": "2026-08-06T14:32:10.123Z",
  "correlationId": "3f9e8d7c-6b5a-4938-8271-0f1e2d3c4b5a",
  "payload": {}
}
```

* **`eventId`** — a UUID (v4), unique per published event instance. Not needed by any consumer's correctness logic today (`09_architecture.md` §5's "Idempotent consumption" traces every current consumer to natural idempotency via domain identity or aggregate state, not a message ID) — it exists for the same reason JSON itself was chosen over Avro/Protobuf (§1): a solo project's debugging session needs a stable handle to find one specific message in the outbox, a broker's log, and a consumer's error output, without reconstructing one from the payload. It's also the field any future dedup mechanism would key off, should `09_architecture.md` §5's conclusion ever need revisiting for a new event.
* **`eventType`** and **`schemaVersion`** — the wire identifier and version established in §4/§5 below, now given a fixed home in the envelope rather than assumed to live at the top level implicitly.
* **`occurredAt`** — ISO-8601, UTC, millisecond precision. When the domain event was *raised*, not when the outbox relayed it (`09_architecture.md` §5) — the two can differ under backpressure, and it's the raise time that matters for reconstructing what happened, when.
* **`correlationId`** — a UUID (v4) tying together every event belonging to one end-to-end business scenario (`05_connect_message_flows.md`'s Scenarios 1–7), across both brokers. **Propagation rule:** the first integration event published as a direct consequence of a user/Manager-initiated command mints a new `correlationId` (defaulting to its own `eventId`, so no separate ID-minting step is needed). Every aggregate **created or updated** as a result of consuming an inbound integration event **stores that event's `correlationId` as part of its own state** — not just for the instant of consumption, but for as long as the aggregate exists — so that whatever integration event the aggregate eventually raises carries the same `correlationId` forward, whether that happens immediately as a direct reaction, or later, as the tail end of a chain of purely internal domain events with no further inbound message in between. This makes one guest group's visit, or one order's journey from `OrderSentToKitchen` through `ChefFinishedPizza` to `OrderReadyForPickup`, traceable end-to-end in logs with a single grep — the same solo-debugging rationale as `eventId` above and §1's format choice.

  **Worked example (`05_connect_message_flows.md` Scenario 2):** `OrderSentToKitchen` mints `correlationId = X` — no earlier integration event to inherit from. Kitchen consumes it and runs `AcceptOrder` (`08_kitchen_domain_services.md`), which does two things with `X`: it raises `OrderAccepted` immediately, carrying `X` forward as a direct reaction (the simple case); and it stores `X` on the `KitchenOrder` it creates, alongside `kitchenOrderId` (`08_kitchen_aggregates.md` §1). Much later, once every `PizzaTask` reaches `Ready` — a chain of purely internal events (`PizzaPrepared` → Order Progress read model → `OrderReadinessCheck` → `MarkOrderReady`) with no further inbound integration event anywhere in between — `KitchenOrder` raises `OrderReadyForPickup`, carrying `X` forward from its own stored state, not from any message it just consumed. Both `OrderAccepted` and `OrderReadyForPickup` end up correlated to the same `X`, even though only one of them was a direct reaction to consuming something. Tracing every other auto-triggered integration event in this system against this same rule (`PizzeriaClosed`, `WaiterTerminated`, `ChefTerminated`) shows each of them *is* a direct, immediate reaction to consuming one specific inbound event — `KitchenOrder`/`OrderReadyForPickup` is currently the system's only case that needs the stored-state half of this rule, not the norm.
* **`payload`** — the domain-specific fields, documented per event in each context's own `08_<context>_integration_events.md` and validated against the `contracts/` schema named by `(eventType, schemaVersion)` (§5).

**Deliberately out of scope:** distributed-tracing metadata (OpenTelemetry trace/span IDs and the like). `correlationId` covers this project's actual need — following one scenario across two brokers by hand — without taking on an APM/tracing-backend dependency that has no other justification here. Revisit only if a real tracing backend is ever introduced for its own reasons.

---

## 3. Validation rule

**Every integration event is schema-validated twice: once before it is sent, once after it is received.** This is a hard rule, not a convention left to each context to remember:

* **On publish**, validation is wired into the outbox (`09_architecture.md` §5) — an event can't be written to the outbox, and therefore can never reach the broker, without passing validation first.
* **On receipt**, validation is wired into the broker consumer that processes inbound integration events. `09_architecture.md` §5's "Idempotent consumption" concludes no consumer needs an inbox-shaped dedup table today — but schema validation happens regardless of that conclusion, and would keep happening the same way even if a future event needed one. Note this is a different Inbox usage than the one confirmed earlier in `09_architecture.md` §5: that one deduplicates synchronous HTTP requests from the frontend/user, not context-to-context traffic, since no context ever calls another context over HTTP — contexts only reach each other through the outbox/broker. The two would just happen to share the same technique, not the same code path.

So publishing or consuming an integration event without going through validation isn't a code path that exists — but the mechanism it's wired into differs by direction, as above.

This is a **separate concern from the delivery guarantees around it**, not a restatement of them:
* Outbox and broker consumption (`09_architecture.md` §5) guarantee *delivery* — that a published event isn't lost, and that at-least-once redelivery is eventually handled.
* Schema validation (this document) guarantees *shape* — that whatever is delivered actually conforms to its contract.

A message can be reliably delivered and still be malformed; both guarantees are needed, and neither substitutes for the other.

---

## 4. Where schemas live

One top-level directory, sibling to `src/`, `doc/`, `adr/`:

```
contracts/
  pizzeria.resource-management.table-added.v1.schema.json
  pizzeria.resource-management.table-capacity-changed.v1.schema.json
  pizzeria.guest-service.order-sent-to-kitchen.v1.schema.json
  pizzeria.kitchen.order-ready-for-pickup.v1.schema.json
  ...
```

**Naming: `pizzeria.<bounded-context>.<event-name>.v<N>.schema.json`**, every segment lowercase kebab-case. This is deliberately **not** the PHP class name of the domain event behind it (`TableAdded` the class vs. `pizzeria.resource-management.table-added` the wire identifier) — §1 already commits to a broker-agnostic, language-agnostic format, and a filename that's really a PHP class name in disguise would quietly undo that the moment any context gets rewritten in something else. The `<bounded-context>` segment is the kebab-case form of the context names already used throughout `07_define_context_map.md` (`guest-service`, `kitchen`, `resource-management`, `pizzeria-lifecycle`) and identifies the *publishing* context — useful on its own, since a flat directory would otherwise give no indication of which context owns a given event. `pizzeria` is a fixed root namespace (the product itself); it's there mainly so the identifier reads as a self-contained wire name, not because multiple products are anticipated.

One canonical schema file per integration event, per version. It lives outside every context's own `src/<Context>/` tree — but that's a statement about *where the file sits*, not about *who's authoritative over its content*. Authority is simple and always well-defined: **whoever publishes an event owns that event's schema.** For most relationships in this system that coincides with the Upstream, since the Open Host Service + Published Language pattern (`07_define_context_map.md` §5) makes the Upstream exactly the context broadcasting its own model as events. The one relationship where it doesn't automatically coincide is Kitchen ⇄ Guest Service — a Customer-Supplier relationship (`07_define_context_map.md` §5) where Kitchen stays the Supplier/Upstream throughout, yet Guest Service is the one publishing `OrderSentToKitchen` (`07_define_context_map.md` §3). The publisher-owns-the-schema rule still holds there without needing a special case: Guest Service owns `OrderSentToKitchen`'s schema, Kitchen owns `OrderAccepted`'s and `OrderReadyForPickup`'s — ownership tracks who publishes each individual event, not the relationship-level Upstream label, which is exactly the distinction `07_define_context_map.md` §2 already draws ("upstream/downstream describes whose model is authoritative... not which way any individual event flows"). What living outside `src/<Context>/` avoids is a narrower, physical problem: nesting the schema inside, say, `ResourceManagement/` would make every consumer reach into another context's own module tree just to validate a payload — the same kind of cross-context filesystem coupling `09_architecture.md` §1 already rules out for everything else. A neutral, shared directory keeps that physical-layout question separate from the ownership question, which the rule above already answers on its own.

A flat, filesystem-based `contracts/` directory is a **starting point, not the destination.** It has no way to *enforce* a publisher's authority over its own schema — nothing stops a contributor from another context editing a schema it doesn't own; ownership here is a convention, not something the tooling checks. That's an accepted gap for now, same trade-off as the "convention only" boundary enforcement in `09_architecture.md` §1–§2. Some more structural mechanism will eventually be worth it — §5 already leaves room for swapping the validator's adapter without touching any caller, and §7's Open Questions defer exactly when that becomes worth building; which mechanism that ends up being isn't decided here.

---

## 5. How contexts use it: a `SchemaValidator` service

Each context depends on a `SchemaValidatorInterface`, not on the filesystem directly:

```php
interface SchemaValidatorInterface
{
    /** @throws SchemaValidationException */
    public function validate(string $eventType, string $schemaVersion, array $payload): void;
}
```

* Defined once, alongside the other technical-only plumbing in `Shared/` (`09_architecture.md` §2) — it carries no domain behaviour or domain language, same justification as everything else that lives there.
* `$eventType` is the wire identifier from §4 (`pizzeria.<bounded-context>.<event-name>`), not a PHP class name. Since the two no longer coincide, each context needs an explicit mapping from its domain event classes to their wire identifiers (a constant on the event, a lookup table at the outbox boundary — exact shape not decided here). The one firm rule: that mapping is explicit, never inferred from the class name via reflection, or the wire identifier would just be a disguised class name again.
* **Today's implementation** is a thin adapter: read `contracts/<eventType>.v<schemaVersion>.schema.json`, validate the payload against it with a JSON Schema validation library (specific library choice deferred to implementation — not an architectural decision).
* **Tomorrow's implementation** could call a real Schema Registry or a small internal API instead, without any caller changing — only the adapter behind `SchemaValidatorInterface` changes, which is exactly the Clean Architecture dependency rule already in force (`09_architecture.md` §2). The interface is deliberately shaped so that swap is invisible to every context using it.

---

## 6. Schema compatibility & versioning policy

**Every schema version change must either stay backward-compatible, or ship through Expand and Contract — never as a breaking change made in place.**

* **Backward-compatible changes** (adding an optional field, widening an enum, relaxing a constraint) can bump the schema version freely. A consumer still validating against the previous version keeps working unmodified, since nothing it depends on changed shape.
* **Breaking changes** (removing/renaming a field, narrowing a type, changing semantics) are never allowed to replace a schema version in place. They go through the three-step **Expand and Contract** pattern instead:
  1. **Expand** — publish the new version (`vN+1`) alongside the old one (`vN`); the publisher emits a payload shaped to satisfy both schemas at once, for the duration of the migration.
  2. **Migrate** — each consumer switches to validating against `vN+1` independently, at its own pace — no coordinated deployment across contexts, consistent with the Distributed Monolith's independently-deployed processes (`09_architecture.md` §3).
  3. **Contract** — once every consumer has migrated, the publisher stops emitting the `vN`-shaped payload. `vN`'s schema file itself stays in `contracts/` — it's a historical record of what was once on the wire, not something ongoing publication depends on.

This is what makes the publisher-owns-the-schema rule from §4 actually workable under independent deployment: the publisher still decides when its own version is safe to retire, but can't force every consumer to migrate in lockstep — Expand and Contract is what makes that migration safe without coordination.

---

## 7. Open Questions

* ~~Message envelope shape.~~ **Resolved, see §2.** The detailed message-flow diagram this was deferred to (`05_connect_message_flows.md`) is now finished and reviewed, same as the broker choice in `09_architecture.md` §5 this was tied to: `eventId`, `eventType`, `schemaVersion`, `occurredAt`, `correlationId` (with a propagation rule), and `payload`. Distributed-tracing metadata was considered and deliberately left out — no APM/tracing backend exists here to justify it.
* **How "every consumer has migrated" gets confirmed.** §6's Contract step assumes the publisher can tell when it's safe to retire `vN` — but nothing here yet says *how* it knows every consumer has moved to `vN+1` (a manual checklist, a registry of active consumers per schema version, usage metrics on the old file). Deferred until Expand and Contract is actually exercised for a real breaking change.
* **Stronger contract-ownership enforcement.** §4 accepts, for now, that nothing stops a contributor from another context editing a schema it doesn't own — a real Schema Registry is one way to close that gap, a small internal API is another, and neither is chosen here. Not needed now (§5) — revisit once the local-file approach becomes a real bottleneck (e.g. ownership needs to be enforced by tooling, not convention, or schemas need to be discoverable/queryable at runtime rather than just read from disk).
