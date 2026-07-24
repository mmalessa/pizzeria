# 08. Code — Value Objects: Guest Service

Part of the tactical design for the **Guest Service** Bounded Context.

---

## `OrderLine`

One line of an `Order` (`08_guest_service_aggregates.md` §3). No identity — two lines with the same `menuItemId`, `quantity`, and `price` are interchangeable — and immutable once created: `Order` never edits a line after `PlaceOrder`, only advances the order's own `status`.

| Field | Type | Notes |
|---|---|---|
| `menuItemId` | `MenuItemId` (below) | Opaque reference into Resource Management. |
| `quantity` | positive integer | Feeds Kitchen's per-pizza task split (`02_discover_process_level.md` §1.3.1: "one production task per pizza, `OrderLine` quantity"). |
| `price` | `Money` (below) | Captured at `PlaceOrder` time — see `08_guest_service_domain_model.md` §1 for why this is frozen on the line rather than re-derived. |

**Open point, not a bug:** whether ordering the same `menuItemId` twice in one `PlaceOrder` call produces one line with `quantity: 2` or two separate lines with `quantity: 1` each is left unspecified — nothing in `02_discover_process_level.md` §1.3 forces either shape, and both are equivalent from Kitchen's side (it only cares about total pizzas per menu item). Pick whichever is more convenient at implementation time.

## `Money`

Wraps a single non-negative monetary amount. No multi-currency modelling — the pizzeria has exactly one, implicit currency; introducing a currency field would be speculative given nothing in `01_understand.md` through `07` calls for it. Supports addition (used to derive **Bill Summary**'s total from its recorded order lines, `08_guest_service_read_models.md` §3 — not to mutate a field on `Bill`, which holds no total, `08_guest_service_entities.md`) and equality/comparison to zero (used by the `Bill` guard split in `08_guest_service_aggregates.md` §2, invariant 2).

## Names

`GuestGroup.name` is a plain string, not a dedicated value object — no behaviour beyond "non-empty," and its uniqueness rule (scoped to currently-active groups, `08_guest_service_aggregates.md` §1 invariant 7) is a cross-instance guard (`UniqueActiveGuestGroupNameGuard`, `08_guest_service_domain_services.md`), not something a value-object type itself could enforce. Same kind of field as Resource Management's `Table.name` (`08_resource_management_value_objects.md`), defined independently — not a shared type, no Shared Kernel (`07_define_context_map.md` §6).

## Identifiers

All typed, non-interchangeable value objects wrapping a raw ID — prevents passing a `TableId` where a `MenuItemId` is expected, without adding any behaviour beyond identity and equality.

| Type | Owned by this context? | Notes |
|---|---|---|
| `GuestGroupId` | Yes — but the group's own *definition* is external input (`08_guest_service_domain_model.md` §1) | |
| `OrderId` | Yes | |
| `BillId` | Yes | Identity within `GuestGroup`, not a separate repository (`08_guest_service_entities.md`). |
| `TableId` | No — Resource Management | Opaque here: Guest Service carries it, never dereferences it beyond its own replicated read models (`07_define_context_map.md` §6). |
| `MenuItemId` | No — Resource Management | Same as `TableId`. |
| `WaiterId` | No — Resource Management | Only appears indirectly, inside the locally-replicated Table & Waiter Availability read model (`05_connect_message_flows.md` §0) — no aggregate in this context stores it directly. |

---

## Open Questions

None at this stage.
