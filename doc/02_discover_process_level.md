# 02. Discover — Process Level EventStorming

**Step in the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process):** 2 of 8 — *Discover* (part 2: Process level).

**Purpose:** zoom into each process area identified in `02_discover_big_picture.md` §3, adding the commands that trigger events, the policies that automate reactions to events, and the read models actors need to act correctly.

**Key question:** *for each process, who does what, in what order, and what do they need to see to do it?*

This document does not introduce aggregates, entities, or bounded contexts — that's steps 3 (*Decompose*) and 8 (*Code*). It follows the process hierarchy agreed in `02_discover_big_picture.md` §3.

---

## Notation

* **Command** (imperative) — an actor's intent to change something, e.g. `PlaceOrder`.
* **Event** (past tense) — something that happened; cross-referenced to the timeline in `02_discover_big_picture.md` §2.
* **Policy** — automation of the shape *"Whenever `<event>` happens, then `<command>` is issued."*
* **Read Model** — a data view an actor needs in order to issue a command correctly.

---

## 1. Guest Service (main process)

### 1.1 Guest Arrival

**Scope:** starts when a guest group declares intent to enter; ends when the group is seated (or refused).

| Command | Actor | Event |
|---|---|---|
| `GuestGroupArrive` | Guest | `GuestGroupArrived` |
| `AssignTable` | Host | `TableAssigned` |
| `RefuseGuestGroup` | Host | `GuestGroupRefused` *(new — see note below)* |
| `SeatGuestGroup` | Host (auto) | `GuestGroupSeated` |

**Policies:**
* Whenever `GuestGroupArrived` → Host searches for a qualifying table.
* Whenever the Host finds no `Free` table with sufficient capacity and an `Active` waiter → `RefuseGuestGroup`. The group leaves; the process ends here for this group.
* Whenever the Host finds one or more qualifying tables → `AssignTable`, applying the **table selection policy** (below), then `SeatGuestGroup` (auto).

**Table selection policy:** when multiple qualifying tables exist, the Host picks the one whose waiter currently has the fewest `Occupied` tables (load-balancing).

**Read models:**
* **Available Tables** — tables that are `Free`, have capacity ≥ group size, and have an `Active` waiter assigned. Needed by the Host to search and apply the selection policy.
* **Waiter Workload** — number of `Occupied` tables per waiter. Needed by the Host's table-selection policy.

**Note — new event surfaced here:** `GuestGroupRefused` wasn't on the Big Picture timeline (§2.1.1 only covered the happy path). It should be added there too — see "Loop-back to Big Picture" at the end of this document.

---

### 1.2 Bill Management

**Scope:** starts when a guest group is seated; ends when the bill is closed.

| Command | Actor | Event |
|---|---|---|
| `OpenBill` | Waiter (auto, on seating) | `BillOpened` |
| `RequestBill` | Guest | `BillRequested` |
| `ReceivePayment` | Waiter | `PaymentReceived` |
| `CloseBill` | Waiter | `BillClosed` |

**Policies:**
* Whenever `GuestGroupSeated` (§1.1) → `OpenBill`.
* Whenever `BillRequested` **and** every order on this bill is `Delivered` **and** the bill total is 0 → skip payment, `CloseBill` automatically.
* Whenever `BillRequested` **and** every order on this bill is `Delivered` **and** the bill total is > 0 → wait for `ReceivePayment`, then `CloseBill`.
* Whenever `OrderPlaced` (§1.3) → the order's lines (with current menu prices) are added to this bill and its total is recalculated.

**Read models:**
* **Bill Summary** — order lines with prices and running total. Needed by the Waiter to know the amount due and relay it to the guest.
* **Order Delivery Status** — which orders on this bill are `Delivered` vs. still in flight. Needed to decide whether `BillRequested` can proceed to payment/closing.

---

### 1.3 Ordering

**Scope:** starts when the main process allows an order (bill is `Open`); ends when the order is `Delivered`. Repeats for every additional order within the same open bill.

| Command | Actor | Event |
|---|---|---|
| `PlaceOrder` | Guest, via Waiter | `OrderPlaced` |
| `SendOrderToKitchen` | Waiter | `OrderSentToKitchen` |
| `PickUpOrder` | Waiter | `OrderPickedUpFromKitchen` |
| `DeliverOrder` | Waiter | `OrderDelivered` |

**Policies:**
* Whenever `OrderPlaced` → order lines are added to the open bill (§1.2), and the Waiter proceeds to `SendOrderToKitchen`.
* Whenever `OrderSentToKitchen` → Kitchen Order Fulfilment (§1.3.1) begins.
* Whenever `OrderReadyForPickup` (§1.3.1) and the Waiter's task queue reaches this item (FIFO — tasks are handled strictly in arrival order, no prioritisation) → `PickUpOrder`, then `DeliverOrder`.

