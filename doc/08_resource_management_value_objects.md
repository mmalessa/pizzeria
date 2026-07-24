# 08. Code — Value Objects: Resource Management

Part of the tactical design for the **Resource Management** Bounded Context.

---

## `Money`

Same shape and reasoning as Guest Service's `Money` (`08_guest_service_value_objects.md`) — a single non-negative monetary amount, no multi-currency modelling. Its own type, not literally shared code, per `07_define_context_map.md` §6 (no Shared Kernel) — `MenuItem.price` and Guest Service's `OrderLine.price` happen to look identical but are independent types that could diverge later without coordination.

## `Ingredients` / `RecipeSteps`

Deliberately **not** given dedicated value object treatment beyond "list of text" — `02_discover_process_level.md` §3 never assigns either any structure beyond "basic ingredients" (guest view) and "full ingredients and preparation steps" (kitchen view). No computation in this context depends on ingredient- or step-level detail (unlike `OrderLine`/`KitchenOrderLine`'s `quantity`, which feeds real logic) — introducing a richer type now would be speculative.

## Names

`Table.name`, `Waiter.name`, `Chef.name`, `MenuItem.name` are all plain strings, not dedicated value objects — none of them carries behaviour or validation beyond "non-empty" (`Table.name` additionally has a uniqueness rule, but that's a cross-instance guard, `08_resource_management_domain_services.md`'s `UniqueTableNameGuard`, not something a value-object type itself could enforce). Guest Service's `GuestGroup.name` (`08_guest_service_value_objects.md`) is the same kind of field, defined independently there — not a shared type, no Shared Kernel (`07_define_context_map.md` §6).

## Identifiers

| Type | Owned by this context? | Notes |
|---|---|---|
| `TableId` | Yes | |
| `MenuItemId` | Yes | Referenced opaquely by Guest Service and Kitchen (`08_guest_service_value_objects.md`, `08_kitchen_value_objects.md`) — this is where it's actually defined. |
| `WaiterId` | Yes | Referenced opaquely by Guest Service's replicated read models (`08_guest_service_value_objects.md`) — never by an aggregate there directly. |
| `ChefId` | Yes | Referenced opaquely by Kitchen's `PizzaTask.chefId` (`08_kitchen_value_objects.md`) — this is where it's actually defined. |

---

## Open Questions

None at this stage.
