# 08. Code — Domain Model: Pizzeria Lifecycle

Part of the tactical design for the **Pizzeria Lifecycle** Bounded Context (`07_define_pizzeria_lifecycle.md`) — Supporting (`04_strategize_core_domain_chart.md` §1).

**Purpose:** identify the aggregate(s) inside this context and how they relate. Invariants are detailed in `08_pizzeria_lifecycle_aggregates.md`.

This is the smallest context in the system — one aggregate, no internal relationships. Its complexity is entirely in its *coupling* to the other three contexts (`04` §3), not in its own model.

---

## 1. Aggregate

### Pizzeria

**Singleton.** There is exactly one pizzeria in this domain (`01_understand.md` — the whole simulation models one restaurant); this aggregate has no meaningful identity beyond "the one instance," unlike every other aggregate root in this series.

Owns: `status` (`Closed` → `Open` → `Closing` → `Closed`, the cycle repeats — full state machine in `08_pizzeria_lifecycle_aggregates.md` §1). Driven by commands: `OpenPizzeria`, `StartClosingPizzeria`, `ClosePizzeria` (auto) (`02_discover_process_level.md` §6).

---

## 2. Relationships

No diagram — there is only one aggregate in this context, and it references no other aggregate (there is no other aggregate to reference).

`Pizzeria`'s guards depend on two locally-replicated read models sourced from *other* Bounded Contexts (`08_pizzeria_lifecycle_read_models.md`), not on any aggregate in this one:

* **Readiness** — whether `OpenPizzeria`'s conditions are met, replicated from Resource Management's events.
* **Active Visits** — which guest groups are currently mid-visit, replicated from Guest Service's events.

---

## 3. Cross-aggregate coordination

Nothing to coordinate *within* this context — with one aggregate, there's no second aggregate to stay in sync with. The interesting coordination is cross-*context*, already governed by `05_connect_message_flows.md` §0's replication rule and detailed per-read-model in `08_pizzeria_lifecycle_read_models.md`.

---

## Open Questions

None at this stage.
