# Agent instructions — pizzeria

## Project overview

Pizzeria is an architectural sandbox demonstrating Domain-Driven Design end to end: an interactive simulation of a restaurant serving pizzas, where a Web GUI lets a user take on the roles of guests, a waiter, a chef, a host, and a manager, and observe the system's behaviour from the perspective of the whole pizzeria. The goal is to demonstrate the full process of domain discovery, strategic and tactical modelling, and then implementation using patterns such as Saga, Process Manager, CQRS, and CQS. The intended architecture (see `doc/09_architecture.md`) is a "Distributed Monolith": one Symfony codebase, four Bounded Contexts (Guest Service, Kitchen, Resource Management, Pizzeria Lifecycle), each deployed as several independent processes (HTTP, broker consumers, outbox relay), communicating only through published domain events — never a live cross-context query.

**Current stage: documentation-only.** As of this writing there is no `src/`, no `package.json`/`Makefile`/build tooling, and no PHP code — the repository holds the DDD modelling process (steps 1–8, all reviewed and accepted) plus a draft of step 9 (Architecture). Implementation has not started. Update this file's routing table once the Symfony scaffold described in `doc/09_architecture.md` exists.

## Task-routing table

| When the task involves… | Read first | Key rules |
|---|---|---|
| The overall DDD modelling roadmap, or figuring out what stage the project is at | `doc/README.md` | Tracks all 8 DDD Starter Modelling Process steps plus the Architecture extension (step 9), each with a status symbol (⏸/⏳/✅/⚠️). Don't mark a step ✅ until it's been explicitly reviewed and accepted, not just drafted. |
| Domain modelling docs — vision, EventStorming, sub-domains, context map, Bounded Context Canvases, aggregates/entities/value objects/domain services/integration events/read models per context | `doc/01_understand.md` through `doc/08_*.md` | Every produced document lives under `doc/`, prefixed with the step number it belongs to. Filenames for step 8 follow `08_<context>_<artifact>.md` for one of the four Bounded Contexts (`guest_service`, `kitchen`, `resource_management`, `pizzeria_lifecycle`). If a concept expected from the methodology appears missing, treat it as a question to resolve — justify the omission in writing or fix the gap. |
| Implementation architecture — layering, deployment topology, persistence, inter-context communication | `doc/09_architecture.md` | Distributed Monolith style (§1): every cross-context relationship is event-driven replication, never a live query. One top-level directory per Bounded Context (`Domain/`, `Application/`, `Infrastructure/`, `UI/`), Clean Architecture dependency rule pointing inward. `Shared/` is technical plumbing only, never domain behaviour, and only `Infrastructure/`/`UI/` may reference it. Several Open Questions (§6 — broker choice, idempotent consumption, specific HTTP runtime) are explicitly deferred; don't resolve them here, follow the pointers in that section instead. |
| Integration event schemas/contracts crossing Bounded Context boundaries | `doc/09_integration_contracts.md` | Companion to `09_architecture.md` — JSON Schema and validation rules for cross-context events. |
| Short terminology or design clarifications that came up mid-process | `doc/design_notes/README.md`, `doc/design_notes/dn_*.md` | ADR-style but lightweight — one file per note (`dn_NNNN.md`), for things worth keeping so later steps don't re-derive them from scratch. Not a substitute for a full ADR. |
| Formal architecture decisions | `adr/README.md` | Directory exists but is currently empty — no ADRs have been recorded yet. |
| Project framing, sources, high-level "what is this" | `README.md` | One-paragraph project description, links to the DDD Starter Modelling Process and related ddd-crew techniques used throughout `doc/`. |
| Future implementation code (Symfony/PHP, once it exists) | *(no `src/` yet)* | Add a routing row here once the scaffold from `doc/09_architecture.md` §2 (`src/<BoundedContext>/{Domain,Application,Infrastructure,UI}/`) is created. |

## Validation

No validation commands are configured yet (`.ai/agentic.config.json` → `validation.commands` is empty) — there is no code to typecheck, test, or build. Update both the config and `SDLC.md`'s Validation gate section together once implementation begins.

## Process and configuration

- `SDLC.md` — the full ticket-to-merge process, label state machine, and claim protocol.
- `.ai/agentic.config.json` — the machine-readable pipeline configuration this file and `SDLC.md` are derived from.
