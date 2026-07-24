# 08. Code — Entities: Kitchen

Part of the tactical design for the **Kitchen** Bounded Context.

---

Unlike Guest Service (`08_guest_service_entities.md`, which has `Bill` nested inside `GuestGroup`), Kitchen has no non-root **entity**: both `KitchenOrder` and `PizzaTask` are aggregate roots (`08_kitchen_aggregates.md`), and neither owns a child with its own identity. This doesn't mean `KitchenOrder` is structurally bare, though — it holds `lines`, a list of `KitchenOrderLine` **value objects** (no identity, per `08_kitchen_value_objects.md`). "No entities beyond the roots" and "flat, no supporting building blocks at all" aren't the same claim; only the first is true here.

---

## Open Questions

None at this stage.
