# 08. Code — Domain Services: Resource Management

Part of the tactical design for the **Resource Management** Bounded Context. Because this context merges four aggregate types sharing the same guard infrastructure (`07_define_resource_management.md`'s Domain Roles), several of these are genuinely reused across more than one aggregate — not duplicated per-aggregate logic.

---

## `ClosedOnlyGuard`

Shared by every `Table` and `MenuItem` command (`08_resource_management_aggregates.md` §1 invariant 1, §2 invariant 1).

```
requiresClosed(pizzeriaStatus: PizzeriaStatus): boolean
```

One check, reused across ten commands (`AddTable`, `ChangeTableCapacity`, `RenameTable`, `RemoveTable`, `AssignTableToWaiter`, `UnassignTableFromWaiter`, `AddMenuItem`, `UpdateMenuItem`, `DisableMenuItem`, `EnableMenuItem`) rather than duplicated in each command handler.

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

## `ActiveWaiterGuard`

Answers `AssignTableToWaiter`'s second guard: is the target waiter `Active`? (`08_resource_management_aggregates.md` §1, invariant 4)

```
isActive(waiterId: WaiterId, waiterDirectory: WaiterDirectory): boolean
```

Rejects assignment to a `Terminating` or `Terminated` waiter — resolved during tactical design, not stated in `02_discover_process_level.md` §2/§4. Reuses the same Waiter Directory `LastActiveStaffGuard` already reads (`08_resource_management_domain_model.md` §3), just a membership check instead of a count.

## `UniqueTableNameGuard`

Answers `AddTable`'s and `RenameTable`'s name-uniqueness guard (`08_resource_management_aggregates.md` §1, invariant 5).

```
isUnique(name: string, tableDirectory: TableDirectory): boolean
```

Takes the whole Table Directory (`08_resource_management_domain_model.md` §3), same reasoning as `LastTableGuard` above — the check is cheap to derive from the directory that already exists for other guards, no reason to introduce a separately-maintained index just for this.

## `WaiterRehireCleanup`

Coordinates `RehireWaiter`'s second effect: clearing any table still pointing at this `waiterId` from before termination (`08_resource_management_aggregates.md` §3, invariant 4).

```
staleTables(waiterId: WaiterId, tableDirectory: TableDirectory): TableId[]
```

Returns every `tableId` whose `assignedWaiterId` still equals this `waiterId`. `FinalizeWaiterTermination` only ever guarded against `Occupied` tables (§3, invariant 3), so a `Free` table can survive all the way past `Terminated` with nothing to clear it — harmless while `Terminated` was a dead end, but not once `RehireWaiter` makes it reversible. `RehireWaiter`'s handler issues `UnassignTableFromWaiter` for each result before completing — `Waiter` can't issue that command against `Table` itself (a different aggregate), so this coordination has to live outside either one, the same reason `AcceptOrder`'s two-aggregate creation needed its own coordination in Kitchen (`08_kitchen_aggregates.md` §1, invariant 1). **No `Chef` equivalent** — same reasoning as `WaiterTerminationCompletionCheck` above: nothing in this context tracks a persistent per-chef assignment a rehire could leave stale.

---

## Open Questions

None at this stage.
