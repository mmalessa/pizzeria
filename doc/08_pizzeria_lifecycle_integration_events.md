# 08. Code — Integration Events: Pizzeria Lifecycle

Part of the tactical design for the **Pizzeria Lifecycle** Bounded Context. Every published event goes to both Guest Service and Resource Management — one publish, two consumers (Open Host Service + Published Language, `07_define_context_map.md` §3).

---

## Published

### `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed`

| Field | Type | Why |
|---|---|---|
| — | — | No payload. `Pizzeria` is a singleton (`08_pizzeria_lifecycle_aggregates.md` §1) — there's nothing to correlate the event to beyond "the one pizzeria," so unlike every other event in this series, no identifier is needed at all. |

Consumers replicate these into their own local Pizzeria Status (Guest Service: `08_guest_service_read_models.md` §1; Resource Management: `08_resource_management_read_models.md` §1).

---

## Consumed

| Event | From | Used for |
|---|---|---|
| `TableAssignedToWaiter`, `TableUnassignedFromWaiter` | Resource Management | Readiness's table-to-waiter tracking (`08_pizzeria_lifecycle_read_models.md`) |
| `WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated` | Resource Management | Readiness's waiter-status tracking |
| `ChefHired`, `ChefTerminationStarted`, `ChefTerminated` | Resource Management | Readiness's chef-status tracking |
| `GuestGroupSeated`, `GuestGroupLeft` | Guest Service | Active Visits set (`08_pizzeria_lifecycle_read_models.md`) — add/remove `guestGroupId`, feeds `AutoCloseEligibility` |

**Only half of Readiness carries a stronger-than-usual freshness guarantee — the other half doesn't, and it's worth being precise about which.** `TableAssignedToWaiter`/`TableUnassignedFromWaiter` can only be published while the pizzeria is `Closed` (`08_resource_management_aggregates.md` §1, invariant 1) — meaning by the time either could arrive, `Pizzeria` already knows it's `Closed` on its own authority, having been the one to publish that fact in the first place. No staleness window is even possible for the table-assignment tracker (`08_pizzeria_lifecycle_read_models.md`), stronger than the general "eventually consistent" guarantee everywhere else in this series.

`WaiterHired`, `WaiterTerminationStarted`, `WaiterTerminated`, `ChefHired`, `ChefTerminationStarted`, `ChefTerminated` carry **no such guarantee** — staffing changes aren't `Closed`-gated at all (`08_resource_management_aggregates.md` §3, invariant 1; §4, invariant 1), so they can be published at any time, including while `Open`. The waiter/chef-status trackers are only as current as ordinary eventual consistency allows, same as every other replica in this series. `OpenPizzeria` is still only ever attempted from `Closed` (`08_pizzeria_lifecycle_aggregates.md` §1, invariant 1), but that alone doesn't guarantee every staffing event up to that moment has already been processed here.

---

## Open Questions

None at this stage.
