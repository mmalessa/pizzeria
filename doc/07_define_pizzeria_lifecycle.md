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
* **Active Visits Count** — number of guest groups currently mid-visit; increments on `GuestGroupSeated`, decrements on `GuestGroupLeft` (`02` §6).

## Business Decisions

* **`OpenPizzeria` requires at least one table with an assigned `Active` waiter, and at least one `Active` chef.** A table with no waiter assigned, or only a `Terminating`/`Terminated` waiter, doesn't count — it would leave Guest Service's `Available Tables` empty (`02` §6).
* **`StartClosingPizzeria` stops the Host admitting new guest groups**; existing open bills keep operating normally, including new orders (`02` §6).
* **Auto-close:** once `Closing` and Active Visits Count reaches 0, `ClosePizzeria` fires automatically — `BillClosed` is already guaranteed for every group by that point, so it isn't checked separately (`02` §6).

## Assumptions

* Resource Management's configuration changes (which feed Readiness) only ever happen while the pizzeria is itself `Closed` (`02` §2, §3) — meaning by the time any `TableAssignedToWaiter`/`WaiterHired`/etc. event could arrive, Pizzeria Lifecycle already knows it's `Closed`. There's no window where Readiness could be updated mid-`Open`, which is a stronger guarantee than "eventually consistent."
* No two Managers race to open/close simultaneously — single human user, per `01_understand.md` §2.

## Open Questions

None at this stage.
