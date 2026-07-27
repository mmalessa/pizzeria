# 09. Integration Contracts

**Part of:** [Architecture (Clean Architecture)](README.md#architecture-clean-architecture) — this step goes beyond the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process) and is its natural extension towards implementation.

**Purpose:** define how the *shape* of every integration event crossing a Bounded Context boundary is described, shared, and validated. `07_define_context_map.md` §5 already names **Published Language** as the pattern behind almost every relationship in this system — this document is that pattern's technical realization: a schema per event, and an enforced validation step on both ends of the wire. It complements `09_architecture.md` §5–§6 (outbox/inbox, delivery guarantees) without repeating it — this document is about the *shape* of what's delivered, not the delivery mechanism itself.

**Key question:** *what does an integration event look like on the wire, where does that definition live, and what stops a malformed one from ever being sent or accepted?*

---

## 1. Format: JSON + JSON Schema

Considered alongside Avro and Protobuf; JSON + JSON Schema was chosen as the most universal option:

* **Broker-agnostic.** `09_architecture.md` §7 leaves the broker choice (Kafka, RabbitMQ, or both) open. Avro is idiomatically tied to a Kafka-centric ecosystem (Confluent Schema Registry); committing to it now would silently pre-decide the broker question. JSON carries no such coupling.
* **No compile step.** Protobuf and Avro both generate code from a schema definition, which gives structural correctness "for free" but adds a build step and, for Avro particularly, thinner PHP tooling. JSON requires an explicit validation step regardless — which is exactly what's being required here anyway (§2), so there's no lost safety by not code-generating.
* **Human-readable**, which matters for a solo project where the person debugging a bad message and the person who defined its schema are the same person.

Protobuf remains a reasonable option to revisit later if message size/throughput ever becomes a real constraint — nothing here rules it out permanently, it's just not the starting choice.

---

## 2. Validation rule

**Every integration event is schema-validated twice: once before it is sent, once after it is received.** This is a hard rule, not a convention left to each context to remember — it is wired into the shared outbox/inbox infrastructure from `09_architecture.md` §5, so publishing or consuming an integration event without going through validation isn't a code path that exists.

This is a **separate concern from the outbox/inbox pattern**, not a restatement of it:
* Outbox/inbox (`09_architecture.md` §5) guarantees *delivery* — that a published event isn't lost, and that a retried HTTP request isn't double-processed.
* Schema validation (this document) guarantees *shape* — that whatever is delivered actually conforms to its contract.

A message can be reliably delivered and still be malformed; both guarantees are needed, and neither substitutes for the other.

---

## 3. Where schemas live

One top-level directory, sibling to `src/`, `doc/`, `adr/`:

```
contracts/
  TableAdded.v1.schema.json
  TableCapacityChanged.v1.schema.json
  OrderSentToKitchen.v1.schema.json
  OrderReadyForPickup.v1.schema.json
  ...
```

One canonical schema file per integration event, per version — `<EventType>.v<N>.schema.json`. This lives outside every context's own `src/<Context>/` tree, since a schema belongs to neither the publisher nor any one consumer alone: per the Open Host Service + Published Language pattern (`07_define_context_map.md` §5), one publisher and multiple independent consumers all validate against the exact same file. Putting it inside, say, `ResourceManagement/` would misrepresent it as something Resource Management owns and could change unilaterally.

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
* **Today's implementation** is a thin adapter: read `contracts/<eventType>.v<schemaVersion>.schema.json`, validate the payload against it with a JSON Schema validation library (specific library choice deferred to implementation — not an architectural decision).
* **Tomorrow's implementation** could call a real Schema Registry or a small internal API instead, without any caller changing — only the adapter behind `SchemaValidatorInterface` changes, which is exactly the Clean Architecture dependency rule already in force (`09_architecture.md` §2). The interface is deliberately shaped so that swap is invisible to every context using it.

---

## 5. Open Questions

* **Message envelope shape.** This document assumes every integration event is identifiable by an `(eventType, schemaVersion)` pair, but the exact envelope (correlation IDs, timestamps, tracing metadata alongside the payload) isn't specified yet — deferred to the detailed cross-context message-flow diagram, same as the broker choice in `09_architecture.md` §7.
* **Schema compatibility / versioning policy.** Nothing here yet says whether a new schema version must stay backward-compatible with consumers still running the old one, or whether version bumps require coordinated deployment across contexts. Given the Distributed Monolith's independently-deployed processes (`09_architecture.md` §3), an uncoordinated breaking change could break a consumer mid-flight — this needs an explicit policy, not an assumption, once real schema evolution actually comes up.
* **Schema Registry timing.** Not needed now (§4) — revisit once the local-file approach becomes a real bottleneck (e.g. schemas need to be discoverable/queryable at runtime, not just read from disk).
