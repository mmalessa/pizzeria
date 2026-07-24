# 08. Code — Value Objects: Pizzeria Lifecycle

Part of the tactical design for the **Pizzeria Lifecycle** Bounded Context.

---

No value object is owned by this context — `Pizzeria.status` is a plain enum (`08_pizzeria_lifecycle_aggregates.md`), and the read models (`08_pizzeria_lifecycle_read_models.md`) only ever reference identifiers defined elsewhere:

| Type | Owned by this context? | Notes |
|---|---|---|
| `TableId`, `WaiterId` | No — Resource Management | Appear in the Readiness read model's table-to-waiter tracking, never dereferenced beyond that (`07_define_context_map.md` §6). |
| `ChefId` | No — Resource Management | Appears in Readiness' chef-status tracking. |
| `GuestGroupId` | No — Guest Service | Appears in the Active Visits read model. |

---

## Open Questions

None at this stage.
