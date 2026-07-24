# 06. Organise — Hypothetical Team Ownership

**Step in the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process):** 6 of 8 — *Organise*.

**Purpose:** in a real setting, form autonomous teams aligned with context boundaries (Team Topologies, Conway's Law).

**Simplification note:** this is a **solo project with no organisation behind it** — there are no teams to align, and no reorganisation to plan. This document records *conceptually* which team would plausibly own each subdomain if Pizzeria were a real product with a real engineering org, purely as useful context for later "as if this were real" discussions. It isn't a real team-design exercise, and doesn't gate or block step 7 (*Define*) in any way.

**Key question:** *if this were a real organisation, which team would own which part of the domain, and how would they need to interact?*

**Note on granularity:** `doc/README.md` describes this artifact as "per bounded context," but Bounded Contexts aren't decided until step 7 (`03_decompose_subdomains.md` §5 explicitly defers that grouping). This document is organised by **subdomain** instead, using the boundaries from `03_decompose_subdomains.md` §1. Once step 7 groups subdomains into actual Bounded Contexts, team ownership below may need a light revisit — noted as an open question at the end.

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
| **Staffing & Lifecycle** | Stream-aligned | Waiter Management, Chef Management, Pizzeria Lifecycle | Supporting |
| **Configuration Platform** | Platform | Table Management, Menu Management | Generic |

---

## 3. Rationale

### Guest Experience — Stream-aligned

Owns the Core Domain (`04_strategize_core_domain_chart.md` §1) and the richest, fastest-changing part of the system (`03_decompose_subdomains.md`: `GuestGroup`, `Bill`, `Order`, arrival→departure coordination). This is the team every other team ultimately serves — matches the Key Insight in `02_discover_big_picture.md` §4 that the guest-service lifecycle *is* the point of the simulation.

### Kitchen Operations — Stream-aligned, not folded into Guest Experience

Kitchen has genuinely different vocabulary, its own read models, and its own rate of change (`03_decompose_subdomains.md`: "the vocabulary shifts from 'order' to 'pizza production tasks'"), plus moderate-high complexity (`04` §3: chef-assignment strategy, time-estimation policy). That's enough independent domain knowledge and pace of change to justify its own team rather than a sub-team of Guest Experience — Team Topologies' usual signal for splitting a stream. The two teams only need to agree on one narrow interface: `OrderSentToKitchen` in, `OrderReadyForPickup` out (`05_connect_message_flows.md` Scenario 2) — everything else each side needs is already replicated locally, so this is a low-collaboration-overhead split, not a chatty one.

### Staffing & Lifecycle — one team across three subdomains

Waiter Management, Chef Management, and Pizzeria Lifecycle are three separate *subdomains* (`03_decompose_subdomains.md` deliberately keeps Waiter/Chef Management apart — different completion rules, `02_discover_big_picture.md` §3), but that's a modelling-boundary argument, not a team-sizing one. Organisationally, all three are thin, related, Supporting-classified concerns — hire/terminate lifecycles plus the pizzeria-wide open/closed gate that reads their readiness (`02_discover_process_level.md` §6, `05_connect_message_flows.md` Scenario 4/6). None of the three is complex enough alone to justify a dedicated team in an org this size; together they form one coherent, if narrow, area of ownership. Team Topologies explicitly allows one team to own multiple bounded contexts as long as cognitive load stays manageable — this qualifies.

### Configuration Platform — Platform team

Table Management and Menu Management are both Generic (`04` §1), and `04` §4 explicitly calls them out as "prime candidates for an off-the-shelf/generic solution" — the textbook profile for a Platform team: low differentiation, mostly CRUD-shaped, consumed as a service by everyone else rather than driving the product's direction. Grouping them keeps the platform surface small and avoids spinning up a team for what's essentially two well-guarded configuration APIs (`02_discover_process_level.md` §2, §3 — both now share the same `Closed`-only guard).

---

## 4. Interaction modes

Because `05_connect_message_flows.md` §0 already commits the whole system to **event-driven replication with no live cross-module reads**, the organisational interaction mode falls out almost for free: every team boundary above is **X-as-a-Service** (one team publishes integration events; every consumer replicates independently, on its own schedule) — matching Team Topologies' lowest-cognitive-load interaction mode. There's exactly one place worth calling out for tighter, **Collaborating**-mode work: Guest Experience and Kitchen Operations would need to jointly design the `OrderSentToKitchen`/`OrderReadyForPickup` contract up front (Scenario 2), then fall back to X-as-a-Service once it's stable. No team needs an Enabling team's help, and no subsystem here is complicated enough to need a dedicated Complicated-subsystem team — consistent with §1's reasoning for leaving those two team types unused.

---

## Open Questions

* **Revisit after step 7 (*Define*):** once subdomains are grouped into actual Bounded Contexts, check whether any BC groups together subdomains this document assigned to different teams (or splits one team's subdomains across BCs) — if so, either the BC grouping or this team split should be reconsidered, per `doc/README.md`'s working agreement on looping back.
