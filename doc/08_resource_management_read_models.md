# 08. Code — Read Models: Resource Management

Part of the tactical design for the **Resource Management** Bounded Context.

---

## 1. Cross-context replica

| Read model | Fed by | Notes |
|---|---|---|
| **Pizzeria Status** | Pizzeria Lifecycle's status events | One shared local copy for all four aggregate types (`08_resource_management_domain_model.md` §3) — not four separate replicas, even though `05_connect_message_flows.md` §0 originally listed Table Management's and Menu Management's copies as separate rows (subdomain-level granularity; this is the same underlying data, now one replica at the merged Bounded Context level). |

## 2. Local read models (fed by this context's own aggregates' events)

Keyed directories, not counters — per `design_notes/dn_0002.md`, applied from the start here rather than discovered after the fact (as it was for Kitchen and Guest Service).

* **Table Directory** — `tableId → { capacity, status, assignedWaiterId }`. Fed by `TableAdded`, `TableCapacityChanged`, `TableRemoved`, `TableAssignedToWaiter`, `TableUnassignedFromWaiter`, and Guest Service's `TableAssigned`/`TableReleased` (`08_resource_management_integration_events.md`). Feeds `LastTableGuard`, `WaiterTerminationCompletionCheck` (`08_resource_management_domain_services.md`).
* **Waiter Directory** — `waiterId → status`. Fed by `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`. Feeds `LastActiveStaffGuard`.
* **Chef Directory** — `chefId → status`. Fed by `ChefHired`, `ChefTerminationStarted`, `ChefTerminated`. Feeds `LastActiveStaffGuard`.

## 3. Not a separate read model: Available Tables / Waiter Workload

`02_discover_process_level.md` §2 names these as read models "exposed" by Table Management, used by Guest Arrival. They aren't maintained here as their own projections — **Table Directory** (§2 above) already holds everything either needs. The "exposure" happens through the integration events in `08_resource_management_integration_events.md`; Guest Service is the one that actually builds **Available Tables**/**Waiter Workload** as two queries over its own replica (`08_guest_service_read_models.md` §2). Worth calling out explicitly so a future reader doesn't go looking for a fourth local read model here that doesn't exist — same shape as Kitchen's Order Progress note (`08_kitchen_read_models.md` §3, since resolved differently there — this one really is just "computed downstream," not miscategorised the way Kitchen's was).

---

## Open Questions

None at this stage.

---

This completes the tactical design for Resource Management. Next per `doc/README.md`'s step 8 plan: Pizzeria Lifecycle.
