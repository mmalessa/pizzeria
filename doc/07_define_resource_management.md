# 07. Define — Bounded Context Canvas: Resource Management

Part of step 7 (*Define*) — see `07_define_context_map.md` for how this context relates to the other three.

---

## Name

Resource Management

## Purpose

Let the Manager configure the pizzeria's shared resources — tables, menu, waiters, chefs — as one coherent configuration surface, uniformly gated by the pizzeria's `Closed` state. Merges four subdomains that stay distinct at the modelling level but share one actor, one configuration shape, and (for Table/Menu) an identical guard (`07_define_context_map.md` §1).

## Strategic Classification

* **Domain:** Generic / Supporting mix (`07_define_context_map.md` §1) — Table Management and Menu Management are Generic, Waiter Management and Chef Management are Supporting (`04_strategize_core_domain_chart.md` §1). This BC-level label is a convenience, not a strict DDD classification: classification is properly a *subdomain* concept, and this context spans two of them. Internally, treat each of the four as its own model with its own classification, not one uniform "Generic/Supporting" blob.
* **Evolution:** Table/Menu Management — commodity-like, off-the-shelf shape (`04` §4: "prime candidates for an off-the-shelf/generic solution"). Waiter/Chef Management — custom-built, but simple.

## Domain Roles

Four largely independent configuration aggregates (`Table`, `MenuItem`, `Waiter`, `Chef`) sharing infrastructure (the `Closed`-only / last-active-staff guard family) rather than a shared model. Merging them into one context is an organisational/deployment decision (`06_organise.md` §3), not a claim that they form one aggregate or one ubiquitous-language pocket.

## Inbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Pizzeria Lifecycle | Open Host Service + Published Language (`07_define_context_map.md` §3) | `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed` |
| Guest Service | Resource Management is downstream for *occupancy* only, per `07_define_context_map.md` §2 (Table Management mirrors it, doesn't own it) | `TableAssigned`, `TableReleased` |
| Kitchen | Customer-Supplier (Kitchen is Supplier) | `ChefFinishedPizza` — feeds `FinalizeChefTermination` (`08_resource_management_aggregates.md` §4), a genuinely new dependency added after this canvas was first drafted (`08_resource_management_domain_model.md`'s Open Questions, since resolved). |

## Outbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Guest Service | Open Host Service + Published Language | `TableAdded`, `TableCapacityChanged`, `TableRemoved`, `TableAssignedToWaiter`, `TableUnassignedFromWaiter`, `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `MenuItemAdded`, `MenuItemUpdated`, `MenuItemRemoved` |
| Kitchen | Open Host Service + Published Language | `MenuItemAdded`, `MenuItemUpdated`, `MenuItemRemoved`, `ChefHired`, `ChefTerminationStarted`, `ChefTerminated` |
| Pizzeria Lifecycle | Resource Management is upstream for *readiness facts* (table assignment, staff status), per `07_define_context_map.md` §2 | `TableAssignedToWaiter`, `TableUnassignedFromWaiter`, `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `ChefHired`, `ChefTerminationStarted`, `ChefTerminated` |

## Ubiquitous Language

* **Table** — capacity, `Free`/`Occupied` state (mirrored from Guest Service), and its assigned waiter (`02_discover_process_level.md` §2).
* **MenuItem** — name, ingredients, recipe, price (`02` §3). Two projections exist outside this context: Guest Service's guest view (no recipe) and Kitchen's kitchen view (no price) — see `07_define_context_map.md` §6.
* **Waiter** / **Chef** — `Active` / `Terminating` / `Terminated` lifecycle, sharing the shape but not the completion rule (`02_discover_big_picture.md` §3).
* **Assigned Tables** (as consumed by Waiter Management's own guard) — internal to this context once merged; no longer a cross-context integration concern (`06_organise.md` §4).

## Business Decisions

* **All Table and Menu changes are rejected unless the pizzeria is `Closed`** (`02` §2, §3) — this is what eliminates the classic menu-price race at the domain-rule level, not by patching replication (`07_define_context_map.md` §6).
* **`RemoveTable` is rejected if it's the last table in the pizzeria** (`02` §2).
* **The last `Active` waiter/chef cannot start termination while `Open` or `Closing`** (`02` §4, §5).
* **Table-to-waiter assignment is owned here, not in Waiter Management** — a deliberate revision from an earlier draft; the termination-completion rule only needs to *read* the assignment, not *write* it (`03_decompose_subdomains.md` §5 Decisions).

## Assumptions

* The Manager only ever attempts configuration changes while `Closed` in the simulated GUI — the guard exists to enforce this, not merely document it (`02` §2, §3).
* Grouping four subdomains into one context doesn't imply one shared aggregate or transaction — each keeps its own consistency boundary; only team/deployment ownership is shared (`06_organise.md` §3).

## Open Questions

None at this stage.
