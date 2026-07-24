# 08. Code — Read Models: Resource Management

Part of the tactical design for the **Resource Management** Bounded Context.

---

## 1. Cross-context replica

| Read model | Fed by | Notes |
|---|---|---|
| **Pizzeria Status** | Pizzeria Lifecycle's status events | One shared local copy for all four aggregate types (`08_resource_management_domain_model.md` §3) — not four separate replicas, even though `05_connect_message_flows.md` §0 originally listed Table Management's and Menu Management's copies as separate rows (subdomain-level granularity; this is the same underlying data, now one replica at the merged Bounded Context level). |

## 2. Local read models (fed by this context's own aggregates' events)

Keyed directories, not counters — per `design_notes/dn_0002.md`, applied from the start here rather than discovered after the fact (as it was for Kitchen and Guest Service).

* **Table Directory** — `tableId → { name, capacity, status, assignedWaiterId }`. Fed by `TableAdded`, `TableCapacityChanged`, `TableRenamed`, `TableRemoved`, `TableAssignedToWaiter`, `TableUnassignedFromWaiter`, and Guest Service's `TableAssigned`/`TableReleased` (`08_resource_management_integration_events.md`). Feeds `LastTableGuard`, `UniqueTableNameGuard`, `WaiterTerminationCompletionCheck`, `WaiterRehireCleanup` (`08_resource_management_domain_services.md`).
* **Waiter Directory** — `waiterId → status`. Fed by `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `WaiterRehired`. Feeds `LastActiveStaffGuard`.
* **Chef Directory** — `chefId → status`. Fed by `ChefHired`, `ChefTerminationStarted`, `ChefTerminated`, `ChefRehired`. Feeds `LastActiveStaffGuard`.

## 3. Not a separate read model: Available Tables / Waiter Workload

`02_discover_process_level.md` §2 names these as read models "exposed" by Table Management, used by Guest Arrival. They aren't maintained here as their own projections — **Table Directory** (§2 above) already holds everything either needs. The "exposure" happens through the integration events in `08_resource_management_integration_events.md`; Guest Service is the one that actually builds **Available Tables**/**Waiter Workload** as two queries over its own replica (`08_guest_service_read_models.md` §2). Worth calling out explicitly so a future reader doesn't go looking for a fourth local read model here that doesn't exist — same shape as Kitchen's Order Progress note (`08_kitchen_read_models.md` §3, since resolved differently there — this one really is just "computed downstream," not miscategorised the way Kitchen's was).

## 4. GUI-facing presentation views

Three read models exist purely to serve a human-facing view (`01_understand.md` §2.1), not a domain guard — unlike everything in §1–2 above. Each is a straightforward query over this context's own aggregates, built the same event-projection way as the guard-facing directories, just shaped for display instead of for a decision:

* **Dining Room View** — every table's `name`, `capacity`, `status`, and assigned waiter's `name` (denormalised from `Waiter`, so the GUI doesn't issue a second query). Serves the Dining Room perspective (`01_understand.md` §2.1). Built entirely from this context's own Table Directory (§2) and Waiter Directory (§2), since `Table` and `Waiter` are both aggregates here — no cross-context replication needed at all, unlike an equivalent view would have required back when Table Management and Waiter Management were still separate subdomains (`03_decompose_subdomains.md` §1).
* **Staff View** — every waiter's and chef's `name` and `status`, plus, for waiters, the list of currently-assigned table names. Serves the Manager's staff overview (`01_understand.md` §2.1). The table-name list is a field that exists **only** in this read model — `Waiter` itself deliberately holds no such list (`08_resource_management_domain_model.md` §2: "`Waiter` carries nothing pointing back"); this view is exactly where that reverse lookup (`findByWaiterId` over the Table Directory) gets materialised for display, without duplicating state on the write side.
* **Menu Management View** — every `MenuItem` regardless of `status`, including `Disabled` ones, so the Manager can find and restore one (`02_discover_process_level.md` §3). The one presentation view in this context that genuinely needs to see past what Guest Service's and Kitchen's replicas are ever shown — those two only ever receive `Active` items (`08_resource_management_integration_events.md`) — but since this view is built locally from `MenuItem` itself rather than a replica, it isn't limited by what crosses a boundary.

None of these three feed a guard or a cross-aggregate check — if one ever needed to, it would stop being purely a presentation concern and would move into §2 instead.

---

## Open Questions

None at this stage.

---

This completes the tactical design for Resource Management. Next per `doc/README.md`'s step 8 plan: Pizzeria Lifecycle.
