# 07. Define — Bounded Context Canvas: Pizzeria Lifecycle

Part of step 7 (*Define*) — see `07_define_context_map.md` for how this context relates to the other three.

---

## Name

Pizzeria Lifecycle

## Purpose

Own the single, authoritative `Open` / `Closing` / `Closed` status of the whole pizzeria, and the readiness/shutdown rules gating those transitions. The one piece of state every other context depends on but none of them decides (`03_decompose_subdomains.md`).

## Strategic Classification

* **Domain:** Supporting (`04_strategize_core_domain_chart.md` §1) — small internal state machine, but its guard conditions reach into nearly every other context; the complexity is in its *coupling*, not its own logic (`04` §3).
* **Evolution:** Custom-built — simple state shape, but the readiness/shutdown rules are specific to this domain, not off-the-shelf.

## Domain Roles

A small state-machine aggregate (`Pizzeria`: `Open`/`Closing`/`Closed`) plus two locally-replicated read models (Readiness, Active Visits Count) that exist purely to let the state-machine's guards evaluate without ever querying another context live (`05_connect_message_flows.md` §0).

## Inbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Resource Management | Resource Management is upstream for *readiness facts* only — Pizzeria Lifecycle stays upstream for `Open`/`Closing`/`Closed` itself (`07_define_context_map.md` §2) | `TableAssignedToWaiter`, `TableUnassignedFromWaiter`, `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `ChefHired`, `ChefTerminationStarted`, `ChefTerminated` |
| Guest Service | Guest Service is upstream for *visit-count facts* only (`07_define_context_map.md` §2) | `GuestGroupSeated`, `GuestGroupLeft` |

## Outbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Guest Service | Open Host Service + Published Language | `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed` |
| Resource Management | Open Host Service + Published Language — same published status, second consumer | `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed` |

## Ubiquitous Language

* **`Open` / `Closing` / `Closed`** — the pizzeria's own status; gates whether Guest Service can run at all (`02_discover_process_level.md` §6).
* **Readiness** — a locally-replicated read model: whether at least one table has an assigned `Active` waiter, and whether at least one chef is `Active`. Never queried live — fed continuously by Resource Management's events (`02` §6, `05_connect_message_flows.md` §0 and Scenario 4).
* **Active Visits** — the *set* of `guestGroupId`s currently mid-visit, not a raw counter: `GuestGroupSeated` adds one, `GuestGroupLeft` removes one, both idempotent under redelivery (`design_notes/dn_0002.md`). "Active Visits Count" is the size of that set, derived, not separately tracked (`02` §6, `08_pizzeria_lifecycle_read_models.md`).

## Business Decisions

* **`OpenPizzeria` requires at least one table with an assigned `Active` waiter, and at least one `Active` chef.** A table with no waiter assigned, or only a `Terminating`/`Terminated` waiter, doesn't count — it would leave Guest Service's `Available Tables` empty (`02` §6).
* **`StartClosingPizzeria` stops the Host admitting new guest groups**; existing open bills keep operating normally, including new orders (`02` §6).
* **Auto-close:** once `Closing` and Active Visits Count reaches 0, `ClosePizzeria` fires automatically — `BillClosed` is already guaranteed for every group by that point, so it isn't checked separately (`02` §6).

## Assumptions

* Only half of this holds: Table Management's `TableAssignedToWaiter`/`TableUnassignedFromWaiter` only ever fire while the pizzeria is itself `Closed` (`02` §2), so that half of Readiness has no staleness window at all — stronger than "eventually consistent." Waiter/Chef Management's `WaiterHired`/`ChefHired`/etc. carry no such guarantee — staffing isn't `Closed`-gated (`02` §4, §5) — so the waiter/chef-status half of Readiness is only as fresh as ordinary eventual consistency (`08_pizzeria_lifecycle_integration_events.md`).
* No two Managers race to open/close simultaneously — single human user, per `01_understand.md` §2.

## Open Questions

None at this stage.
