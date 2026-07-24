# 08. Code — Domain Services: Pizzeria Lifecycle

Part of the tactical design for the **Pizzeria Lifecycle** Bounded Context.

---

## `OpenPizzeriaEligibility`

Answers `OpenPizzeria`'s guard: does Readiness show both conditions met? (`08_pizzeria_lifecycle_aggregates.md` §1, invariant 1)

```
canOpen(readiness: Readiness): boolean
```

Takes the whole Readiness read model (`08_pizzeria_lifecycle_read_models.md`), not two pre-extracted booleans — per `design_notes/dn_0003.md`'s convention of passing whole objects to guard/policy services, not a pre-extracted subset of fields (`08_guest_service_domain_services.md`'s `TableSelectionPolicy`, among others). `Pizzeria` holds no readiness data itself to pass instead.

## `AutoCloseEligibility`

Answers `ClosePizzeria`'s guard: is the pizzeria `Closing`, and are there zero guest groups still mid-visit? (`08_pizzeria_lifecycle_aggregates.md` §1, invariant 3)

```
canAutoClose(pizzeria: Pizzeria, activeVisits: ActiveVisits): boolean
```

Re-evaluated on every `GuestGroupLeft` (`08_pizzeria_lifecycle_read_models.md`) — `activeVisits.guestGroupIds.isEmpty()` after removing the departing group, combined with `pizzeria.status == Closing`.

---

## Open Questions

None at this stage.
