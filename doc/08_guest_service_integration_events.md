# 08. Code — Integration Events: Guest Service

Part of the tactical design for the **Guest Service** Bounded Context. Covers the events this context **publishes** across its boundary — the five identified in `07_define_guest_service.md`'s Outbound Communication and `05_connect_message_flows.md` §0 — now specified down to payload shape. Payload design follows the same minimalism the rest of this series has used: carry only what a known consumer actually needs, nothing "for completeness."

---

## Published

### `TableAssigned`

| Field | Type | Why |
|---|---|---|
| `tableId` | `TableId` | The only fact Table Management and Waiter Management need — to flip `Free → Occupied` and to mark the table `Occupied` in Assigned Tables, respectively (`05_connect_message_flows.md` Scenario 1). |

`guestGroupId` deliberately **not** included — no known consumer needs it. Resource Management doesn't track *who* occupies a table, only *that* it's occupied.

### `TableReleased`

| Field | Type | Why |
|---|---|---|
| `tableId` | `TableId` | Same reasoning as `TableAssigned`, reverse direction — `Occupied → Free`, and unmarking `Occupied` in Assigned Tables (`05_connect_message_flows.md` Scenario 3). |

### `GuestGroupSeated`

| Field | Type | Why |
|---|---|---|
| `guestGroupId` | `GuestGroupId` | Pizzeria Lifecycle tracks Active Visits as a **set** of currently-active `guestGroupId`s, not a bare counter — so a redelivered `GuestGroupSeated` is a no-op instead of double-counting (`05_connect_message_flows.md` §0, Scenario 1). |

### `GuestGroupLeft`

| Field | Type | Why |
|---|---|---|
| `guestGroupId` | `GuestGroupId` | Same set-based reasoning as `GuestGroupSeated`, reverse direction (`05_connect_message_flows.md` Scenario 3). |

### `OrderSentToKitchen`

| Field | Type | Why |
|---|---|---|
| `orderId` | `OrderId` | Correlates Kitchen's later `OrderReadyForPickup` back to this order. Kitchen treats this same value as its own `KitchenOrderId` (a separate type, no Shared Kernel — see `08_kitchen_aggregates.md` §1). |
| `lines` | `{ menuItemId: MenuItemId, quantity: int }[]` | What Kitchen needs to produce. |

Deliberately **not** included: `tableId`, `billId`, `price` — Kitchen's model of an order is purely a set of pizzas to produce; it never sees where the order came from or what it costs (`07_define_context_map.md` §6, "Order, between Guest Service and Kitchen"). `price` is dropped even though `OrderLine` carries it internally (`08_guest_service_value_objects.md`) — it's a `GuestGroup`/`Bill` concern only.

---

## Consumed

Guest Service is downstream for the following (payload shapes are the publishing context's responsibility — Resource Management's, Pizzeria Lifecycle's, and Kitchen's own `08_*_integration_events.md`, written when tactical design reaches those contexts):

| Event | From | Used for |
|---|---|---|
| `TableAdded`, `TableCapacityChanged`, `TableRenamed`, `TableRemoved`, `TableAssignedToWaiter`, `TableUnassignedFromWaiter` | Resource Management | Table & Waiter Availability replica (`05` §0) |
| `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `WaiterRehired` | Resource Management | Table & Waiter Availability replica — `Active` waiter status (`05` §0) |
| `MenuItemAdded`, `MenuItemUpdated`, `MenuItemDisabled`, `MenuItemEnabled` | Resource Management | Menu (guest view) replica (`05` §0) — `MenuItemDisabled` removes the entry, `MenuItemEnabled` re-adds it |
| `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed` | Pizzeria Lifecycle | Pizzeria Status replica (`05` §0) |
| `OrderReadyForPickup` | Kitchen | Triggers `PickUpOrder` once the Waiter's FIFO queue reaches it (`08_guest_service_aggregates.md` §3, invariant 2) |
| `OrderAccepted` (`{ orderId, estimatedWaitTime }`) | Kitchen | Relayed straight to the GUI, not persisted anywhere in this context — see `08_guest_service_read_models.md`. |

---

## Open Questions

None at this stage.
