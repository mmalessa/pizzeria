# 08. Code — Domain Services: Resource Management

Part of the tactical design for the **Resource Management** Bounded Context. Because this context merges four aggregate types sharing the same guard infrastructure (`07_define_resource_management.md`'s Domain Roles), several of these are genuinely reused across more than one aggregate — not duplicated per-aggregate logic.

---

## `ClosedOnlyGuard`

Shared by every `Table` and `MenuItem` command (`08_resource_management_aggregates.md` §1 invariant 1, §2 invariant 1).

```
requiresClosed(pizzeriaStatus: PizzeriaStatus): boolean
```

One check, reused across eight commands (`AddTable`, `ChangeTableCapacity`, `RemoveTable`, `AssignTableToWaiter`, `UnassignTableFromWaiter`, `AddMenuItem`, `UpdateMenuItem`, `RemoveMenuItem`) rather than duplicated in each command handler.

## `LastTableGuard`

Answers `RemoveTable`'s second guard: would this removal leave zero tables? (`08_resource_management_aggregates.md` §1, invariant 2)

```
isLastTable(tableId: TableId, tableDirectory: TableDirectory): boolean
```

Takes the whole Table Directory read model (`08_resource_management_domain_model.md` §3), not a pre-computed count — the count itself is cheap to derive and there's no reason to introduce a second, separately-maintained number that could drift from the directory.

## `LastActiveStaffGuard`

Shared by `Waiter`'s and `Chef`'s termination-start guard — same shape in both (`08_resource_management_aggregates.md` §3 invariant 2, §4 invariant 2).

```
wouldLeaveZeroActive(directory: WaiterDirectory | ChefDirectory, pizzeriaStatus: PizzeriaStatus): boolean
```

One generic service instead of two near-identical ones (`WaiterLastActiveGuard`, `ChefLastActiveGuard`) — the rule is identical for both ("would this leave zero `Active` entries, while `Open` or `Closing`"), only the directory type differs. This is exactly the kind of shared infrastructure `07_define_resource_management.md` justifies merging these subdomains for.

## `WaiterTerminationCompletionCheck`

Answers `FinalizeWaiterTermination`'s guard: does this `Terminating` waiter still have an `Occupied` table? (`08_resource_management_aggregates.md` §3, invariant 3)

```
hasOccupiedTable(waiterId: WaiterId, tableDirectory: TableDirectory): boolean
```

**No `Chef` equivalent needed** — not a gap, a genuine simplification. `Waiter` needs this cross-aggregate check because one waiter can hold several `Occupied` tables at once, so "am I done" requires looking across potentially many `Table` instances. `Chef` prepares one pizza at a time (`02_discover_big_picture.md` §5): Kitchen's `ChefFinishedPizza` (`08_kitchen_integration_events.md`) for a given `chefId` is already the complete answer, so `FinalizeChefTermination` just checks `Chef.status` on the one aggregate instance it already has — no directory, no domain service (`08_resource_management_aggregates.md` §4, invariant 3).

---

## Open Questions

None at this stage.
