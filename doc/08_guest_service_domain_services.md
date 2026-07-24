# 08. Code — Domain Services: Guest Service

Part of the tactical design for the **Guest Service** Bounded Context. Covers logic that doesn't belong to a single aggregate — either because it coordinates across aggregates, or because it's explicitly a swappable *policy* rather than a fixed rule.

---

## `TableSelectionPolicy`

Implements the table-selection policy from `02_discover_process_level.md` §1.1: *"when multiple qualifying tables exist, the Host picks the one whose waiter currently has the fewest `Occupied` tables"* — load-balancing.

```
selectTable(guestGroup: GuestGroup, qualifyingTables: Table[], waiterWorkload: WaiterWorkload): TableId | None
```

**Signature notes:**
* Takes the **whole `GuestGroup`**, not an extracted `groupSize: int`. The current strategy (load-balance by waiter workload) doesn't actually need anything else from `GuestGroup` today — but this is explicitly a *policy*, the kind of thing a future variant might reasonably swap (e.g. a strategy that also considers a VIP flag, or a seating preference, if either concept is ever added to `GuestGroup`). Extracting just `groupSize` now would lock out any such variant without a signature change later.
* `qualifyingTables` and `waiterWorkload` are the **Available Tables** and **Waiter Workload** read models (`02_discover_process_level.md` §1.1), passed as-is — this service doesn't re-filter by capacity/`Free`/`Active`-waiter itself. That filtering already happened in the read model's own query (`Available Tables` is defined *parameterised by group size*: "capacity ≥ group size" is part of what makes a table qualify in the first place, before this policy ever runs).
* Returns `None` when `qualifyingTables` is empty — the caller (the command handler behind `GuestGroupArrive`'s policy) maps that directly to `RefuseGuestGroup`; a non-`None` result maps to `AssignTable` (`08_guest_service_aggregates.md` §1, invariant 6).

**Not this service's job:** re-checking that the returned table is actually `Free` or capacity-qualified at the moment of assignment — that's `Table` (in Resource Management)'s own guard territory, and `GuestGroup`'s `AssignTable` invariant already states it trusts the table it's given (`08_guest_service_aggregates.md` §1, invariant 1).

---

## `BillClosingEligibility`

Answers the cross-aggregate question `CloseBill`'s guard needs — both halves of `02_discover_process_level.md` §1.2's policy: is every `Order` on this bill `Delivered`, **and** has payment been settled (either the total is `0`, or it's `> 0` and `paymentReceived`)? (`08_guest_service_aggregates.md` §2, invariants 1 and 2)

```
canClose(bill: Bill, orderDeliveryStatus: OrderDeliveryStatus, billSummary: BillSummary): boolean
```

**Signature notes:**
* Takes the whole `Bill`, the whole **Order Delivery Status** read model, and the whole **Bill Summary** read model (`08_guest_service_read_models.md`) — even though today's rule only reads a few facts off each (`Bill.requested`/`Bill.paymentReceived`; whether every tracked order is `Delivered`; `billSummary.total`). Same reasoning as `TableSelectionPolicy`: this is the one place `02` §1.2's two-branch policy (skip payment vs. wait for it) lives, and a future variant of that policy is more plausible here than most other guards in this context.
* `billSummary` is where the total now comes from — `Bill` itself doesn't hold one (`08_guest_service_entities.md`, `README.md` Design Notes DN-2).
* Unlike `TableSelectionPolicy`, this isn't framed as an interchangeable "policy" in `02` — it's a fixed rule. It's still modelled as a domain service (not inlined into `CloseBill`'s handler) because it's genuinely cross-aggregate: `Bill` alone can't answer it.

---

## Open Questions

None at this stage.
