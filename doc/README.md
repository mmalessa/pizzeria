# Pizzeria — DDD Modelling Roadmap

This document tracks the domain-modelling process for the (simplified) Pizzeria domain. It follows the **[DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process)** by the DDD Crew — a lightweight guide of 8 activities that take a team from business understanding to running code.

All project content (docs, code, comments) is written in **English** from this point forward.

## Process overview

The DDD Starter Modelling Process defines 8 activities: **Understand → Discover → Decompose → Strategize → Connect → Organise → Define → Code**.

The upstream process is explicitly **non-linear** — activities are meant to be revisited as understanding deepens, not completed once and forgotten. For the purpose of *tracking progress* in this repository we still list them in a numbered, roughly sequential order below, but we may loop back to an earlier step whenever a later one surfaces new information (e.g. discovering a missing sub-domain while designing an aggregate).

Since this is a **solo modelling exercise** (one person playing every role, no real organisation behind it), some activities — most notably *Organise* — are kept intentionally light. This is called out explicitly in the relevant section rather than silently skipped.

## Status legend

| Symbol | Meaning |
|---|---|
| ⏸ | Not started |
| ⏳ | Drafted — written, not yet reviewed |
| ✅ | Reviewed and accepted |
| ⚠️ | Substantially complete, but has open questions left to resolve |

## Working agreement

* Don't consider a step "done" (✅) until it has been explicitly reviewed and accepted — not just drafted.
* Prefer finishing a step before starting the next one, but explicitly revisit earlier steps (and update their status/content) when later work reveals gaps or contradictions.
* Every produced document lives under `doc/`, prefixed with the step number it belongs to.
* If a concept expected from the methodology (a role, an event, a pattern) appears to be missing from a doc, treat it as a question to resolve — either justify the omission in writing, or fix the gap.

---

## 1. Understand ✅

**Purpose:** align the modelling effort with the business model, user needs, and strategic goals, so that later architecture/design decisions can be traced back to a business reason.

**Key question:** *why does this business exist, and for whom?*

**Techniques (candidates):**
* Business Model Canvas
* Impact Mapping / Product Strategy Canvas *(likely skipped or lightly touched — no real stakeholders/strategy to interview for a sandbox project)*

**Planned artifacts:**
* `01_understand.md` — project vision, business goals, intended users/actors ✅

---

## 2. Discover ✅

**Purpose:** collaboratively and visually explore domain knowledge — what actually happens in the business, in the language of the people who do it.

**Key question:** *what events and processes make up the domain?*

**Techniques (candidates):**
* EventStorming — Big Picture level (broad sweep of domain events, actors, timeline)
* EventStorming — Process level (zooming into individual processes: commands, policies, read models)

**Planned artifacts:**
* `02_discover_big_picture.md` — domain events, timeline, rough actor/process groupings ✅
* `02_discover_process_level.md` — one section per process, with commands/events/policies ✅

---

## 3. Decompose ✅

**Purpose:** break the domain into loosely-coupled sub-domains, to reduce cognitive load and clarify ownership boundaries.

**Key question:** *where are the natural seams in this domain?*

**Techniques (candidates):**
* Grouping Big-Picture events into candidate sub-domains
* Context Mapping (first pass)
* Design heuristics (linguistic boundaries, autonomy, etc.)

**Planned artifacts:**
* `03_decompose_subdomains.md` — candidate sub-domains and the reasoning behind each boundary ✅

---

## 4. Strategize ✅

**Purpose:** identify which sub-domains represent the greatest business differentiation, to prioritise modelling/implementation effort accordingly.

**Key question:** *which parts of this domain matter most, and which are just necessary plumbing?*

**Techniques (candidates):**
* Core Domain Chart (Core / Supporting / Generic classification, plotted against complexity)

**Planned artifacts:**
* `04_strategize_core_domain_chart.md` — classification of each sub-domain (Core, Supporting, Generic) with justification ✅

---

## 5. Connect ✅

**Purpose:** design how sub-domains collaborate to fulfil end-to-end business use-cases.

**Key question:** *how does a business scenario flow across sub-domain boundaries?*

**Techniques (candidates):**
* Domain Message Flow Modelling (commands/events/queries flowing between contexts for a given scenario)

**Planned artifacts:**
* `05_connect_message_flows.md` — one message flow diagram + narrative per key cross-context scenario ✅

---

