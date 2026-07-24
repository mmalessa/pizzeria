# 08. Code — Entities: Guest Service

Part of the tactical design for the **Guest Service** Bounded Context. The two aggregate roots (`GuestGroup`, `Order`) are fully specified in `08_guest_service_aggregates.md` — this document covers the one entity living *inside* an aggregate that isn't a root itself.

---

## `Bill` (entity of `GuestGroup`)

Has identity (distinct `billId`, since a future requirement could need to reference a specific bill independently — e.g. a receipt lookup), but no lifecycle or repository of its own: it's always loaded, saved, and consistency-checked as part of its owning `GuestGroup` (`08_guest_service_domain_model.md` §3). Invariants are specified in `08_guest_service_aggregates.md` §2 — this is the field-level shape only.

| Field | Type | Notes |
|---|---|---|
| `billId` | `BillId` (value object, `08_guest_service_value_objects.md`) | Identity within `GuestGroup`, not a separate aggregate. |
| `status` | `Open` \| `Closed` | |
| `requested` | `boolean` | Whether `RequestBill` has fired. |
| `paymentReceived` | `boolean` | Only meaningful once the bill total is `> 0` (`08_guest_service_read_models.md`'s Bill Summary). |

Deliberately **not** a field: any list of `Order`s, or a running total. `Bill` never holds a live view of `Order` state — the `CloseBill` guard reads the **Order Delivery Status** read model instead (`08_guest_service_aggregates.md` §2, invariant 1). The total is likewise not tracked on `Bill` as an incrementally-updated sum: an earlier draft did exactly that (`runningTotal`, `+=` on every `OrderPlaced`), which turned out to have the same redelivery-safety problem `design_notes/dn_0002.md` documents for Kitchen's `readyCount` — a redelivered `OrderPlaced` would double-add that order's price. The total now lives in **Bill Summary** (`08_guest_service_read_models.md`), tracked per `orderId` rather than accumulated, so it's safe by construction.

---

## Open Questions

None at this stage.
