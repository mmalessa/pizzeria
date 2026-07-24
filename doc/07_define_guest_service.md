# 07. Define — Bounded Context Canvas: Guest Service

Part of step 7 (*Define*) — see `07_define_context_map.md` for how this context relates to the other three.

---

## Name

Guest Service

## Purpose

Coordinate one guest group's entire visit, end to end — arrival, seating, ordering, billing, and departure — as a single continuous process. This is the context every other context in the system ultimately exists to serve (`02_discover_big_picture.md` §4 Key Insight).

## Strategic Classification

* **Domain:** Core Domain (`04_strategize_core_domain_chart.md` §1) — highest complexity, highest differentiation. This is what the simulation exists to demonstrate.
* **Evolution:** Custom-built, actively evolving. Deserves the deepest tactical design in step 8 and is the natural first candidate whenever the modelling process loops back (`04` §4).

## Domain Roles

Acts as the coordinating process for a visit — closest in shape to a **Process Manager**: it drives a multi-step sequence (arrival → bill open → ordering loop → payment → departure) by reacting to events and issuing the next command, without owning the specialised logic of the contexts it hands work off to (Kitchen for production, Resource Management for configuration facts).

## Inbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Guest (human user, `01_understand.md` §2.1) | direct command (external actor, not a Bounded Context) | `GuestGroupArrive`, `RequestBill`, `PlaceOrder` (via Waiter), `GuestGroupLeave` |
| Resource Management | Open Host Service + Published Language (`07_define_context_map.md` §3) | `TableAdded`, `TableCapacityChanged`, `TableRemoved`, `TableAssignedToWaiter`, `TableUnassignedFromWaiter`, `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `MenuItemAdded`, `MenuItemUpdated`, `MenuItemRemoved` |
| Pizzeria Lifecycle | Open Host Service + Published Language (`07_define_context_map.md` §3) | `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed` |
| Kitchen | Customer-Supplier (Guest Service is Customer) | `OrderReadyForPickup` |

## Outbound Communication

| Collaborator | Pattern | Messages |
|---|---|---|
| Resource Management (Table Management, Waiter Management) | Guest Service is upstream for *occupancy*, per `07_define_context_map.md` §2 | `TableAssigned`, `TableReleased` |
| Pizzeria Lifecycle | Guest Service is upstream for *visit-count facts*, per `07_define_context_map.md` §2 | `GuestGroupSeated`, `GuestGroupLeft` |
| Kitchen | Customer-Supplier (Guest Service is Customer, requesting production) | `OrderSentToKitchen` |

## Ubiquitous Language

* **GuestGroup** — a group of people arriving together, treated as one unit for the duration of a visit. Not defined by this context — the user creates it as an input before the process begins (`01` §2.1).
* **Table** (in this context) — a reference (`tableId`) tied to the current visit. This context doesn't hold or reason about capacity or waiter assignment beyond what its own Table & Waiter Availability replica tells it (`07_define_context_map.md` §6) — that's Resource Management's model.
* **Bill** — the running total for a visit; `Open → Closed` lifecycle (`02_discover_process_level.md` §1.2).
* **Order** — a guest-facing entity: order lines referencing `MenuItem`s with quantities and prices, tied to a `Bill`/`GuestGroup`/`Table` (`02` §1.3). Distinct from Kitchen's model of the same order (`07_define_context_map.md` §6).
* **Host**, **Waiter** — automated in-simulation actors driving this process (`01` §2.2).

## Business Decisions

* **Table selection policy:** among qualifying tables (`Free`, capacity ≥ group size, `Active` waiter assigned), pick the one whose waiter has the fewest `Occupied` tables — load balancing (`02` §1.1).
* **A bill can only close once every order on it is `Delivered`**, and only after payment if the total is > 0 (`02` §1.2).
* **No table changes mid-visit** — a guest group stays at the table the Host assigned for the whole visit (`02_discover_big_picture.md` §5).
* **No split bills, no order cancellation once sent to Kitchen** (`02_discover_big_picture.md` §5).
* **Departure isn't automatic** — a closed bill doesn't force the guest group to leave; `GuestGroupLeave` is a separate, guest-driven step (`02` §1.4).

## Assumptions

* Resource Management's replicated table/waiter/menu data is always current enough to act on — for menu specifically, this isn't just an assumption but a guarantee: Menu Management's `Closed`-only guard makes it impossible for the menu to change during any guest's visit (`02` §3, `07_define_context_map.md` §6).
* Exactly one Host exists and is fully automated — no contention modelling for multiple Hosts (`01` §2.2, `02_discover_big_picture.md` §1).
* Kitchen always eventually responds with `OrderReadyForPickup` — no order-fulfilment failure/timeout modelling (`02_discover_big_picture.md` §5: no order cancellation).

## Open Questions

None at this stage.