## 6. Organise ✅

**Purpose:** in a real setting, form autonomous teams aligned with context boundaries (Team Topologies, Conway's Law).

**Simplification note:** this is a **solo project with no organisation behind it** — there are no teams to align. This step is kept as a placeholder to record *conceptually* which team would plausibly own each bounded context (useful context for future "as if this were a real org" discussions), rather than a real team-design exercise.

**Planned artifacts:**
* `06_organise.md` — brief note on hypothetical team ownership per bounded context ✅

---

## 7. Define ✅

**Purpose:** specify the responsibilities, boundaries, and language of each bounded context precisely, before committing to internal design.

**Key question:** *what is this context responsible for, and how does it talk to the rest of the world?*

**Techniques (candidates):**
* Bounded Context Canvas (name, purpose, strategic classification incl. evolution, domain roles, inbound/outbound communication, ubiquitous language, business decisions, assumptions, open questions)
* Context Map (updated/finalised)

**Planned artifacts:**
* `07_define_context_map.md` — finalised context map with relationship patterns (OHS, ACL, Partnership, etc.) ✅
* `07_define_guest_service.md` — Bounded Context Canvas ✅
* `07_define_kitchen.md` — Bounded Context Canvas ✅
* `07_define_resource_management.md` — Bounded Context Canvas ✅
* `07_define_pizzeria_lifecycle.md` — Bounded Context Canvas ✅

---

## 8. Code ⏳

**Purpose:** design the tactical (and read-side) model for each bounded context, close enough to code to actually implement it.

**Key question:** *what are the aggregates, entities, value objects, services, and integration points inside this context?*

**Techniques (candidates):**
* Aggregate Design Canvas
* Design-level EventStorming
* Event Modeling

**Planned artifacts** — one set per Bounded Context (`07_define_context_map.md` §1: Guest Service, Kitchen, Resource Management, Pizzeria Lifecycle), filenames `08_<context>_<artifact>.md`. Starting with Guest Service (Core Domain, `04` §4's "natural first candidate"), one artifact type at a time, in this order:
* `08_<context>_domain_model.md` — overview of aggregates and how they relate
* `08_<context>_aggregates.md` — aggregate boundaries, invariants, consistency rules
* `08_<context>_entities.md` / `08_<context>_value_objects.md` — supporting building blocks
* `08_<context>_domain_services.md` — cross-aggregate domain logic
* `08_<context>_integration_events.md` — events published across context boundaries
* `08_<context>_read_models.md` — read-side design

**Progress:**
* `08_guest_service_domain_model.md` ⏳
* `08_guest_service_aggregates.md` ⏳
* `08_guest_service_entities.md` ⏳
* `08_guest_service_value_objects.md` ⏳
* `08_guest_service_domain_services.md` ⏳
* `08_guest_service_integration_events.md` ⏳
* `08_guest_service_read_models.md` ⏳ — Guest Service complete, pending review
* `08_kitchen_domain_model.md` ⏳
* `08_kitchen_aggregates.md` ⏳
* `08_kitchen_entities.md` ⏳
* `08_kitchen_value_objects.md` ⏳
* `08_kitchen_domain_services.md` ⏳
* `08_kitchen_integration_events.md` ⏳
* `08_kitchen_read_models.md` ⏳ — Kitchen complete, pending review
* Resource Management, Pizzeria Lifecycle — not started

---

## Design Notes

Short, ADR-style records of terminology and design decisions that came up while working through the process — not full architecture decisions, but clarifications worth keeping so later steps (and later readers) don't have to re-derive them from scratch.

### DN-1: Domain event vs. integration event — where's the boundary?

**Raised:** while writing `08_guest_service_domain_model.md` §4, which uses `OrderPlaced` as a domain event coordinating two aggregates (`GuestGroup`/`Bill` and `Order`) inside Guest Service.

**Question:** is a "domain event" scoped to *the whole problem domain* (potentially crossing Bounded Contexts) or to *one Bounded Context's model*? The two readings disagree on whether an event that crosses a BC boundary could still correctly be called a "domain event."

**Decision:** in this project, a **domain event** is raised by an aggregate and consumed only within the Bounded Context that raised it — used purely for internal aggregate-to-aggregate coordination (e.g. `OrderPlaced` updating `Bill`'s running total inside Guest Service), never published to another context. An **integration event** is deliberately designed and published as a stable contract for other Bounded Contexts to consume — the only channel contexts use to talk to each other (`05_connect_message_flows.md`, `07_define_context_map.md`). Formal definition now lives in `01_understand.md` §4.

**Rationale:**
* Eric Evans' original (2003) text doesn't formally distinguish "domain event" from "integration event" at all — that split was popularized later, by Vaughn Vernon's *Implementing Domain-Driven Design* and widely-adopted microservices/event-driven practice (e.g. Microsoft's eShopOnContainers reference architecture). Evans' "domain event" is about being domain-significant and expressed in ubiquitous language — it carries no built-in rule about how far the event is allowed to be published.
* The "doesn't leave the domain" reading (this project's original, looser phrasing in `01` before this note) is also defensible, if "domain" is read as the whole problem space rather than one specific Bounded Context's model. Genuinely ambiguous terminology, not a mistake on either side.
* This project already draws its practical "crossing a boundary" line at the Bounded Context everywhere else — `05_connect_message_flows.md` §0's entire integration-events table, and `07_define_context_map.md`'s relationship patterns, are both organised around the BC boundary specifically. Adopting the same boundary for "domain event" keeps one consistent meaning of "crossing a boundary" across every document in this series, instead of introducing a second, looser boundary just for this one term.

**Status:** Accepted, 2026-07-24.

### DN-2: Denormalised accumulators vs. idempotent, keyed tracking, for cross-aggregate consistency

**Raised:** while reviewing `08_kitchen_aggregates.md`'s original design for `KitchenOrder` — a `readyCount: int`, incremented every time a `PizzaTask` completed, used to decide when to raise `MarkOrderReady`. The same question was then raised again, independently, against `08_guest_service_aggregates.md`'s original `Bill.runningTotal` — a `Money` field incremented on every `OrderPlaced`.

**Question:** is it safe for one aggregate to track a derived fact — a count of related things done, or a running sum — as a value that's directly mutated (`+=`) by domain events raised by another aggregate?

**Decision:** no — not when the event delivery mechanism is at-least-once (the assumption everywhere in this project, `05_connect_message_flows.md` §0). Any accumulator fed by `+=` double-counts if the same event is redelivered — whether it's counting items (Kitchen's `readyCount`) or summing money (Guest Service's `runningTotal`, a real financial-correctness bug: a redelivered `OrderPlaced` would overcharge the guest). Instead, track contributions **keyed by the source event's ID** — a set of completed item IDs (Kitchen's `pizzaTaskId`s), or a map of `orderId → lines` (Guest Service's Bill Summary) — in a small local read model, and derive the count or sum from that each time. Re-processing the same ID is then a no-op (a set doesn't grow, a map entry is just overwritten with the same value), so it's safe under redelivery by construction. Neither `KitchenOrder` nor `Bill` holds the derived value directly anymore — `KitchenOrder` keeps only `totalPizzaCount` (write-once, safe on its own) and checks the **Order Progress** read model (`08_kitchen_read_models.md`); `Bill` keeps no total at all and checks **Bill Summary** (`08_guest_service_read_models.md`).

**Rationale:**
* This is the same "replicate into your own copy, don't reach into another aggregate live" discipline already used everywhere in this series (`05` §0 across Bounded Contexts; `08_guest_service_domain_model.md` §4's `Order Delivery Status` across aggregates within one context) — applied to *how* that local copy should be updated, not just *whether* to keep one.
* The general rule going forward: whenever a cross-aggregate consistency rule in this series needs a derived count or sum over events raised by another aggregate, model it as a read model tracking entries *keyed by the source ID*, never a bare counter or running total mutated by `+=` — even though the latter looks simpler at first glance. Apply this by default in Resource Management's and Pizzeria Lifecycle's tactical design, not just Kitchen's and Guest Service's.

**Status:** Accepted, 2026-07-24.

---

## Sources

* [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process)
* [EventStorming](https://www.eventstorming.com/)
* [Bounded Context Canvas](https://github.com/ddd-crew/bounded-context-canvas)
* [Context Mapping](https://github.com/ddd-crew/context-mapping)
* [Core Domain Charts](https://github.com/ddd-crew/core-domain-charts)
* [Aggregate Design Canvas](https://github.com/ddd-crew/aggregate-design-canvas)
* [Domain Message Flow Modelling](https://github.com/ddd-crew/domain-message-flow-modelling)
