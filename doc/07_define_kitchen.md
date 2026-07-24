# 07. Define — Bounded Context Canvas: Kitchen

Part of step 7 (*Define*) — see `07_define_context_map.md` for how this context relates to the other three.

---

## Name

Kitchen

## Purpose

Turn an order handed off by Guest Service into prepared pizzas: split it into per-pizza production tasks, coordinate a shared queue across available chefs, and signal back once every pizza in the order is ready.

## Strategic Classification

* **Domain:** Supporting (`04_strategize_core_domain_chart.md` §1) — moderate-high complexity, low-moderate differentiation. Necessary plumbing for order fulfilment, not what differentiates the simulation.
* **Evolution:** Custom-built. Genuinely non-trivial (shared production queue, per-order progress tracking, time-estimation policy) and needs real modelling, but design effort should stay proportional (`04` §4).

## Domain Roles

The `Kitchen` role is itself a coordinator distinct from an individual `Chef` (`02_discover_big_picture.md` §1) — it accepts orders, splits them into tasks, and tracks per-order progress, closer to a lightweight **Process Manager** for one order's production than a single aggregate.

## Inbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Guest Service | Customer-Supplier (Kitchen is Supplier) | `OrderSentToKitchen` |
| Resource Management | Open Host Service + Published Language (`07_define_context_map.md` §3) | `MenuItemAdded`, `MenuItemUpdated`, `MenuItemRemoved` (→ Recipe kitchen view) · `ChefHired`, `ChefTerminationStarted`, `ChefTerminated` (→ Active Chef Pool) |

## Outbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Guest Service | Customer-Supplier (Kitchen is Supplier, signalling completion) | `OrderReadyForPickup`, `OrderAccepted` |

`OrderAccepted` carries the estimated wait time computed at `AcceptOrder` time (`02_discover_process_level.md` §1.3.1) — unlike everything else this canvas lists, it's not designed to feed a persisted replica on the receiving end; Guest Service relays it straight to the GUI and discards it (`08_guest_service_read_models.md`). It fires alongside Kitchen's own internal `OrderSplitIntoPizzas`, which stays inside this context.

Nothing from Kitchen crosses to Resource Management or Pizzeria Lifecycle — Kitchen has no dependency on pizzeria status at all: Guest Service structurally can't send `OrderSentToKitchen` while the pizzeria is `Closed` (no guest group exists in that state to place an order), so Kitchen never needed the guard the four Resource Management subdomains and Guest Service have (`06_organise.md` §4).

## Ubiquitous Language

* **Kitchen** — the coordinating role for the kitchen as a whole; distinct from an individual `Chef` (`02_discover_big_picture.md` §1).
* **Chef** — works the shared production queue, preparing one pizza at a time (`02_discover_big_picture.md` §5).
* **Production Queue** — pizzas `Pending`/`InPreparation`, shared across all chefs (`02_discover_process_level.md` §1.3.1).
* **Order**, here — not the guest-facing entity Guest Service knows. Once `OrderSentToKitchen` arrives, it's reinterpreted purely as a set of pizzas to produce, one production task per `OrderLine` quantity — no `tableId`, `billId`, or price ever crosses into this model (`07_define_context_map.md` §6).
* **Recipe** (kitchen view) — full ingredients and preparation steps per menu item; distinct from the guest-facing Menu view, which omits the recipe and includes the price (`02` §3).

## Business Decisions

* **One pizza per chef at a time**; multiple chefs work in parallel — this is what drives simulation dynamics (`02_discover_big_picture.md` §5).
* **A chef only picks up work when free and the queue is non-empty** — no forced assignment (`02` §1.3.1).
* **Estimated wait time** is computed from queue depth, active chef count, and the configured preparation time — not stored on the order itself, recomputed as needed (`02` §1.3.1).
* **An order is marked ready only once every pizza in it is ready** (`02` §1.3.1).
* **`PizzaPreparationTimeSet`** is a Kitchen-owned configuration event (Manager-set), not a Pizzeria Lifecycle concern, even though it's Manager-driven like the Resource Management contexts (`02_discover_big_picture.md` §2.1.3.1) — it's scoped to this context because it's a production parameter used directly in fulfilment, not a shared resource other contexts need.

## Assumptions

* Chef availability is always accurate from the locally-replicated Active Chef Pool — no live check against Resource Management mid-fulfilment (`07_define_context_map.md` §3).
* Recipes are always current: since Menu Management changes are `Closed`-only and no order exists while `Closed`, a recipe can never change out from under an order already in the queue (`02` §3).
* No order ever needs to be cancelled or partially fulfilled once accepted — `02_discover_big_picture.md` §5 rules this out at the domain level, so Kitchen doesn't model rollback.

## Open Questions

None at this stage.