**Guard policies:**
* `PlaceOrder` is rejected once `BillRequested` (§1.2) has fired for this bill — asking for the bill is a point of no return for new orders, resolved during tactical design (`08_guest_service_aggregates.md` §3, invariant 3).

**Read models:**
* **Menu (guest view)** — name, basic ingredients, price. Needed by the Guest to select items (see §3 Menu Management).
* **Estimated Wait Time** — computed by Kitchen once an order is accepted (§1.3.1); shown to the Waiter to relay to guests. Not stored on the order itself.

---

#### 1.3.1 Kitchen Order Fulfilment (sub-process of Ordering)

**Scope:** starts when Kitchen receives a submitted order; ends when every pizza in it is `Ready` and the order is marked `ReadyForDelivery`.

| Command | Actor | Event |
|---|---|---|
| `AcceptOrder` | Kitchen (auto) | `OrderSplitIntoPizzas`, `OrderAccepted` |
| `PickUpPizzaFromQueue` | Chef | `PizzaPreparationStarted` |
| `FinishPizza` | Chef | `PizzaPrepared`, `ChefFinishedPizza` |
| `MarkOrderReady` | Kitchen (auto) | `OrderReadyForPickup` |
| `SetPizzaPreparationTime` | Manager | `PizzaPreparationTimeSet` |

**Policies:**
* Whenever `OrderSentToKitchen` → `AcceptOrder`: the order is split into one production task per pizza (`OrderLine` quantity), queued `Pending`; Kitchen estimates total time from queue depth, active chef count, and the configured preparation time, and publishes that estimate to Guest Service as `OrderAccepted` — a Guest Service concern, not stored anywhere in Kitchen beyond the moment it's computed (see `08_guest_service_read_models.md`).
* Whenever a Chef is free **and** the queue is non-empty → `PickUpPizzaFromQueue` (one pizza per chef at a time), taking the oldest `Pending` task — strictly FIFO, same convention as the Waiter's task queue (§1.3), resolved during tactical design (`08_kitchen_aggregates.md` §2, invariant 3).
* Whenever `PizzaPrepared` **and** it was the last pending/in-preparation pizza for its order → `MarkOrderReady` (auto).
* `FinishPizza` always publishes `ChefFinishedPizza` too, unconditionally — Kitchen doesn't know or care whether the chef is `Terminating` (that's Chef Management's own state, §5); it's Chef Management's `FinalizeChefTermination` policy that decides whether the fact is relevant, not a condition Kitchen evaluates before publishing.

**Read models:**
* **Production Queue** — pizzas `Pending`/`InPreparation`, shared across all chefs. Needed by a Chef to `PickUpPizzaFromQueue`.
* **Order Progress** — per-order count of pizzas `Ready` vs. total. Needed by Kitchen to decide `OrderReadyForPickup`.
* **Recipe (kitchen view)** — full ingredients and preparation steps per menu item. Needed by the Chef; distinct from the guest-facing menu view (see §3).

---

### 1.4 Departure

**Scope:** coordinated directly by the main process — not a dedicated sub-process. Starts when the bill is closed; ends when the table is released.

| Command | Actor | Event |
|---|---|---|
| `GuestGroupLeave` | Guest | `GuestGroupLeft` |
| `ReleaseTable` | system (auto) | `TableReleased` |

