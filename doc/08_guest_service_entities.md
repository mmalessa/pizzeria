# 08. Code — Entities: Guest Service

Part of the tactical design for the **Guest Service** Bounded Context. The two aggregate roots (`GuestGroup`, `Order`) are fully specified in `08_guest_service_aggregates.md` — this document covers the one entity living *inside* an aggregate that isn't a root itself.

---

## `Bill` (entity of `GuestGroup`)

Has identity (distinct `billId`, since a future requirement could need to reference a specific bill independently — e.g. a receipt lookup), but no lifecycle or repository of its own: it's always loaded, saved, and consistency-checked as part of its owning `GuestGroup` (`08_guest_service_domain_model.md` §3). Invariants are specified in `08_guest_service_aggregates.md` §2 — this is the field-level shape only.

| Field | Type | Notes |
|---|---|---|
| `billId` | `BillId` (value object, §`08_guest_service_value_objects.md`) | Identity within `GuestGroup`, not a separate aggregate. |
| `status` | `Open` \| `Closed` | |
| `runningTotal` | `Money` (value object) | Recalculated on `OrderPlaced` (`08_guest_service_domain_model.md` §4). |
| `requested` | `boolean` | Whether `RequestBill` has fired. |
| `paymentReceived` | `boolean` | Only meaningful once `runningTotal > 0`. |

Deliberately **not** a field: any list or count of `Order`s. `Bill` never holds a live view of `Order` state — the `CloseBill` guard reads the **Order Delivery Status** read model instead (`08_guest_service_aggregates.md` §2, invariant 1), and `runningTotal` is a running sum updated incrementally, not derived by summing a held collection.

---

## Open Questions

None at this stage.
