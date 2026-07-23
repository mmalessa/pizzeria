# 01. Understand

**Step in the [DDD Starter Modelling Process](https://github.com/ddd-crew/ddd-starter-modelling-process):** 1 of 8 — *Understand*.

**Purpose:** align the modelling effort with the business model, user needs, and strategic goals, so that later architecture/design decisions can be traced back to a business reason.

This document intentionally stays at a high level. Detailed processes, roles, and domain rules are discovered in step 2 (`02_discover_*.md`) onward.

---

## 1. Project Vision

Pizzeria is an interactive simulation of a pizza restaurant, built as an **architectural sandbox** to design and implement a system according to Domain-Driven Design.

Through a Web GUI, a user can step into the roles of the people involved in serving a restaurant's guests — guests themselves, a waiter, a chef, a host, a manager — and also observe how the whole pizzeria behaves as a system.

### Problem

Build an example system that:

* walks through a full domain-discovery process,
* implements real architectural patterns (Event Sourcing, Saga, Process Manager, CQRS, CQS),
* demonstrates a modular monolith made of independent microservices,
* allows interactive simulation of domain behaviour through a Web GUI.

### Solution

Pizzeria models a simplified but complete lifecycle of serving a group of guests — from arrival, through ordering, preparation, consumption, to payment and departure.

Staff behaviour (host, waiter, chef) is automated, while the user keeps control over guest decisions and pizzeria configuration.

**Pizzeria is not a POS system or a restaurant-management system in the classical sense. Pizzeria is a simulation of domain processes, where the user can explore the consequences of architectural decisions in real time.**

---

## 2. Users & Actors

The platform serves **a single human user**, who can take on several perspectives — simultaneously and independently.

### 2.1 Human user (the only real user)

Through the Web GUI, the user can:

* act as a **Guest** (many independent guest groups at once) — decide the size of each group, place orders, ask for the bill, pay; multiple groups can be driven in parallel as independent simulations,
* act as the **Manager** — configure the pizzeria: tables, menu, staff (waiters, chefs), pizzeria status, and kitchen parameters such as pizza preparation time,
* observe the system from the **Dining Room** perspective — tables, occupancy, assigned waiters,
* observe the system from the **Kitchen** perspective — order queue, chef occupancy, preparation progress.

### 2.2 Automated actors (in-simulation roles)

Staff behaviour is fully automatic and requires no user interaction:

* **Host** — greets arriving guest groups and seats them.
* **Waiter** — serves an assigned set of tables: opens the bill, takes and relays orders, delivers food, handles payment.
* **Chef** — prepares pizzas from the kitchen's production queue.

These are not "users" in the product sense — they're autonomous roles inside the simulated domain. Their exact responsibilities, states, and collaboration are discovered in step 2 (*Discover*), not fixed here.

---

## 3. Product Goals

The Pizzeria project should enable:

* an interactive simulation of the guest-service cycle in a restaurant,
* a demonstration of Domain-Driven Design architecture (Strategic and Tactical Design),
* a showcase of patterns: Event Sourcing, Saga, Process Manager, CQRS, CQS,
* implementation as a modular monolith made of independent microservices,
* exploration of different HTTP servers/technologies within one system,
* both synchronous and asynchronous communication between services,
* message validation against schemas (JSON-Schema / AVRO / Protobuf),
* running in a containerized environment (Docker), with an eye toward a future Kubernetes migration,
* unit and integration test coverage (no TDD requirement).

---

## 4. Design Assumptions

* the domain model should stay open to growth (e.g. a future Inventory or Cashier domain),
* the system is demonstration-only — no authentication or authorization,
* microservices communicate directly (sync or async), without an internal API Gateway,
* a public API Gateway exists only for the frontend / external clients,
* each microservice may use its own internal structure and technology stack,
* CRUD is acceptable wherever there is no significant business logic,
* non-trivial business logic belongs in dedicated mechanisms (aggregates, process managers, sagas),
* integration events are the preferred way to communicate across contexts; domain events are a modelling tool, not an integration mechanism.

---

## 5. Project Values

**Intuitiveness** — code and architecture should be understandable without studying technical documentation; naming, structure, and data flow should mirror the domain language.

**Process clarity** — every stage of guest service (greeting, ordering, preparation, delivery, payment) has clearly defined boundaries of responsibility and state.

**Modularity** — system components (microservices) work independently; a change in one context should not propagate uncontrollably into others.

**Horizontal scalability** — the system is designed with a future split into independently deployable units (Kubernetes) in mind, even though it runs as a modular monolith during initial development.

---

## 6. Long-Term Vision

Eventually, Pizzeria should become an extensive architectural portfolio, used to demonstrate:

* different approaches to domain modelling,
* the evolution of a monolithic system toward a distributed one,
* different inter-service communication strategies (sync/async, messaging, event-driven),
* different databases and persistence mechanisms per microservice.

Future iterations may add an **Inventory** domain (ingredient management) and a **Cashier** domain (billing, daily reporting), without breaking the existing domain model. These are explicitly **out of scope** for the current modelling effort.

---

## Open Questions

None at this stage.
