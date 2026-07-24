# 06. Organise — Hypothetical Team Ownership

**Step in the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process):** 6 of 8 — *Organise*.

**Purpose:** in a real setting, form autonomous teams aligned with context boundaries (Team Topologies, Conway's Law).

**Simplification note:** this is a **solo project with no organisation behind it** — there are no teams to align, and no reorganisation to plan. This document records *conceptually* which team would plausibly own each subdomain if Pizzeria were a real product with a real engineering org, purely as useful context for later "as if this were real" discussions. It isn't a real team-design exercise, and doesn't gate or block step 7 (*Define*) in any way.

**Key question:** *if this were a real organisation, which team would own which part of the domain, and how would they need to interact?*

**Note on granularity:** `doc/README.md` describes this artifact as "per bounded context." Team ownership below is aligned 1:1 with the Bounded Context grouping finalised in `07_define_context_map.md`.

---

## 1. Team types considered

Using [Team Topologies](https://teamtopologies.com/) vocabulary:

* **Stream-aligned team** — owns end-to-end delivery of a distinct stream of business value; needs to move fast and iterate independently.
* **Platform team** — provides other teams with self-service capabilities (APIs, data, tooling) that reduce their cognitive load, with low differentiation of its own.
* **Complicated-subsystem team** — owns a piece of the domain that needs deep specialist knowledge to work on safely. Not used below — nothing in this domain reaches that bar; even Kitchen's production-queue logic is well within a generalist stream-aligned team's reach.
* **Enabling team** — helps other teams adopt new techniques. Not used below — there's no cross-team capability gap to bridge in a project this size.

---

## 2. Hypothetical team ownership

| Team | Type | Owns (subdomains) | Classification (`04`) |
|---|---|---|---|
| **Guest Experience** | Stream-aligned | Guest Service | Core |
| **Kitchen Operations** | Stream-aligned | Kitchen | Supporting |
| **Resource Management** | Platform | Table Management, Menu Management, Waiter Management, Chef Management | Generic / Supporting |
| **Pizzeria Lifecycle** | Stream-aligned | Pizzeria Lifecycle | Supporting |

---

## 3. Rationale

### Guest Experience — Stream-aligned

Owns the Core Domain (`04_strategize_core_domain_chart.md` §1) and the richest, fastest-changing part of the system (`03_decompose_subdomains.md`: `GuestGroup`, `Bill`, `Order`, arrival→departure coordination). This is the team every other team ultimately serves — matches the Key Insight in `02_discover_big_picture.md` §4 that the guest-service lifecycle *is* the point of the simulation.

### Kitchen Operations — Stream-aligned, not folded into Guest Experience

Kitchen has genuinely different vocabulary, its own read models, and its own rate of change (`03_decompose_subdomains.md`: "the vocabulary shifts from 'order' to 'pizza production tasks'"), plus moderate-high complexity (`04` §3: chef-assignment strategy, time-estimation policy). That's enough independent domain knowledge and pace of change to justify its own team rather than a sub-team of Guest Experience — Team Topologies' usual signal for splitting a stream. The two teams only need to agree on one narrow interface: `OrderSentToKitchen` in, `OrderAccepted` and `OrderReadyForPickup` out (`05_connect_message_flows.md` Scenario 2) — everything else each side needs is already replicated locally, so this is a low-collaboration-overhead split, not a chatty one.

### Resource Management — one team across four subdomains

Table Management, Menu Management, Waiter Management, and Chef Management are four separate *subdomains* (`03_decompose_subdomains.md` §1), each with its own reasoning for staying apart at the modelling level — `02_discover_big_picture.md` §3 explicitly keeps Waiter/Chef Management apart over their different completion rules. That's a modelling-boundary argument, not a team-sizing one. Organisationally, all four share a single actor (the `Manager`), a common configuration shape, and — for Table and Menu Management — the exact same `Closed`-only guard (`02_discover_process_level.md` §2, §3). Individually none is complex enough to justify a dedicated team (`04` §1: Generic, Generic, Supporting, Supporting); together they form one coherent "Resource Management" concern.

### Pizzeria Lifecycle — its own team, not folded into Resource Management

Pizzeria Lifecycle isn't something the Manager *configures* the way tables, menu, and staff are — it's the one piece of state every other subdomain depends on (`03_decompose_subdomains.md`: "nothing else owns 'is the pizzeria open'... every other subdomain only consumes that status, none of them decides it"). That central, cross-cutting role — and its own `OpenPizzeria` readiness check depending in turn on Resource Management's data (`05_connect_message_flows.md` §0, Scenario 4) — is different enough in kind from simple resource configuration to warrant its own small team, rather than diluting Resource Management's otherwise-uniform "Manager configures a resource" shape with a subdomain nobody configures.

---

## 4. Interaction modes

Because `05_connect_message_flows.md` §0 already commits every *subdomain* boundary to event-driven replication with no live cross-module reads, that discipline survives unchanged for every boundary that's still cross-team after grouping. Resource Management ↔ Guest Service, Resource Management ↔ Kitchen, and Resource Management ↔ Pizzeria Lifecycle are all **X-as-a-Service** — matching Team Topologies' lowest-cognitive-load interaction mode. (Pizzeria Lifecycle ↔ Kitchen isn't listed — Kitchen has no dependency on pizzeria status at all: Guest Service structurally can't send `OrderSentToKitchen` while `Closed`, since no guest group exists in that state to place one, so Kitchen never needed the guard the other three subdomains have.)

One thing *does* change once Resource Management owns all four resource subdomains: traffic that was cross-*subdomain* in `05` (e.g. `TableAssignedToWaiter`/`TableUnassignedFromWaiter` feeding Waiter Management's own Assigned Tables replica, `05` §0) becomes purely internal to Resource Management's team/BC — no longer a cross-team integration concern, just an implementation detail for step 8. This is exactly the kind of divergence `03_decompose_subdomains.md`'s own note anticipates: "a subdomain boundary and a Bounded Context boundary don't have to coincide."

Guest Experience ↔ Kitchen Operations is a separate case, not X-as-a-Service: `07_define_context_map.md` §3 classifies it Customer-Supplier (Kitchen is the Supplier the Guest Experience team, as Customer, depends on for order fulfilment). The two teams need one round of **Collaborating**-mode work to jointly design the `OrderSentToKitchen`/`OrderReadyForPickup` contract up front (Scenario 2), then fall back to a stable request/response integration once it's settled. No team needs an Enabling team's help, and no subsystem here is complicated enough to need a dedicated Complicated-subsystem team — consistent with §1's reasoning for leaving those two team types unused.

---

## Open Questions

None at this stage — team ownership is now aligned 1:1 with the Bounded Context grouping in `07_define_context_map.md`.
