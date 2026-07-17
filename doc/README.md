# Pizzeria — Roadmap DDD

Dokument przedstawia aktualny etap odkrywania domeny oraz kolejne kroki projektowe.

## Postęp

### Wizja projektu

* ✅ `000_project_vision.md`

---

### Odkrywanie domeny

* ⏳ `010_big_picture.md`
* ⏳ `011_domain_decisions.md`
* ⏳ `012_roles.md`

---

### Event Storming — poziom procesu

* ⏸ `020_process_guest_arrival.md` — Przyjęcie gości do lokalu
* ⏸ `021_process_ordering.md` — Zamówienie i przygotowanie pizzy
* ⏸ `022_process_extension.md` — Rozszerzenie zamówienia
* ⏸ `023_process_payment.md` — Płatność i opuszczenie lokalu
* ⏸ `024_process_staff_management.md` — Zarządzanie personelem
* ⏸ `025_process_menu_management.md` — Zarządzanie menu
* ⏸ `026_process_table_management.md` — Zarządzanie stolikami

---

### Projektowanie strategiczne

* ⏸ `030_subdomains.md`
* ⏸ `031_bounded_contexts.md`
* ⏸ `032_context_map.md`
* ⏸ `033_ubiquitous_language.md`

---

### Projektowanie taktyczne

* ⏸ `040_domain_model.md`
* ⏸ `041_aggregates.md`
* ⏸ `042_entities.md`
* ⏸ `043_value_objects.md`
* ⏸ `044_domain_services.md`
* ⏸ `045_integration_events.md`

---

### Strona odczytu

* ⏸ `050_read_models.md`
* ⏸ `051_projections.md`
* ⏸ `052_queries.md`

---

### Architektura

* ⏸ `060_architecture.md`
* ⏸ `061_integrations.md`
* ⏸ `062_api.md`
* ⏸ `063_frontend.md`

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
