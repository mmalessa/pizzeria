# 08. Code — Domain Services: Kitchen

Part of the tactical design for the **Kitchen** Bounded Context.

---

## `WaitTimeEstimationPolicy`

Computes `EstimatedWaitTime` at `AcceptOrder` time, from "queue depth, active chef count, and the configured preparation time" (`02_discover_process_level.md` §1.3.1, verbatim).

```
estimate(kitchenOrder: KitchenOrder, productionQueueDepth: int, activeChefCount: int, preparationTime: PizzaPreparationTime): EstimatedWaitTime
```

**Signature notes:**
* Takes the whole `KitchenOrder`, not a pre-extracted `pizzaCount: int` — same reasoning applied throughout this series (`08_guest_service_domain_services.md`'s `TableSelectionPolicy`): this is explicitly a *policy*, `02` frames the calculation as its own concern, and a future variant might weigh individual `lines` differently (e.g. a pizza with more toppings taking longer) rather than treating every pizza as interchangeable. Extracting just a count now would foreclose that without a signature change later.
* `productionQueueDepth` and `activeChefCount` come from read models (Production Queue, Active Chef Pool — `08_kitchen_read_models.md`), not from reaching into other aggregate instances directly.

## `AcceptOrder` coordination

Not a "policy" in the swappable sense — this is the fixed piece of coordination logic that creates `KitchenOrder` and its `PizzaTask`s together (`08_kitchen_aggregates.md` §1, invariant 1). Documented here because it's genuinely cross-aggregate and needs to live somewhere outside either aggregate: neither `KitchenOrder` nor a `PizzaTask` can create the other from inside its own aggregate boundary.

```
acceptOrder(orderId, lines: { menuItemId, quantity }[]): (KitchenOrder, PizzaTask[])
```

Also where `WaitTimeEstimationPolicy` gets invoked and its result handed off to be published as `OrderAccepted` (`08_kitchen_integration_events.md`) — the one point where this context's "compute it, publish it, forget it" flow for wait time actually happens (`08_guest_service_read_models.md` §4).

## `OrderReadinessCheck`

Answers the cross-aggregate question `MarkOrderReady` needs: has every pizza for this order reached `Ready`? (`08_kitchen_aggregates.md` §1, invariant 2)

```
canMarkReady(kitchenOrder: KitchenOrder, orderProgress: OrderProgress): boolean
```

Re-evaluated whenever `PizzaPrepared` updates the Order Progress read model (`08_kitchen_aggregates.md` §3) — `orderProgress.completedTaskIds.size >= kitchenOrder.totalPizzaCount`. Takes the whole `KitchenOrder` and the whole Order Progress read model, not extracted scalars, for the same reason as `WaitTimeEstimationPolicy` and Guest Service's `BillClosingEligibility` (`08_guest_service_domain_services.md`) — this is the one place `02_discover_process_level.md` §1.3.1's readiness rule lives, and neither aggregate can answer it alone.

## `TaskSelectionPolicy`

Answers `PickUpPizzaFromQueue`'s question: which `Pending` `PizzaTask` does a free chef take? (`08_kitchen_aggregates.md` §2, invariant 3)

```
selectTask(pendingTasks: PizzaTask[]): PizzaTaskId
```

Strictly FIFO — the oldest `Pending` task, matching the Waiter's task queue precedent in Guest Service (`02_discover_process_level.md` §1.3). Named as a *policy*, not inlined into `PickUpPizzaFromQueue`'s handler, for the same forward-compatibility reason as `TableSelectionPolicy`/`WaitTimeEstimationPolicy` — a future variant (e.g. prioritising by order age across orders, not just task age) would only need a new implementation of this interface, not a change to `PizzaTask` itself.

---

## Open Questions

None at this stage.
