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

**Read models:**
* **Menu (guest view)** — name, basic ingredients, price. Needed by the Guest to select items (see §3 Menu Management).
* **Estimated Wait Time** — computed by Kitchen once an order is accepted (§1.3.1); shown to the Waiter to relay to guests. Not stored on the order itself.

---

#### 1.3.1 Kitchen Order Fulfilment (sub-process of Ordering)

**Scope:** starts when Kitchen receives a submitted order; ends when every pizza in it is `Ready` and the order is marked `ReadyForDelivery`.

| Command | Actor | Event |
|---|---|---|
| `AcceptOrder` | Kitchen (auto) | `OrderSplitIntoPizzas` |
| `PickUpPizzaFromQueue` | Chef | `PizzaPreparationStarted` |
| `FinishPizza` | Chef | `PizzaPrepared` |
| `MarkOrderReady` | Kitchen (auto) | `OrderReadyForPickup` |
| `SetPizzaPreparationTime` | Manager | `PizzaPreparationTimeSet` |

**Policies:**
* Whenever `OrderSentToKitchen` → `AcceptOrder`: the order is split into one production task per pizza (`OrderLine` quantity), queued `Pending`; Kitchen estimates total time from queue depth, active chef count, and the configured preparation time.
* Whenever a Chef is free **and** the queue is non-empty → `PickUpPizzaFromQueue` (one pizza per chef at a time).
* Whenever `PizzaPrepared` **and** it was the last pending/in-preparation pizza for its order → `MarkOrderReady` (auto).

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

**Scope:** owns the `Table` resource — its existence, capacity, and `Free`/`Occupied` state — across the whole pizzeria lifetime.

| Command | Actor | Event |
|---|---|---|
| `AddTable` | Manager | `TableAdded` |
| `ChangeTableCapacity` | Manager | `TableCapacityChanged` |
| `RemoveTable` | Manager | `TableRemoved` |

**Policies (state driven by other processes):**
* Whenever `TableAssigned` (§1.1) → the table transitions `Free → Occupied`.
* Whenever `TableReleased` (§1.4) → the table transitions `Occupied → Free`.

**Guard policies:**
* `RemoveTable` and `ChangeTableCapacity` are rejected while the table is `Occupied`.
* `RemoveTable` is rejected if it's the last table in the pizzeria.

**Note:** a table's *assigned waiter* is written by Waiter Management (§4), not here — see that section's `AssignTablesToWaiter` command. Table Management and Waiter Management describe the same assignment from two different angles, matching how it was already framed in earlier analysis.

**Read model exposed:** **Available Tables** (used by Guest Arrival, §1.1).

---

## 3. Menu Management

**Scope:** owns `MenuItem` definitions.

| Command | Actor | Event |
|---|---|---|
| `AddMenuItem` | Manager | `MenuItemAdded` |
| `UpdateMenuItem` | Manager | `MenuItemUpdated` |
| `RemoveMenuItem` | Manager | `MenuItemRemoved` |

**Read models exposed (same data, two views):**
* **Menu (guest view)** — name, basic ingredients, price. Used by §1.3 Ordering.
* **Recipe (kitchen view)** — full ingredients and preparation steps. Used by §1.3.1 Kitchen Order Fulfilment.

---

## 4. Waiter Management

**Scope:** hires/terminates waiters and assigns them to tables.

| Command | Actor | Event |
|---|---|---|
| `HireWaiter` | Manager | `WaiterHired` |
| `AssignTablesToWaiter` | Manager | `TablesAssignedToWaiter` |
| `StartWaiterTermination` | Manager | `WaiterTerminationStarted` |
| `FinalizeWaiterTermination` | system (auto) | `WaiterTerminated` |

**Policies:**
* Whenever `StartWaiterTermination` → the waiter stops being offered to §1.1's table-selection policy for *new* guest groups, but keeps serving tables already assigned to them.
* Whenever `TableReleased` (§1.4) **and** the table's waiter is `Terminating` **and** no `Occupied` table is left pointing at this waiter → `FinalizeWaiterTermination` (auto).

**Guard policies:**
* `StartWaiterTermination` is rejected if this would leave zero `Active` waiters while the pizzeria is `Open` or `Closing`.

**Read model exposed:** **Waiter Workload** (used by §1.1's table-selection policy).

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
* Whenever `PizzaPrepared` (§1.3.1) **and** the chef who prepared it is `Terminating` → `FinalizeChefTermination` (auto).

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
* `OpenPizzeria` requires at least one `Active` waiter, one `Active` chef, and one table to exist.
* While `Open` or `Closing`, the last `Active` waiter/chef cannot start termination (enforced in §4/§5, but the rule is owned conceptually by this process's readiness requirement).

**Read models:**
* **Pizzeria Readiness** — active waiter count, active chef count, table count. Needed to validate `OpenPizzeria`.
* **Active Visits Count** — number of guest groups currently mid-visit (`GuestGroupSeated` increments it, `GuestGroupLeft` decrements it). Needed to auto-trigger `PizzeriaClosed`.

---

## Loop-back to Big Picture

Per the working agreement in `doc/README.md`, discovering a new event at process level is reflected back in the Big Picture timeline it belongs to:

* `GuestGroupRefused` (Host) — surfaced in §1.1 above. ✅ Applied — added to `02_discover_big_picture.md` §2.1.1 (Guest Arrival).

---

## Open Questions

* ~~**Waiter task queue ordering** (carried over from `02_discover_big_picture.md`): is it strictly FIFO, or does it prioritise certain actions?~~ Resolved: strictly FIFO in the simplified model — no prioritisation. Applied in §1.3.
* ~~Exact `Open → Closing → Closed` transition rules~~ — resolved above in §6.

None remaining.
