# 08. Code — Entities: Resource Management

Part of the tactical design for the **Resource Management** Bounded Context.

---

`Table`, `MenuItem`, `Waiter`, and `Chef` are all aggregate roots (`08_resource_management_aggregates.md`), and none owns a child with its own identity. Unlike Kitchen (`08_kitchen_entities.md`, where this same claim was initially wrong — `KitchenOrder.lines` turned out to be an unnamed value object), checked here explicitly field by field:

* `Table`: `capacity` (plain integer), `status` (enum), `assignedWaiterId` (identifier) — no nested structure.
* `MenuItem`: `name`, `ingredients`, `recipe` (plain text/string list), `price` (`Money`, `08_resource_management_value_objects.md`) — no nested structure with its own identity.
* `Waiter`, `Chef`: `status` (enum) only.

No entity beyond the four roots.

---

## Open Questions

None at this stage.
