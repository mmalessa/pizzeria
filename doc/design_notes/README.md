# Design Notes

Short, ADR-style records of terminology and design decisions that came up while working through the process — not full architecture decisions, but clarifications worth keeping so later steps (and later readers) don't have to re-derive them from scratch. One file per note, `dn_NNNN.md`.

* [DN-1: Domain event vs. integration event — where's the boundary?](dn_0001.md) — domain events stay inside the Bounded Context that raised them; integration events are the only cross-context channel. Accepted 2026-07-24.
* [DN-2: Denormalised accumulators vs. idempotent, keyed tracking, for cross-aggregate consistency](dn_0002.md) — never track a derived count/sum with a directly-mutated (`+=`) field fed by another aggregate's events; track contributions keyed by source ID instead, so redelivery is a no-op. Accepted 2026-07-24.
