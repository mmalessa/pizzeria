# 08. Code — Read Models: Guest Service

Part of the tactical design for the **Guest Service** Bounded Context. Covers every read-side view this context maintains — both replicated from other Bounded Contexts and derived locally from its own aggregates.

---

## 1. Cross-context replicas

Maintained by simple event-projection handlers over the integration events listed in `08_guest_service_integration_events.md` §Consumed — never written to by this context's own commands, and never queried live from the owning context (`05_connect_message_flows.md` §0).

| Read model | Fed by | Notes |
|---|---|---|
| **Table & Waiter Availability** | Resource Management's table/waiter events | The underlying store: per-table capacity, `Free`/`Occupied`, assigned `waiterId`; per-waiter `Active`/`Terminating`/`Terminated` status. §2 below derives two narrower queries from it. |
| **Pizzeria Status** | Pizzeria Lifecycle's status events | `Open` / `Closing` / `Closed` — gates `GuestGroupArrive` (`08_guest_service_aggregates.md` §1). |
| **Menu (guest view)** | Resource Management's menu events | Name, ingredients, price — no recipe (`07_define_context_map.md` §6). |

## 2. Derived queries over Table & Waiter Availability

`02_discover_process_level.md` §1.1 names these as two separate read models; they're really two different queries over the one replicated store in §1, not two separately-maintained projections:

* **Available Tables** — `Table & Waiter Availability` filtered by: `Free`, capacity ≥ the arriving group's size, assigned waiter is `Active`. Parameterised by group size — there's no single static "available tables" list, it depends on who's asking.
* **Waiter Workload** — `Table & Waiter Availability` aggregated: count of `Occupied` tables per `waiterId`.

Both feed `TableSelectionPolicy` (`08_guest_service_domain_services.md`).

## 3. Local read models (fed by this context's own domain events)

Not replicated from anywhere — built from `GuestGroup`'s and `Order`'s own domain events (`08_guest_service_domain_model.md` §4), the same "replicate, don't reach into another aggregate" pattern applied one level down from cross-context.

* **Order Delivery Status** — which `Order`s on a given `Bill` are `Delivered` vs. still in flight. Fed by `OrderPlaced` (adds an order to track) and `OrderDelivered` (marks it done). Consumed by `BillClosingEligibility` (`08_guest_service_domain_services.md`).
* **Bill Summary** — order lines with prices, and the bill's total, for the Waiter to relay to the guest (`02_discover_process_level.md` §1.2). Fed by `OrderPlaced`, keyed by `orderId`: each order's lines are recorded once per `orderId`, not appended to an accumulator — a redelivered `OrderPlaced` for an order already on file overwrites the same entry instead of adding a second one. `total` is derived by summing the recorded entries, not tracked as a separately-incremented number. This is deliberately different from an earlier draft, which had `Bill` itself hold a `runningTotal` incremented on every `OrderPlaced` — dropped for the same redelivery-safety reason `08_kitchen_read_models.md`'s Order Progress was redesigned around a set instead of a counter (`README.md` Design Notes DN-2). `Bill` itself doesn't store order lines or a total at all (`08_guest_service_entities.md`) — this read model is the only place either lives.

## 4. Not a read model at all: Estimated Wait Time

`02_discover_process_level.md` §1.3 describes this as "computed by Kitchen once an order is accepted... shown to the Waiter to relay to guests. Not stored on the order itself." Resolved: it isn't stored *anywhere* in Guest Service, either. Kitchen publishes `OrderAccepted` (`08_guest_service_integration_events.md`, `05_connect_message_flows.md` Scenario 2) carrying `estimatedWaitTime`; Guest Service relays it straight to the GUI on arrival and discards it — no aggregate field, no local read model, nothing to keep consistent or query later. Unlike §1–3 above, this isn't a projection at all, just a pass-through notification. Still fully consistent with `05` §0's no-live-query rule (`OrderAccepted` is published exactly like any other integration event) — see the note added there for why "replicate" and "relay-then-discard" are two different, equally valid things a consumer can do with an event.

---

## Open Questions

None at this stage.

---

This completes the tactical design for Guest Service. Next per `doc/README.md`'s step 8 plan: Kitchen.
