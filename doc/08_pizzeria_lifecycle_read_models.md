# 08. Code — Read Models: Pizzeria Lifecycle

Part of the tactical design for the **Pizzeria Lifecycle** Bounded Context. Both read models below apply `design_notes/dn_0002.md` from the start — keyed tracking, never a raw counter or mutated boolean fed directly by `+=`-style event handling.

---

## Readiness

Answers `OpenPizzeriaEligibility`'s question: at least one table with an assigned `Active` waiter, and at least one `Active` chef? (`02_discover_process_level.md` §6). Composed of three keyed trackers, each fed by Resource Management's events (`08_pizzeria_lifecycle_integration_events.md`) — this context's own independent replica, not a shared copy of Resource Management's internal `Table Directory`/`Waiter Directory`/`Chef Directory` (`08_resource_management_read_models.md`); per `07_define_context_map.md` §6, no Shared Kernel, so this context maintains its own:

* **Table Assignment** — `tableId → waiterId`, fed by `TableAssignedToWaiter` (upsert) and `TableUnassignedFromWaiter` (remove).
* **Waiter Status** — `waiterId → status`, fed by `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `WaiterRehired`.
* **Chef Status** — `chefId → status`, fed by `ChefHired`, `ChefTerminationStarted`, `ChefTerminated`, `ChefRehired`.

The two booleans `OpenPizzeriaEligibility` needs are derived, not stored: "does any entry in Table Assignment map to a `waiterId` whose Waiter Status is `Active`?" and "does any entry in Chef Status equal `Active`?" — computed fresh from the three trackers each time, never cached as a separately-mutated flag.

## Active Visits

The *set* of `guestGroupId`s currently mid-visit — not a counter, per `07_define_pizzeria_lifecycle.md`'s corrected description. Fed by `GuestGroupSeated` (add) and `GuestGroupLeft` (remove), both idempotent: redelivering either is a no-op (adding an already-present ID, or removing an already-absent one, change nothing). "Active Visits Count," as `02_discover_process_level.md` §6 names it, is this set's size — derived on demand by `AutoCloseEligibility` (`08_pizzeria_lifecycle_domain_services.md`), not tracked as its own incrementing/decrementing field the way an earlier draft of this context described it.

---

## Open Questions

None at this stage.

---

This completes the tactical design for Pizzeria Lifecycle — and with it, step 8 for all four Bounded Contexts (`doc/README.md`).
