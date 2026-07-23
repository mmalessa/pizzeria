# Zapytania (Queries)

## Cel dokumentu

Dokument nazywa i specyfikuje operacje zapytań (queries) udostępniane konsumentom strony odczytu — sygnatury, parametry wejściowe, kształt wyniku oraz to, który model odczytu z `330_read_models.md` (zbudowany zgodnie z `331_projections.md`) każde zapytanie zwraca. Zamyka stronę odczytu (`330`–`332`).

## Granica dokumentu

`332_queries.md` opisuje **interfejs odczytu** — operacje wywoływane przez konsumenta (UI, inny Bounded Context), nie mechanizm budowania danych, które zwracają (to `331_projections.md`), ani kształt samych modeli (to `330_read_models.md`).

## Zasady wspólne dla zapytań

* Zapytanie nigdy nie modyfikuje stanu (CQS — Command-Query Separation, `000_project_vision.md`) — wyłącznie odczyt.
* Zapytanie zwraca model odczytu (lub jego fragment/widok filtrowany parametrami) — nigdy surowy agregat zapisu.
* Zapytanie jest operacją synchroniczną request/response — w odróżnieniu od zdarzeń integracyjnych (`325_integration_events.md`), które są asynchroniczne.
* Zapytanie może przyjmować proste parametry filtrujące (np. identyfikator), ale nie parametry zmieniające wynik w sposób wymagający logiki biznesowej — to należałoby do usługi domenowej (`324_domain_services.md`), nie zapytania.

---

## Zapytania — szczegółowy opis

### `GetGuestMenu()`

**Konsument:** `GuestGroup` (wybór pozycji, `213_ordering.md`).

**Wejście:** brak.

**Wyjście:** `MenuForGuests[]`.

### `GetKitchenMenu()`

**Konsument:** `Kitchen` (receptury do przygotowania pizz, `251_kitchen_order_fulfillment.md`).

**Wejście:** brak.

**Wyjście:** `MenuForKitchen[]`.

### `GetFullMenu()`

**Konsument:** `Manager` (`253_menu_management.md`).

**Wejście:** brak.

**Wyjście:** `MenuManagementView[]`.

### `GetDiningRoomOverview()`

**Konsument:** perspektywa „Sala Jadalna", `Manager` (`252_table_management.md`).

**Wejście:** brak.

**Wyjście:** `DiningRoomView[]`.

### `GetStaffOverview()`

**Konsument:** `Manager` (`254_staff_management.md`).

**Wejście:** brak.

**Wyjście:** `StaffView`.

### `GetKitchenOverview()`

**Konsument:** perspektywa „Kuchnia" (`000_project_vision.md`).

**Wejście:** brak.

**Wyjście:** `KitchenQueueView`.

### `GetPizzeriaStatus()`

**Konsument:** `Host`, `Waiter`, `Kitchen`, `Manager` — każdy, kto musi sprawdzić stan pizzerii przed akcją (`255_pizzeria_lifecycle.md`).

**Wejście:** brak.

**Wyjście:** `PizzeriaStatusView`.

**Uwaga:** poza `Pizzeria Lifecycle` ta operacja czyta lokalną kopię statusu utrzymywaną wewnątrz własnego kontekstu konsumenta (`Guest Service`, `Kitchen`, `Resource Management`), zasilaną integracyjnymi `PizzeriaOpened`/`PizzeriaClosingStarted`/`PizzeriaClosed` (`325_integration_events.md`, `331_projections.md`) — nie wywołuje `Pizzeria Lifecycle` przez sieć.

### `GetGuestVisit(guestGroupId)`

**Konsument:** `GuestGroup` (podgląd własnej wizyty i rachunku), `Waiter` (kontekst obsługi).

**Wejście:** `guestGroupId: GuestGroupId`.

**Wyjście:** `GuestVisitView`.

**Uwaga:** jedyne zapytanie, którego pełna specyfikacja czeka na projekt sagi obsługi gości (`340_architecture.md`) — patrz `331_projections.md`.

### `EstimateOrderWaitTime(orderId)`

**Konsument:** `Waiter` (informowanie gości o czasie oczekiwania, `213_ordering.md`, OQ-007 w `111_domain_decisions.md`).

**Wejście:** `orderId: OrderId`.

**Wyjście:** szacowany czas realizacji (w jednostkach symulacji).

**Uwaga:** w odróżnieniu od pozostałych zapytań, nie czyta gotowego, materializowanego modelu odczytu — wywołuje `KitchenTimeEstimationPolicy` (`324_domain_services.md`) na żywo, przekazując zamówienie oraz aktualny stan `KitchenQueueView` (obciążenie kolejki, liczba aktywnych kucharzy). Wynik nigdy nie jest zapisywany — jest liczony na nowo przy każdym wywołaniu (`111_domain_decisions.md`, OQ-007: szacowany czas nie jest częścią modelu zamówienia).

---

## Decyzje ostateczne

* ✅ **Czy każde zapytanie odpowiada dokładnie jednemu modelowi odczytu?** Prawie — wszystkie poza `EstimateOrderWaitTime`, które jest zapytaniem obliczeniowym (wywołuje usługę domenową na podstawie bieżących danych z `KitchenQueueView`), a nie odczytem gotowej projekcji.
* ✅ **Czy zapytania mogą przyjmować parametry zmieniające logikę filtrowania w sposób biznesowy?** Nie zaprojektowano takich w tym dokumencie — jedyny parametr identyfikujący to `guestGroupId`/`orderId`, czysta identyfikacja rekordu, nie reguła biznesowa.

## Pytania do dalszej analizy

* Ostateczny kształt `GetGuestVisit` zależy od projektu sagi obsługi gości w `340_architecture.md` (patrz `330_read_models.md`, `331_projections.md`).