**Policies:**
* Whenever `BillClosed` → the guest group may `GuestGroupLeave` (a separate, guest-driven step — not automatic; a closed bill doesn't force departure).
* Whenever `GuestGroupLeft` → `ReleaseTable` (auto); Table Management (§2) separately reacts to `TableReleased` to flip its own `Free`/`Occupied` state, same as it does for `TableAssigned`.

---

## 2. Table Management

**Scope:** owns the `Table` resource — its existence, capacity, assigned waiter, and `Free`/`Occupied` state — across the whole pizzeria lifetime. All configuration changes (adding, resizing, removing, waiter (re)assignment) are allowed only while the pizzeria is `Closed` (§6).

| Command | Actor | Event |
|---|---|---|
| `AddTable` | Manager | `TableAdded` |
| `ChangeTableCapacity` | Manager | `TableCapacityChanged` |
| `RemoveTable` | Manager | `TableRemoved` |
| `AssignTableToWaiter` | Manager | `TableAssignedToWaiter` |
| `UnassignTableFromWaiter` | Manager | `TableUnassignedFromWaiter` |

**Policies (state driven by other processes):**
* Whenever `TableAssigned` (§1.1) → the table transitions `Free → Occupied`.
* Whenever `TableReleased` (§1.4) → the table transitions `Occupied → Free`.

**Guard policies:**
* `AddTable`, `ChangeTableCapacity`, `RemoveTable`, `AssignTableToWaiter`, and `UnassignTableFromWaiter` are all rejected unless the pizzeria is `Closed`. This supersedes an earlier, narrower per-table guard ("rejected while `Occupied`"): while `Closed`, `Active Visits Count` is 0 (§6), so no table is ever `Occupied` at a moment one of these commands could run — the state-level guard makes the table-level one unreachable.
* `RemoveTable` is rejected if it's the last table in the pizzeria.
* `AssignTableToWaiter` is also rejected unless the target waiter is `Active` — resolved during tactical design (`08_resource_management_aggregates.md` §1, invariant 4): assigning to a `Terminating` waiter contradicts winding them down, and to a `Terminated` one is almost certainly a mistake.

**Read models exposed:** **Available Tables** and **Waiter Workload** (both used by Guest Arrival, §1.1) — Table Management now holds both the assignment link and the `Free`/`Occupied` state needed to compute either, so it exposes both.

---

## 3. Menu Management

**Scope:** owns `MenuItem` definitions. All changes (adding, updating, removing) are allowed only while the pizzeria is `Closed` (§6) — same rule and same reason as Table Management (§2).

| Command | Actor | Event |
|---|---|---|
| `AddMenuItem` | Manager | `MenuItemAdded` |
| `UpdateMenuItem` | Manager | `MenuItemUpdated` |
| `RemoveMenuItem` | Manager | `MenuItemRemoved` |

**Guard policies:**
* `AddMenuItem`, `UpdateMenuItem`, and `RemoveMenuItem` are all rejected unless the pizzeria is `Closed`. This is what guarantees the price a guest sees on the menu (§1.3) is the price they get billed (§1.2) — the menu cannot change under a guest mid-visit, so there's no window for it to change out from under an open order.

**Read models exposed (same data, two views):**
* **Menu (guest view)** — name, basic ingredients, price. Used by §1.3 Ordering.
* **Recipe (kitchen view)** — full ingredients and preparation steps. Used by §1.3.1 Kitchen Order Fulfilment.

---

## 4. Waiter Management

**Scope:** hires and terminates waiters.

| Command | Actor | Event |
|---|---|---|
| `HireWaiter` | Manager | `WaiterHired` |
| `StartWaiterTermination` | Manager | `WaiterTerminationStarted` |
| `FinalizeWaiterTermination` | system (auto) | `WaiterTerminated` |

**Policies:**
* Whenever `StartWaiterTermination` → the waiter stops being offered to §1.1's table-selection policy for *new* guest groups, but keeps serving tables already assigned to them.
* Whenever `TableReleased` (§1.4) **and** the table's waiter is `Terminating` **and** no `Occupied` table is left pointing at this waiter → `FinalizeWaiterTermination` (auto). "Which tables point at this waiter" is not this process's own data — table-to-waiter assignment is owned by Table Management (§2) — so Waiter Management keeps a local replica fed by `TableAssignedToWaiter`/`TableUnassignedFromWaiter`, per the integration rule in `05_connect_message_flows.md` §0.

**Guard policies:**
* `StartWaiterTermination` is rejected if this would leave zero `Active` waiters while the pizzeria is `Open` or `Closing`.

---

## 5. Chef Management

**Scope:** hires/terminates chefs.

| Command | Actor | Event |
|---|---|---|
| `HireChef` | Manager | `ChefHired` |
| `StartChefTermination` | Manager | `ChefTerminationStarted` |
| `FinalizeChefTermination` | system (auto) | `ChefTerminated` |

**Policies:**
* Whenever `StartChefTermination` → the chef stops picking up new pizzas from the production queue (§1.3.1), but finishes the one currently in hand.
* Whenever `PizzaPrepared` (§1.3.1) **and** the chef who prepared it is `Terminating` → `FinalizeChefTermination` (auto). `PizzaPrepared` itself is Kitchen's own internal fact — this process learns "chef X just finished a pizza" from `ChefFinishedPizza` instead, published by Kitchen alongside `PizzaPrepared` for exactly this purpose (§1.3.1).

**Guard policies:**
* `StartChefTermination` is rejected if this would leave zero `Active` chefs while the pizzeria is `Open` or `Closing`.

---

## 6. Pizzeria Lifecycle

**Scope:** owns the pizzeria's own `Open` / `Closing` / `Closed` status, gating whether Guest Service can run at all.

| Command | Actor | Event |
|---|---|---|
| `OpenPizzeria` | Manager | `PizzeriaOpened` |
| `StartClosingPizzeria` | Manager | `PizzeriaClosingStarted` |
| `ClosePizzeria` | system (auto) | `PizzeriaClosed` |

**Policies:**
* Whenever `StartClosingPizzeria` → the Host (§1.1) stops admitting new guest groups; existing open bills keep operating normally (new orders still allowed).
* Whenever `GuestGroupLeft` (§1.4) **and** the pizzeria is `Closing` **and** no guest group is still mid-visit (Active Visits Count reaches 0) → `ClosePizzeria` (auto). (`BillClosed` is guaranteed to have already happened for every group by this point — §1.4's policy only allows `GuestGroupLeave` after `BillClosed` — so it doesn't need to be checked separately.)

**Guard policies:**
* `OpenPizzeria` requires at least one `Active` chef, and at least one table with an assigned `Active` waiter. (A table existing with no waiter assigned, or with only a `Terminating`/`Terminated` waiter assigned, doesn't count — it would leave `Available Tables`, §1.1, empty and no guest group could ever be seated.)
* While `Open` or `Closing`, the last `Active` waiter/chef cannot start termination (enforced in §4/§5, but the rule is owned conceptually by this process's readiness requirement).

**Read models:**
* **Pizzeria Readiness** — whether at least one table has an assigned `Active` waiter, and whether at least one chef is `Active`. Maintained as Pizzeria Lifecycle's own locally-replicated read model, fed by Table Management's, Waiter Management's, and Chef Management's events — never queried live from any of the three (see `05_connect_message_flows.md` §0 and Scenario 4). Needed to validate `OpenPizzeria`.
* **Active Visits Count** — number of guest groups currently mid-visit (`GuestGroupSeated` increments it, `GuestGroupLeft` decrements it). Needed to auto-trigger `PizzeriaClosed`.

> **Implementation note (added during tactical design):** "Active Visits Count" is a process-level abstraction. To stay idempotent under redelivered events, the internal tracking is a **set of active `guestGroupId`s** rather than a directly-incremented counter — the count is just a derived view. See `07_define_pizzeria_lifecycle.md` and `08_pizzeria_lifecycle_read_models.md` for the tactical shape, and `design_notes/dn_0002.md` for the general redelivery-safety reasoning.

---

## Loop-back to Big Picture

Per the working agreement in `doc/README.md`, discovering a new event at process level is reflected back in the Big Picture timeline it belongs to:

* `GuestGroupRefused` (Host) — surfaced in §1.1 above. ✅ Applied — added to `02_discover_big_picture.md` §2.1.1 (Guest Arrival).
* `TableAssignedToWaiter` / `TableUnassignedFromWaiter` (Manager) — surfaced in §2 above, replacing `TablesAssignedToWaiter`, as part of moving table-to-waiter assignment ownership from Waiter Management to Table Management (`03_decompose_subdomains.md` §5 Decisions). ✅ Applied — `02_discover_big_picture.md` §2.2.1/§2.2.3 and §3 updated to match.
* `OrderAccepted` (Kitchen) — surfaced in §1.3.1 above, while resolving an open question in `08_guest_service_read_models.md` about how Kitchen's wait-time estimate reaches Guest Service. Fires alongside `OrderSplitIntoPizzas` from the same `AcceptOrder` command, but crosses the Kitchen↔Guest Service boundary — `OrderSplitIntoPizzas` doesn't. ✅ Applied — `02_discover_big_picture.md` §2.1.3.1 updated to match.
* `ChefFinishedPizza` (Chef) — surfaced in §1.3.1 above, while resolving an open question in `08_resource_management_domain_model.md` about how `FinalizeChefTermination` (§5) learns that a `Terminating` chef's in-hand pizza is done. Fires alongside `PizzaPrepared` from the same `FinishPizza` command, but crosses the Kitchen↔Resource Management boundary — `PizzaPrepared` doesn't, same shape as `OrderAccepted`/`OrderSplitIntoPizzas` above. ✅ Applied — `02_discover_big_picture.md` §2.1.3.1 updated to match.

---

## Open Questions

* ~~**Waiter task queue ordering** (carried over from `02_discover_big_picture.md`): is it strictly FIFO, or does it prioritise certain actions?~~ Resolved: strictly FIFO in the simplified model — no prioritisation. Applied in §1.3.
* ~~Exact `Open → Closing → Closed` transition rules~~ — resolved above in §6.

None remaining.
