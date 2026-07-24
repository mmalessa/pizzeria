# 08. Code — Value Objects: Kitchen

Part of the tactical design for the **Kitchen** Bounded Context.

---

## `KitchenOrderLine`

One line of a `KitchenOrder` (`08_kitchen_aggregates.md` §1) — `{ menuItemId, quantity }`. No identity — two lines with the same `menuItemId` and `quantity` are interchangeable — and immutable once created: `KitchenOrder` never edits `lines` after `AcceptOrder` (`08_kitchen_aggregates.md` §1, invariant 3). Guest Service's own `OrderLine` (`08_guest_service_value_objects.md`) also carries `price`; this one doesn't — Kitchen never receives a price at all (`08_guest_service_integration_events.md`, `OrderSentToKitchen`'s payload).

## `EstimatedWaitTime`

A duration, computed at `AcceptOrder` time from queue depth, active chef count, and the configured preparation time (`02_discover_process_level.md` §1.3.1). Exists only transiently — never persisted, not a field on `KitchenOrder` (`08_kitchen_aggregates.md` §1 doesn't list it), computed and immediately handed to the `OrderAccepted` integration event, then discarded (`08_guest_service_read_models.md` §4). Modelled as a value object anyway, not a raw number, because the computation itself (§`08_kitchen_domain_services.md`) is exactly the kind of thing worth naming.

## `PizzaPreparationTime`

The Manager-configured parameter set by `SetPizzaPreparationTime` (`02_discover_process_level.md` §1.3.1) — a single duration, one input to `EstimatedWaitTime`'s calculation. Kitchen-owned configuration, not Resource Management's, per `01_understand.md` §2 and `02_discover_big_picture.md` §2.1.3.1 (already justified when this was first raised, see `07_define_kitchen.md` Business Decisions).

## Identifiers

| Type | Owned by this context? | Notes |
|---|---|---|
| `KitchenOrderId` | Yes | Correlates 1:1 with Guest Service's `OrderId`, but is its own type — no Shared Kernel (`08_kitchen_aggregates.md` §1). |
| `PizzaTaskId` | Yes | |
| `MenuItemId` | No — Resource Management | Opaque: used only to look up the Recipe read model (`08_kitchen_read_models.md`), never dereferenced further. |
| `ChefId` | No — Resource Management | Opaque: appears on `PizzaTask.chefId` and in the Busy Chefs / Active Chef Pool read models, never resolved to a `Chef` entity locally — there isn't one (`08_kitchen_domain_model.md` §4). |

---

## Open Questions

None at this stage.
