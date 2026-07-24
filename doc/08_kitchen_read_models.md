# 08. Code — Read Models: Kitchen

Part of the tactical design for the **Kitchen** Bounded Context.

---

## 1. Cross-context replicas

| Read model | Fed by | Notes |
|---|---|---|
| **Recipe (kitchen view)** | Resource Management's menu events | Name, ingredients, preparation steps — no price (`07_define_context_map.md` §6). |
| **Active Chef Pool** | Resource Management's chef events | `Active`/`Terminating`/`Terminated` employment status only — not whether a chef is currently mid-`PizzaTask` (§2). |

## 2. Local read models (fed by this context's own domain events)

* **Production Queue** — `PizzaTask`s with `status = Pending`, ordered oldest-first (not just a set — `TaskSelectionPolicy`, `08_kitchen_domain_services.md`, needs FIFO order, resolved from `08_kitchen_aggregates.md` §2's Open Question). Not a separately-maintained projection — a direct query over `PizzaTask` (`08_kitchen_domain_model.md` §4), listed here because `02_discover_process_level.md` §1.3.1 names it as a read model a Chef consults.
* **Busy Chefs** — which `chefId`s are currently `InPreparation` on a `PizzaTask`. Fed by `PizzaPreparationStarted` (mark busy) and `PizzaPrepared` (mark free) — `08_kitchen_aggregates.md` §3. Not named explicitly in `02_discover_process_level.md` §1.3.1, but required to enforce "a chef prepares one pizza at a time" (`08_kitchen_aggregates.md` §2, invariant 1); combined with Active Chef Pool (§1) to answer "is this chef both employed and free" at `PickUpPizzaFromQueue` time.
* **Order Progress** — per `kitchenOrderId`, the **set** of `pizzaTaskId`s that have reached `Ready`. Fed by `PizzaPrepared`, which adds the finishing task's ID to its order's set (`08_kitchen_aggregates.md` §3). This is the read model `02_discover_process_level.md` §1.3.1 names directly ("per-order count of pizzas `Ready` vs. total") — modelled as a set of IDs rather than a raw counter specifically so a redelivered `PizzaPrepared` for the same task is a no-op rather than double-counting (`08_kitchen_aggregates.md` §1). Compared against `KitchenOrder.totalPizzaCount` by `OrderReadinessCheck` (`08_kitchen_domain_services.md`) to decide `MarkOrderReady`.

---

## Open Questions

None at this stage.

---

This completes the tactical design for Kitchen. Next per `doc/README.md`'s step 8 plan: Resource Management.
