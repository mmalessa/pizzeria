# 08. Code — Integration Events: Resource Management

Part of the tactical design for the **Resource Management** Bounded Context. Every event below is published once and reused identically by every consumer (Open Host Service + Published Language, `07_define_context_map.md` §3, §5) — no per-consumer variants.

---

## Published

### From `Table`

| Event | Payload | Consumers |
|---|---|---|
| `TableAdded` | `{ tableId, capacity }` | Guest Service |
| `TableCapacityChanged` | `{ tableId, capacity }` | Guest Service |
| `TableRemoved` | `{ tableId }` | Guest Service |
| `TableAssignedToWaiter` | `{ tableId, waiterId }` | Guest Service, Pizzeria Lifecycle |
| `TableUnassignedFromWaiter` | `{ tableId }` | Guest Service, Pizzeria Lifecycle |

### From `MenuItem`

| Event | Payload | Consumers |
|---|---|---|
| `MenuItemAdded` | `{ menuItemId, name, ingredients, recipe, price }` | Guest Service, Kitchen |
| `MenuItemUpdated` | `{ menuItemId, name, ingredients, recipe, price }` | Guest Service, Kitchen |
| `MenuItemRemoved` | `{ menuItemId }` | Guest Service, Kitchen |

Full snapshot on every event, even though Guest Service only ever uses `name`/`ingredients`/`price` and Kitchen only `name`/`ingredients`/`recipe` (`07_define_context_map.md` §6). Not split the way `AcceptOrder` was in Kitchen (`08_kitchen_domain_model.md` §3) — there, two audiences needed genuinely disjoint data for a need-to-know reason (Kitchen must never see price at all, by design). Here it's just that each side projects a subset of one coherent record; nothing requires withholding the rest.

### From `Waiter`

| Event | Payload | Consumers |
|---|---|---|
| `WaiterHired` | `{ waiterId }` | Guest Service, Pizzeria Lifecycle |
| `WaiterTerminationStarted` | `{ waiterId }` | Guest Service, Pizzeria Lifecycle |
| `WaiterTerminated` | `{ waiterId }` | Guest Service, Pizzeria Lifecycle |

### From `Chef`

| Event | Payload | Consumers |
|---|---|---|
| `ChefHired` | `{ chefId }` | Kitchen, Pizzeria Lifecycle |
| `ChefTerminationStarted` | `{ chefId }` | Kitchen, Pizzeria Lifecycle |
| `ChefTerminated` | `{ chefId }` | Kitchen, Pizzeria Lifecycle |

---

## Consumed

| Event | From | Used for |
|---|---|---|
| `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed` | Pizzeria Lifecycle | Local Pizzeria Status replica (`08_resource_management_read_models.md`) — feeds `ClosedOnlyGuard` and `LastActiveStaffGuard` (`08_resource_management_domain_services.md`) |
| `TableAssigned`, `TableReleased` | Guest Service | `Table.status` mirroring (`08_resource_management_aggregates.md` §1, invariant 3); `TableReleased` also triggers `Waiter`'s `FinalizeWaiterTermination` check |
| `ChefFinishedPizza` (`{ chefId }`) | Kitchen | Direct trigger for `Chef`'s `FinalizeChefTermination`, checked against `Chef.status` on the one instance — no read model involved (`08_resource_management_aggregates.md` §4, invariant 3) |

---

## Open Questions

None at this stage.
