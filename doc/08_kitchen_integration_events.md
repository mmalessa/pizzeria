# 08. Code — Integration Events: Kitchen

Part of the tactical design for the **Kitchen** Bounded Context. Covers events this context **publishes** across its boundary, per `07_define_kitchen.md` Outbound Communication and `05_connect_message_flows.md` Scenario 2. Two go to Guest Service, one to Resource Management.

---

## Published

### `OrderAccepted`

| Field | Type | Why |
|---|---|---|
| `orderId` | `KitchenOrderId` (correlates with Guest Service's `OrderId`) | Lets Guest Service match this to the order it's displaying. |
| `estimatedWaitTime` | `EstimatedWaitTime` | The one piece of information this event exists to carry (`08_kitchen_value_objects.md`). |

Raised from `AcceptOrder` (`08_kitchen_domain_services.md`), alongside — but independent of — the internal `OrderSplitIntoPizzas` domain event, which never crosses this boundary (`08_kitchen_domain_model.md` §3). Deliberately **not** designed to feed a persisted read model on the receiving end — Guest Service relays it to the GUI and discards it (`08_guest_service_read_models.md` §4). Still an ordinary integration event in every other respect: published once, consumed independently, no live query involved (`05_connect_message_flows.md` §0's added note).

### `OrderReadyForPickup`

| Field | Type | Why |
|---|---|---|
| `orderId` | `KitchenOrderId` | Correlates to the `Order` Guest Service is waiting on (`08_guest_service_aggregates.md` §3, invariant 2). |

Deliberately minimal — no per-pizza detail, no chef information. Guest Service's `Order` aggregate only cares that the whole thing is done, not how it got there (`07_define_context_map.md` §6). Raised automatically once the Order Progress read model's completed set reaches `KitchenOrder.totalPizzaCount` in size (`08_kitchen_aggregates.md` §1, invariant 2; `08_kitchen_domain_services.md`'s `OrderReadinessCheck`) — this *is* the aggregate's own completion signal, not a separate event wrapping it (`08_kitchen_domain_model.md` §3).

### `ChefFinishedPizza`

| Field | Type | Why |
|---|---|---|
| `chefId` | `ChefId` (`08_kitchen_value_objects.md`) | The one fact Resource Management's `Chef` needs — which chef just became free (`08_resource_management_aggregates.md` §4). |

Goes to **Resource Management**, not Guest Service — the only Kitchen-published event that does (`07_define_kitchen.md` Outbound Communication). Raised from `FinishPizza`, alongside — but independent of — the internal `PizzaPrepared` domain event (`08_kitchen_domain_model.md` §3), same split shape as `AcceptOrder`'s `OrderSplitIntoPizzas`/`OrderAccepted`. Published unconditionally on every `FinishPizza`: Kitchen doesn't check whether the chef is `Terminating` before publishing — that's Resource Management's own state, and its `Chef` aggregate decides on its own side whether the fact is relevant (`02_discover_process_level.md` §5). Not designed to feed a persisted replica on the receiving end — Resource Management consumes it as a direct trigger, the same way it consumes Guest Service's `TableReleased` for `Waiter`'s equivalent check (`08_resource_management_aggregates.md` §3).

---

## Consumed

| Event | From | Used for |
|---|---|---|
| `OrderSentToKitchen` | Guest Service | Triggers `AcceptOrder` — creates `KitchenOrder` and its `PizzaTask`s (`08_kitchen_aggregates.md` §1) |
| `MenuItemAdded`, `MenuItemUpdated`, `MenuItemDisabled`, `MenuItemEnabled` | Resource Management | Recipe (kitchen view) replica (`05` §0) — `MenuItemDisabled` removes the entry, `MenuItemEnabled` re-adds it |
| `ChefHired`, `ChefTerminationStarted`, `ChefTerminated`, `ChefRehired` | Resource Management | Active Chef Pool replica — employment status only, not "currently busy" (`08_kitchen_domain_model.md` §4) |

---

## Open Questions

None at this stage.
