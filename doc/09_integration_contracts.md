# 09. Integration Contracts

**Part of:** [Architecture (Clean Architecture)](README.md#architecture-clean-architecture) — this step goes beyond the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process) and is its natural extension towards implementation.

**Purpose:** define how the *shape* of every integration event crossing a Bounded Context boundary is described, shared, and validated. `07_define_context_map.md` §5 already names **Published Language** as the pattern behind almost every relationship in this system — this document is that pattern's technical realization: a schema per event, and an enforced validation step on both ends of the wire. It complements `09_architecture.md` §5–§6 (outbox on publish, and whatever deduplication broker consumption ends up needing on receipt — delivery guarantees generally) without repeating it — this document is about the *shape* of what's delivered, not the delivery mechanism itself.

**Key question:** *what does an integration event look like on the wire, where does that definition live, and what stops a malformed one from ever being sent or accepted?*

---

## 1. Format: JSON + JSON Schema

Considered alongside Avro and Protobuf; JSON + JSON Schema was chosen as the most universal option:

* **Broker-agnostic.** `09_architecture.md` §6 leaves the broker choice (Kafka, RabbitMQ, or both) open. Avro is idiomatically tied to a Kafka-centric ecosystem (Confluent Schema Registry); committing to it now would silently pre-decide the broker question. JSON carries no such coupling.
* **No compile step.** Protobuf and Avro both generate code from a schema definition, which gives structural correctness "for free" but adds a build step and, for Avro particularly, thinner PHP tooling. JSON requires an explicit validation step regardless — which is exactly what's being required here anyway (§2), so there's no lost safety by not code-generating.
* **Human-readable**, which matters for a solo project where the person debugging a bad message and the person who defined its schema are the same person.

Protobuf remains a reasonable option to revisit later if message size/throughput ever becomes a real constraint — nothing here rules it out permanently, it's just not the starting choice.

---

## 2. Validation rule

**Every integration event is schema-validated twice: once before it is sent, once after it is received.** This is a hard rule, not a convention left to each context to remember:

* **On publish**, validation is wired into the outbox (`09_architecture.md` §5) — an event can't be written to the outbox, and therefore can never reach the broker, without passing validation first.
* **On receipt**, validation is wired into the broker consumer that processes inbound integration events. Whether that consumer also deduplicates via an inbox-shaped idempotency table is a separate, still-open question (`09_architecture.md` §6) tied to the broker choice — schema validation happens regardless of how (or whether) that deduplication ends up being implemented. Note this is a different Inbox usage than the one confirmed in `09_architecture.md` §5: that one deduplicates synchronous HTTP requests from the frontend/user, not context-to-context traffic, since no context ever calls another context over HTTP — contexts only reach each other through the outbox/broker. The two would just happen to share the same technique, not the same code path.

So publishing or consuming an integration event without going through validation isn't a code path that exists — but the mechanism it's wired into differs by direction, as above.

This is a **separate concern from the delivery guarantees around it**, not a restatement of them:
* Outbox (`09_architecture.md` §5) and broker consumption (`09_architecture.md` §6) guarantee *delivery* — that a published event isn't lost, and that at-least-once redelivery is eventually handled.
* Schema validation (this document) guarantees *shape* — that whatever is delivered actually conforms to its contract.

A message can be reliably delivered and still be malformed; both guarantees are needed, and neither substitutes for the other.

---

## 3. Where schemas live

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

A flat, filesystem-based `contracts/` directory is a **starting point, not the destination.** It has no way to *enforce* a publisher's authority over its own schema — nothing stops a contributor from another context editing a schema it doesn't own; ownership here is a convention, not something the tooling checks. That's an accepted gap for now, same trade-off as the "convention only" boundary enforcement in `09_architecture.md` §1–§2. Some more structural mechanism will eventually be worth it — §4 already leaves room for swapping the validator's adapter without touching any caller, and §6's Open Questions defer exactly when that becomes worth building; which mechanism that ends up being isn't decided here.

---

## 4. How contexts use it: a `SchemaValidator` service

Each context depends on a `SchemaValidatorInterface`, not on the filesystem directly:

```php
interface SchemaValidatorInterface
{
    /** @throws SchemaValidationException */
    public function validate(string $eventType, string $schemaVersion, array $payload): void;
}
```

* Defined once, alongside the other technical-only plumbing in `Shared/` (`09_architecture.md` §2) — it carries no domain behaviour or domain language, same justification as everything else that lives there.
* `$eventType` is the wire identifier from §3 (`pizzeria.<bounded-context>.<event-name>`), not a PHP class name. Since the two no longer coincide, each context needs an explicit mapping from its domain event classes to their wire identifiers (a constant on the event, a lookup table at the outbox boundary — exact shape not decided here). The one firm rule: that mapping is explicit, never inferred from the class name via reflection, or the wire identifier would just be a disguised class name again.
* **Today's implementation** is a thin adapter: read `contracts/<eventType>.v<schemaVersion>.schema.json`, validate the payload against it with a JSON Schema validation library (specific library choice deferred to implementation — not an architectural decision).
* **Tomorrow's implementation** could call a real Schema Registry or a small internal API instead, without any caller changing — only the adapter behind `SchemaValidatorInterface` changes, which is exactly the Clean Architecture dependency rule already in force (`09_architecture.md` §2). The interface is deliberately shaped so that swap is invisible to every context using it.

---

## 5. Schema compatibility & versioning policy

**Every schema version change must either stay backward-compatible, or ship through Expand and Contract — never as a breaking change made in place.**

* **Backward-compatible changes** (adding an optional field, widening an enum, relaxing a constraint) can bump the schema version freely. A consumer still validating against the previous version keeps working unmodified, since nothing it depends on changed shape.
* **Breaking changes** (removing/renaming a field, narrowing a type, changing semantics) are never allowed to replace a schema version in place. They go through the three-step **Expand and Contract** pattern instead:
  1. **Expand** — publish the new version (`vN+1`) alongside the old one (`vN`); the publisher emits a payload shaped to satisfy both schemas at once, for the duration of the migration.
  2. **Migrate** — each consumer switches to validating against `vN+1` independently, at its own pace — no coordinated deployment across contexts, consistent with the Distributed Monolith's independently-deployed processes (`09_architecture.md` §3).
  3. **Contract** — once every consumer has migrated, the publisher stops emitting the `vN`-shaped payload. `vN`'s schema file itself stays in `contracts/` — it's a historical record of what was once on the wire, not something ongoing publication depends on.

This is what makes the publisher-owns-the-schema rule from §3 actually workable under independent deployment: the publisher still decides when its own version is safe to retire, but can't force every consumer to migrate in lockstep — Expand and Contract is what makes that migration safe without coordination.

---

## 6. Open Questions

* **Message envelope shape.** This document assumes every integration event is identifiable by an `(eventType, schemaVersion)` pair, but the exact envelope (correlation IDs, timestamps, tracing metadata alongside the payload) isn't specified yet — deferred to the detailed cross-context message-flow diagram, same as the broker choice in `09_architecture.md` §6.
* **How "every consumer has migrated" gets confirmed.** §5's Contract step assumes the publisher can tell when it's safe to retire `vN` — but nothing here yet says *how* it knows every consumer has moved to `vN+1` (a manual checklist, a registry of active consumers per schema version, usage metrics on the old file). Deferred until Expand and Contract is actually exercised for a real breaking change.
* **Stronger contract-ownership enforcement.** §3 accepts, for now, that nothing stops a contributor from another context editing a schema it doesn't own — a real Schema Registry is one way to close that gap, a small internal API is another, and neither is chosen here. Not needed now (§4) — revisit once the local-file approach becomes a real bottleneck (e.g. ownership needs to be enforced by tooling, not convention, or schemas need to be discoverable/queryable at runtime rather than just read from disk).
