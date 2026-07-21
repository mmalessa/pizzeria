# Pizzeria — Roadmap DDD

Dokument przedstawia aktualny etap odkrywania domeny oraz kolejne kroki projektowe.

## Postęp

### Wizja projektu

* ✅ `000_project_vision.md`

---

### Odkrywanie domeny

* ✅ `110_big_picture.md`
* ✅ `111_domain_decisions.md`
* ✅ `112_roles.md`

---

### Event Storming — poziom procesu

#### Główny proces domenowy

* ✅ `200_guest_service.md` — Obsługa grupy gości
* ✅ `211_guest_arrival.md` — Przyjęcie gości do lokalu
* ✅ `212_bill_management.md` — Zarządzanie rachunkiem
* ✅ `213_ordering.md` — Składanie zamówienia

#### Procesy wspierające

* ✅ `251_kitchen_order_fulfillment.md` — Realizacja zamówienia w kuchni
* ✅ `252_table_management.md` — Zarządzanie stolikami
* ⏸ `253_menu_management.md` — Zarządzanie menu
* ⏸ `254_staff_management.md` — Zarządzanie personelem
* ⏸ `255_pizzeria_lifecycle.md` — Cykl życia pizzerii

---

### Projektowanie strategiczne

* ⏸ `310_subdomains.md`
* ⏸ `311_bounded_contexts.md`
* ⏸ `312_context_map.md`
* ⏸ `313_ubiquitous_language.md`

---

### Projektowanie taktyczne

* ⏸ `320_domain_model.md`
* ⏸ `321_aggregates.md`
* ⏸ `322_entities.md`
* ⏸ `323_value_objects.md`
* ⏸ `324_domain_services.md`
* ⏸ `325_integration_events.md`

---

### Strona odczytu

* ⏸ `330_read_models.md`
* ⏸ `331_projections.md`
* ⏸ `332_queries.md`

---

### Architektura

* ⏸ `340_architecture.md`
* ⏸ `341_integrations.md`
* ⏸ `342_api.md`
* ⏸ `343_frontend.md`

---

### Rejestry decyzji architektonicznych

* ⏸ `adr/`

---

# Zasady

* Dokument jest oznaczany jako ukończony (`✅`) wyłącznie po przeglądzie i akceptacji.
* `⏳` oznacza dokument w trakcie tworzenia — szkic gotowy, ale jeszcze nieprzejrzany i niezaakceptowany.
* `⚠️` oznacza dokument domenowo kompletny, ale zawierający otwarte pytania świadomie odłożone do konsultacji spoza domeny (np. prawnej) — nie blokuje, ale nie jest w pełni zamknięty.
* Nie rozpoczynaj kolejnego etapu, dopóki bieżący nie zostanie uznany za kompletny.
* Jeśli nowa wiedza domenowa unieważnia wcześniejszy etap, wróć do tego etapu, zaktualizuj dokumentację i kontynuuj stamtąd.
