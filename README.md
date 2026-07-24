# Pizzeria

An architectural sandbox demonstrating the design and implementation of a system according to Domain-Driven Design principles.

## About the project

Pizzeria is an interactive simulation of a restaurant serving pizzas. Through a Web GUI, the user can take on the roles of participants in the restaurant service process — guests, a waiter, a chef, a host, and a manager — and observe the system's behaviour from the perspective of the whole pizzeria.

The goal of the project is to demonstrate the full process of domain discovery, strategic and tactical modelling, and then implementation using patterns such as Event Sourcing, Saga, Process Manager, CQRS, and CQS.

The system is built as a modular monolith composed of independent microservices, prepared for a future migration to Kubernetes.

## Documentation

The project is run according to the DDD methodology. Current progress and the upcoming stages are tracked in [doc/README.md](doc/README.md).

The `doc/` directory contains the complete documentation of the domain discovery stage — the project vision, discovered business processes, domain decisions, and identified roles.
