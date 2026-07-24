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

## 8. Code ⏸

**Purpose:** design the tactical (and read-side) model for each bounded context, close enough to code to actually implement it.

**Key question:** *what are the aggregates, entities, value objects, services, and integration points inside this context?*

**Techniques (candidates):**
* Aggregate Design Canvas
* Design-level EventStorming
* Event Modeling

**Planned artifacts** *(per bounded context, exact filenames TBD once contexts exist)*:
* `08_domain_model.md` — overview of aggregates and how they relate ⏸
* `08_aggregates.md` — aggregate boundaries, invariants, consistency rules ⏸
* `08_entities.md` / `08_value_objects.md` — supporting building blocks ⏸
* `08_domain_services.md` — cross-aggregate domain logic ⏸
* `08_integration_events.md` — events published across context boundaries ⏸
* `08_read_models.md` / `08_projections.md` / `08_queries.md` — read-side design ⏸

---

## Sources

* [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process)
* [EventStorming](https://www.eventstorming.com/)
* [Bounded Context Canvas](https://github.com/ddd-crew/bounded-context-canvas)
* [Context Mapping](https://github.com/ddd-crew/context-mapping)
* [Core Domain Charts](https://github.com/ddd-crew/core-domain-charts)
* [Aggregate Design Canvas](https://github.com/ddd-crew/aggregate-design-canvas)
* [Domain Message Flow Modelling](https://github.com/ddd-crew/domain-message-flow-modelling)
