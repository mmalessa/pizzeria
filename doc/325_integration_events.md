# Zdarzenia integracyjne (Integration Events)

## Cel dokumentu

Dokument nazywa i specyfikuje zdarzenia integracyjne — komunikaty publikowane przez agregat w jednym Bounded Contexcie i konsumowane przez agregat w innym Bounded Contexcie. Formalizuje mechanizmy spójności ostatecznej, które `321_aggregates.md`, `322_entities.md` i `324_domain_services.md` już zapowiadały jako „zdarzenie domenowe" lub „event", nadając im konkretną nazwę, payload i kierunek.

## Granica dokumentu

Zgodnie z założeniem z `000_project_vision.md` („zdarzenia integracyjne są preferowanym sposobem komunikacji między kontekstami; zdarzenia domenowe są narzędziem modelowania, nie integracji") ten dokument opisuje wyłącznie zdarzenia **przekraczające granicę Bounded Contextu**. Nie każde zdarzenie domenowe wspomniane we wcześniejszych dokumentach jest tu opisane — tylko te, które faktycznie komunikują się między kontekstami. Konkretny format serializacji, broker/mechanizm dostarczania i gwarancje dostarczenia (at-least-once, idempotencja konsumenta) są zagadnieniem architektury — `340_architecture.md`/`341_integrations.md`.

**Przykład zdarzenia wyłącznie domenowego (poza zakresem tego dokumentu):** utworzenie pojedynczego `PizzaTask` w stanie `Pending` podczas `Kitchen.acceptOrder(...)` (`322_entities.md`) jest faktem zachodzącym w całości wewnątrz agregatu `Kitchen`, w jednej transakcji. Nikt poza `Kitchen` nie musi wiedzieć o powstaniu pojedynczej pizzy w kolejce — świat zewnętrzny dowiaduje się dopiero o `OrderPreparationStarted`, gdy wszystkie `PizzaTask` dla zamówienia już istnieją. To samo zdarzenie mogłoby w przyszłości posłużyć jako element wewnętrznego Event Sourcingu dla agregatu `Kitchen` (jeden z wzorców, które projekt chce zademonstrować — `000_project_vision.md`) — byłaby to jednak decyzja architektoniczna (`340_architecture.md`), niezależna od tego, czy dane zdarzenie jest też zdarzeniem integracyjnym. Reguła jest więc taka: zdarzenie domenowe staje się zdarzeniem integracyjnym dopiero, gdy realnie potrzebuje go inny kontekst — nie automatycznie przy każdym przejściu stanu.

## Zasady wspólne dla zdarzeń integracyjnych

* Zdarzenie jest publikowane dopiero po zatwierdzeniu transakcji agregatu źródłowego — nigdy przed, aby nie ogłosić czegoś, co ostatecznie się nie wydarzyło.
* Nazwa zdarzenia jest w czasie przeszłym — opisuje fakt, który już zaszedł (`OrderDelivered`, nie `DeliverOrder`).
* Payload zawiera wyłącznie identyfikatory i dane, których faktycznie potrzebuje konsument (Published Language) — nie jest to zrzut całego stanu agregatu źródłowego.
* Konsument reaguje na zdarzenie we własnej, osobnej transakcji na swoim agregacie — publikacja zdarzenia i jego konsumpcja nigdy nie dzielą jednej transakcji (`321_aggregates.md`: „jedna transakcja = jeden agregat").
* Zdarzenie integracyjne jest publicznym kontraktem między kontekstami — zmiana jego kształtu jest zmianą kontraktu, nie wewnętrznym refaktorem, i wymaga koordynacji z konsumentami (w duchu **Published Language** z `312_context_map.md`).

---

## Zdarzenia — szczegółowy opis

### `Guest Service` → `Kitchen` (oraz `Resource Management`)

#### `OrderSubmittedForPreparation`

**Publikowane przez:** `Order` (`Guest Service`), przy przejściu `Accepted → Submitted` (`Waiter.submit()`, `213_ordering.md`).

**Konsumowane przez:** `Kitchen` (`Kitchen`); dodatkowo `Resource Management` — patrz niżej.

**Payload:** `orderId: OrderId`, `lines: OrderLine[]` (`menuItemId` + `quantity` dla każdej pozycji).

**Efekt u konsumenta (`Kitchen`):** `Kitchen.acceptOrder(orderId, lines)` — rozbija zamówienie na `PizzaTask` w stanie `Pending` i rozpoczyna dystrybucję do dostępnych kucharzy (`251_kitchen_order_fulfillment.md`).

**Efekt u konsumenta (`Resource Management`):** `MenuItemDisablingPolicy` zapisuje w lokalnej projekcji, że pozycje menu z `lines` są referencjonowane przez `orderId` jako „niedostarczone" — patrz `MenuItemDisablingPolicy` w `324_domain_services.md` i sekcja „Kierunki zwrotne" poniżej.

---

### `Kitchen` → `Guest Service`

#### `OrderPreparationStarted`

**Publikowane przez:** `Kitchen`, natychmiast po przyjęciu `OrderSubmittedForPreparation` i utworzeniu odpowiadających `PizzaTask` (`251_kitchen_order_fulfillment.md`: kuchnia przyjmuje zamówienie automatycznie, bez osobnej interakcji).

**Konsumowane przez:** `Order` (`Guest Service`).

**Payload:** `orderId: OrderId`.

**Efekt u konsumenta:** `Order.startPreparation()` — przejście `Submitted → InPreparation` (`322_entities.md`).

#### `OrderReadyForDelivery`

**Publikowane przez:** `Kitchen`, gdy wszystkie `PizzaTask` należące do zamówienia osiągną stan `Ready` (`251_kitchen_order_fulfillment.md`).

**Konsumowane przez:** `Order` (`Guest Service`).

**Payload:** `orderId: OrderId`.

**Efekt u konsumenta:** `Order.markReadyForDelivery()` — przejście `InPreparation → ReadyForDelivery` (`322_entities.md`). Kelner zostaje poinformowany o gotowości do odbioru (`213_ordering.md`).

---

### `Guest Service` → `Pizzeria Lifecycle` (oraz `Resource Management` dla `OrderDelivered`)

#### `BillClosed`

**Publikowane przez:** `Bill` (`Guest Service`), przy przejściu `Open → Closed` (`Waiter.close()`, `212_bill_management.md`).

**Konsumowane przez:** handler `PizzeriaClosingPolicy` w `Pizzeria Lifecycle`.

**Payload:** `billId: BillId`, `closedAt: timestamp`.

**Efekt u konsumenta:** ponowna ewaluacja `PizzeriaClosingPolicy` (`324_domain_services.md`); jeśli zwróci `true` i `Pizzeria.status = Closing`, wywołane zostaje przejście `Closing → Closed`.

#### `OrderDelivered`

**Publikowane przez:** `Order` (`Guest Service`), przy przejściu `ReadyForDelivery → Delivered` (`Waiter.deliver()`, `213_ordering.md`).

**Konsumowane przez:** handler `PizzeriaClosingPolicy` w `Pizzeria Lifecycle`; dodatkowo `Resource Management`.

**Payload:** `orderId: OrderId`.

**Efekt u konsumenta (`Pizzeria Lifecycle`):** jak w `BillClosed` — ponowna ewaluacja `PizzeriaClosingPolicy`.

**Efekt u konsumenta (`Resource Management`):** `MenuItemDisablingPolicy` usuwa `orderId` z lokalnej projekcji „niedostarczonych zamówień" dla pozycji menu zapisanych wcześniej z `OrderSubmittedForPreparation` (ten sam `orderId` jako klucz) — gdy dla danej pozycji menu nie zostaje żaden niedostarczony `orderId`, `canDisable(menuItemId)` może zwrócić `true`.

---

### `Resource Management` → `Pizzeria Lifecycle`

#### `TableReleased`

**Publikowane przez:** `Table` (`Resource Management`), przy przejściu `Occupied → Free` (`TableRelease`, `252_table_management.md`).

**Konsumowane przez:** handler `PizzeriaClosingPolicy` w `Pizzeria Lifecycle`.

**Payload:** `tableId: TableId`.

**Efekt u konsumenta:** jak wyżej — ponowna ewaluacja `PizzeriaClosingPolicy`.

---

### `Kitchen` → `Resource Management`

#### `PizzaTaskStarted`

**Publikowane przez:** `PizzaTask` (`Kitchen`), przy przejściu `Pending → InPreparation` (`Chef.assignChef(...)`, `251_kitchen_order_fulfillment.md`).

**Konsumowane przez:** `Resource Management`.

**Payload:** `chefId: ChefId`, `pizzaTaskId: PizzaTaskId`.

**Efekt u konsumenta:** `ChefTerminationPolicy` (`324_domain_services.md`) oznacza w lokalnej projekcji, że `chefId` jest aktualnie zajęty — bez tego `canCompleteTermination(chefId)` musiałby synchronicznie odpytywać agregat `Kitchen`, co jest kierunkiem odwrotnym względem relacji `Resource Management → Kitchen` udokumentowanej w `312_context_map.md`.

#### `PizzaTaskReady`

**Publikowane przez:** `PizzaTask` (`Kitchen`), przy przejściu `InPreparation → Ready` (`Chef.reportReady()`, `251_kitchen_order_fulfillment.md`).

**Konsumowane przez:** `Resource Management`.

**Payload:** `chefId: ChefId`, `pizzaTaskId: PizzaTaskId`.

**Efekt u konsumenta:** `ChefTerminationPolicy` oznacza `chefId` jako wolnego w lokalnej projekcji — `canCompleteTermination(chefId)` zwraca `true`, gdy `chefId` nie jest oznaczony jako zajęty.

---

## Poza zakresem

* **`Resource Management` → `Guest Service` / `Kitchen` (dane o stolikach, menu, personelu) — kierunek główny.** `312_context_map.md` opisuje tę relację jako **Open Host Service + Published Language**, nie jako zdarzenia asynchroniczne — konsumenci odpytują Resource Management na żądanie (np. `TableAssignmentPolicy` odpytuje `Table`/`Waiter` synchronicznie, `324_domain_services.md`). Nie definiujemy tu zdarzeń w rodzaju `MenuItemStatusChanged` czy `WaiterStatusChanged`, ponieważ żaden wcześniejszy dokument nie wymaga reaktywnego powiadamiania w tym kierunku — tylko odczytu aktualnego stanu w momencie działania. (Wąskie kanały zwrotne w przeciwnym kierunku — `PizzaTaskStarted`/`PizzaTaskReady` i konsumpcja `OrderSubmittedForPreparation`/`OrderDelivered` przez `Resource Management` — SĄ zdefiniowane wyżej; nie dotyczą tego punktu.)
* **`Pizzeria Lifecycle` → pozostałe konteksty (rozgłaszanie `Open`/`Closing`/`Closed`).** Relacja jest **Conformist** (`312_context_map.md`), ale wszystkie miejsca, które sprawdzają stan pizzerii (`Host` przy przyjęciu gości, `Kitchen` przy nowych zamówieniach, `Manager` przy zmianach konfiguracji), robią to jako odczyt stanu w momencie działania, a nie jako reakcję na wcześniej otrzymane zdarzenie. Wprowadzenie zdarzeń rozgłoszeniowych `PizzeriaOpened`/`PizzeriaClosingStarted`/`PizzeriaClosed` nie jest dziś niczym motywowane — pozostaje możliwym rozszerzeniem na etapie architektury, gdyby lokalne odczyty synchroniczne okazały się niewystarczające przy podziale na mikrousługi.
* **`PizzeriaOpeningPolicy` odczytuje `Resource Management` (liczbę aktywnych kelnerów, kucharzy i stolików) — kierunek odwrotny względem `Pizzeria Lifecycle → Resource Management` z `312_context_map.md`.** W odróżnieniu od `ChefTerminationPolicy` i `MenuItemDisablingPolicy`, tego odczytu **nie** modelujemy jako zdarzenie: otwarcie pizzerii jest rzadką, świadomą akcją Managera (raz na „dzień" symulacji), a nie operacją wykonywaną w hot-pathcie (jak przyjęcie gościa czy zwolnienie pracownika). Utrzymywanie trwałej lokalnej projekcji liczby aktywnego personelu i stolików wyłącznie dla tego jednego, rzadkiego sprawdzenia byłoby nadmiarowe. Modelujemy to jako synchroniczny odczyt (Open Host Service) — Resource Management udostępnia go Pizzeria Lifecycle na tej samej zasadzie, na jakiej już udostępnia dane Guest Service i Kitchen.

---

## Decyzje ostateczne

* ✅ **Czy każde „zdarzenie domenowe" z wcześniejszych dokumentów jest zdarzeniem integracyjnym?** Nie. Tylko te przekraczające granicę Bounded Contextu — zgodnie z zasadą z `000_project_vision.md` rozróżniającą zdarzenia domenowe (narzędzie modelowania) od integracyjnych (mechanizm integracji).
* ✅ **Czy `Order` potrzebuje osobnego zdarzenia `OrderPreparationStarted`, skoro kuchnia przyjmuje zamówienie automatycznie?** Tak. Mimo że przyjęcie jest automatyczne (bez interakcji użytkownika), `Kitchen` i `Order` to osobne agregaty w osobnych kontekstach — przejście `Submitted → InPreparation` w `Order` wciąż wymaga własnej transakcji wyzwalanej zdarzeniem, a nie bezpośredniego wywołania.
* ✅ **Czy Resource Management publikuje zdarzenia integracyjne do Guest Service/Kitchen?** Nie w głównym kierunku tej relacji. Relacja jest Open Host Service (odczyt na żądanie), zgodnie z `312_context_map.md`.
* ✅ **Czy Pizzeria Lifecycle rozgłasza swoje przejścia stanu do innych kontekstów?** Nie zdefiniowano takich zdarzeń — wszystkie zależne kontrole odczytują stan pizzerii synchronicznie w momencie działania, co jest wystarczające przy obecnym zakresie modelu.
* ✅ **Czy pierwsza wersja tego dokumentu ujęła wszystkie potrzebne zdarzenia?** Nie. Weryfikacja wykazała, że trzy usługi domenowe z `324_domain_services.md` (`ChefTerminationPolicy`, `MenuItemDisablingPolicy`, `PizzeriaOpeningPolicy`) wymagały odczytu danych w kierunku **odwrotnym** do zależności udokumentowanych w `312_context_map.md` (Resource Management miałoby odpytywać Kitchen; Resource Management miałoby odpytywać Guest Service/Order; Pizzeria Lifecycle miałoby odpytywać Resource Management). Dla dwóch pierwszych dodano wąskie zdarzenia zwrotne (`PizzaTaskStarted`/`PizzaTaskReady`; dodatkowa konsumpcja `OrderSubmittedForPreparation`/`OrderDelivered` przez Resource Management), utrzymujące lokalną projekcję zamiast odpytywania na żywo. Dla `PizzeriaOpeningPolicy` świadomie zostawiono synchroniczny odczyt (rzadka operacja, nieopłacalna do modelowania jako trwała projekcja) — udokumentowane w sekcji „Poza zakresem".
* ✅ **Czy `312_context_map.md` wymaga aktualizacji w związku z tymi ustaleniami?** Tak — brakujące kanały zwrotne (Kitchen → Resource Management, Guest Service → Resource Management, oraz odwrotny odczyt Pizzeria Lifecycle → Resource Management) zostały dopisane do odpowiednich relacji w `312_context_map.md`.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
