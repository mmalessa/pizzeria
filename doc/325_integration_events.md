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

### `Guest Service` → `Kitchen`

#### `OrderSubmittedForPreparation`

**Publikowane przez:** `Order` (`Guest Service`), przy przejściu `Accepted → Submitted` (`Waiter.submit()`, `213_ordering.md`).

**Konsumowane przez:** `Kitchen` (`Kitchen`).

**Payload:** `orderId: OrderId`, `lines: OrderLine[]` (`menuItemId` + `quantity` dla każdej pozycji).

**Efekt u konsumenta (`Kitchen`):** `Kitchen.acceptOrder(orderId, lines)` — rozbija zamówienie na `PizzaTask` w stanie `Pending` i rozpoczyna dystrybucję do dostępnych kucharzy (`251_kitchen_order_fulfillment.md`).

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

### `Guest Service` → `Pizzeria Lifecycle`

#### `GuestServiceStarted`

**Publikowane przez:** główny proces obsługi gości (`Guest Service`), w momencie `BillOpening` — rachunek nowej wizyty zostaje otwarty (`200_guest_service.md`, `212_bill_management.md`).

**Konsumowane przez:** `Pizzeria Lifecycle` — zasila lokalny licznik aktualnie trwających wizyt używany przez `PizzeriaClosingPolicy` (`324_domain_services.md`).

**Payload:** identyfikator rozpoczętej wizyty (dokładny kształt do ustalenia razem z sagą — `340_architecture.md`).

**Efekt u konsumenta:** licznik aktywnych wizyt w `Pizzeria Lifecycle` zwiększa się o 1. Samo to zdarzenie **nie** wyzwala ponownej ewaluacji `PizzeriaClosingPolicy` — rozpoczęcie wizyty nigdy nie może spowodować przejścia `Closing → Closed`, tylko oddalić je.

#### `GuestServiceCompleted`

**Publikowane przez:** główny proces obsługi gości (`Guest Service`) — nie przez `Bill` ani `Order` bezpośrednio — gdy rachunek danej wizyty jest `Closed` i wszystkie jej zamówienia są `Delivered` (`200_guest_service.md`, `212_bill_management.md`). Dokładny mechanizm publikacji (np. handler procesu nasłuchujący wewnętrznych `BillClosed`/`OrderDelivered` agregatów `Bill`/`Order`, `331_projections.md`) zostanie doprecyzowany razem z projektem sagi w `340_architecture.md`.

**Konsumowane przez:** handler `PizzeriaClosingPolicy` w `Pizzeria Lifecycle`.

**Payload:** identyfikator zakończonej wizyty (dokładny kształt do ustalenia razem z sagą — `340_architecture.md`).

**Efekt u konsumenta:** licznik aktywnych wizyt zmniejsza się o 1, a następnie następuje ponowna ewaluacja `PizzeriaClosingPolicy` (`324_domain_services.md`); jeśli zwróci `true` i `Pizzeria.status = Closing`, wywołane zostaje przejście `Closing → Closed`.

**Uwaga:** ta para zdarzeń (`GuestServiceStarted`/`GuestServiceCompleted`) zastępuje wcześniejszą bezpośrednią konsumpcję `BillClosed`/`OrderDelivered` przez `Pizzeria Lifecycle` i jest tym samym wzorcem „start/koniec zasilający lokalny licznik", co `PizzaTaskStarted`/`PizzaTaskReady` przy `ChefTerminationPolicy` — bez pary start/koniec nie da się stwierdzić, że licznik wrócił do zera, samo zdarzenie zakończenia by to uniemożliwiło. `Bill` i `Order` nadal publikują `BillClosed`/`OrderDelivered`, ale wyłącznie jako zdarzenia wewnętrzne `Guest Service` (nadal zasilają `GuestVisitView`, `331_projections.md`) — nie przekraczają już granicy kontekstu, ponieważ ich jedynym zewnętrznym konsumentem był `PizzeriaClosingPolicy`. Uzasadnienie tej pary zdarzeń wobec `Pizzeria Lifecycle`: `Pizzeria Lifecycle` nie powinien znać wewnętrznego rozbicia `Guest Service` na `Bill` i `Order` — potrzebuje wyłącznie dwóch faktów na poziomie procesu („wizyta się zaczęła" / „zobowiązania tej wizyty wobec Guest Service są zakończone"), analogicznie do „kamieni milowych", których `GuestVisitView` już używa zamiast surowych zdarzeń agregatów (`331_projections.md`).

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

**Efekt u konsumenta:** `ChefTerminationPolicy` (`324_domain_services.md`) oznacza w lokalnej projekcji, że `chefId` jest aktualnie zajęty — bez tego `canCompleteTermination(chefId)` musiałby bezpośrednio odpytywać agregat `Kitchen` w hot-pathcie, czego ten mechanizm ma uniknąć (nie ma to związku z tym, że Resource Management jest upstream względem Kitchen w relacji dot. menu/personelu — `312_context_map.md`, sekcja „Ważne rozróżnienie").

#### `PizzaTaskReady`

**Publikowane przez:** `PizzaTask` (`Kitchen`), przy przejściu `InPreparation → Ready` (`Chef.reportReady()`, `251_kitchen_order_fulfillment.md`).

**Konsumowane przez:** `Resource Management`.

**Payload:** `chefId: ChefId`, `pizzaTaskId: PizzaTaskId`.

**Efekt u konsumenta:** `ChefTerminationPolicy` oznacza `chefId` jako wolnego w lokalnej projekcji — `canCompleteTermination(chefId)` zwraca `true`, gdy `chefId` nie jest oznaczony jako zajęty.

---

### `Pizzeria Lifecycle` → `Guest Service` / `Kitchen` / `Resource Management`

#### `PizzeriaOpened`

**Publikowane przez:** `Pizzeria` (`Pizzeria Lifecycle`), przy przejściu `Closed → Open` (`Manager` otwiera pizzerię, `255_pizzeria_lifecycle.md`).

**Konsumowane przez:** `Guest Service`, `Kitchen`, `Resource Management`.

**Payload:** brak — sam fakt zajścia zdarzenia niesie całą potrzebną informację.

**Efekt u konsumenta:** każdy z trzech kontekstów aktualizuje własną, lokalną kopię statusu pizzerii (`status = Open`), używaną do bramkowania własnych akcji (`Host` przy przyjęciu gości, `Waiter`/`Kitchen` przy zamówieniach, `Manager` przy zmianach konfiguracji) bez synchronicznego odpytywania `Pizzeria Lifecycle` przy każdej z nich.

#### `PizzeriaClosingStarted`

**Publikowane przez:** `Pizzeria` (`Pizzeria Lifecycle`), przy przejściu `Open → Closing` (`Manager` inicjuje zamykanie, `255_pizzeria_lifecycle.md`).

**Konsumowane przez:** `Guest Service`, `Kitchen`, `Resource Management`.

**Payload:** brak.

**Efekt u konsumenta:** aktualizacja lokalnej kopii statusu (`status = Closing`) — od tego momentu `Host` przestaje przyjmować nowe grupy gości, a `Waiter`/`Kitchen` nadal obsługują istniejące rachunki i zamówienia (`255_pizzeria_lifecycle.md`).

#### `PizzeriaClosed`

**Publikowane przez:** `Pizzeria` (`Pizzeria Lifecycle`), przy przejściu `Closing → Closed`, wywołanym automatycznie przez ponowną ewaluację `PizzeriaClosingPolicy` (`324_domain_services.md`).

**Konsumowane przez:** `Guest Service`, `Kitchen`, `Resource Management`.

**Payload:** brak.

**Efekt u konsumenta:** aktualizacja lokalnej kopii statusu (`status = Closed`) — `Manager` może od teraz swobodnie modyfikować menu (`253_menu_management.md`), personel i konfigurację stolików.

**Uwaga:** te trzy zdarzenia są jednocześnie zdarzeniami wewnętrznymi zasilającymi `PizzeriaStatusView` w samym `Pizzeria Lifecycle` (`331_projections.md`) — jeden fakt, wielu konsumentów, ten sam wzorzec co `TableReleased` czy `OrderSubmittedForPreparation`.

---

## Poza zakresem

* **`Resource Management` → `Guest Service` / `Kitchen` (dane o stolikach, menu, personelu) — kierunek główny.** `312_context_map.md` opisuje tę relację jako **Open Host Service + Published Language**, nie jako zdarzenia integracyjne — konsumenci odpytują Resource Management na żądanie (np. `TableAssignmentPolicy` odpytuje `Table`/`Waiter` na żądanie, `324_domain_services.md`). Nie definiujemy tu zdarzeń w rodzaju `MenuItemStatusChanged` czy `WaiterStatusChanged`, ponieważ żaden wcześniejszy dokument nie wymaga reaktywnego powiadamiania w tym kierunku — tylko odczytu aktualnego stanu w momencie działania. (Kitchen, niezależnie, publikuje `PizzaTaskStarted`/`PizzaTaskReady`, konsumowane przez Resource Management — zdefiniowane wyżej; to osobna potrzeba integracyjna, niezwiązana z tym punktem.)
* **`PizzeriaOpeningPolicy` odczytuje `Resource Management` (liczbę aktywnych kelnerów, kucharzy i stolików).** Pizzeria Lifecycle jest upstream względem Resource Management w relacji dot. stanu pizzerii (`312_context_map.md`), ale to nie ogranicza, jakie dane Pizzeria Lifecycle może odczytać z Resource Management — upstream/downstream opisuje, czyj model jest autorytatywny, nie dozwolony kierunek zapytań (`312_context_map.md`, sekcja „Ważne rozróżnienie"). W odróżnieniu od `ChefTerminationPolicy`, tego odczytu **nie** modelujemy jako zdarzenie: otwarcie pizzerii jest rzadką, świadomą akcją Managera (raz na „dzień" symulacji), a nie operacją wykonywaną w hot-pathcie (jak przyjęcie gościa czy zwolnienie pracownika). Utrzymywanie trwałej lokalnej projekcji liczby aktywnego personelu i stolików wyłącznie dla tego jednego, rzadkiego sprawdzenia byłoby nadmiarowe. Modelujemy to jako odczyt na żądanie (Open Host Service) — Resource Management udostępnia go Pizzeria Lifecycle na tej samej zasadzie, na jakiej już udostępnia dane Guest Service i Kitchen.

---

## Decyzje ostateczne

* ✅ **Czy każde „zdarzenie domenowe" z wcześniejszych dokumentów jest zdarzeniem integracyjnym?** Nie. Tylko te przekraczające granicę Bounded Contextu — zgodnie z zasadą z `000_project_vision.md` rozróżniającą zdarzenia domenowe (narzędzie modelowania) od integracyjnych (mechanizm integracji).
* ✅ **Czy `Order` potrzebuje osobnego zdarzenia `OrderPreparationStarted`, skoro kuchnia przyjmuje zamówienie automatycznie?** Tak. Mimo że przyjęcie jest automatyczne (bez interakcji użytkownika), `Kitchen` i `Order` to osobne agregaty w osobnych kontekstach — przejście `Submitted → InPreparation` w `Order` wciąż wymaga własnej transakcji wyzwalanej zdarzeniem, a nie bezpośredniego wywołania.
* ✅ **Czy Resource Management publikuje zdarzenia integracyjne do Guest Service/Kitchen?** Nie w głównym kierunku tej relacji. Relacja jest Open Host Service (odczyt na żądanie), zgodnie z `312_context_map.md`.
* ✅ **Czy Pizzeria Lifecycle rozgłasza swoje przejścia stanu do innych kontekstów?** Tak — `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed`. Pierwotnie uznano to za niepotrzebne (każdy konsument sprawdzał status na żądanie w chwili własnej akcji, co wystarczało funkcjonalnie). Decyzję odwrócono: status pizzerii bramkuje realne decyzje biznesowe w trzech innych kontekstach (`Guest Service`, `Kitchen`, `Resource Management` — w tym teraz też każdą operację na menu, `253_menu_management.md`), więc świadomie unikamy sprzężenia przez synchroniczne odpytywanie `Pizzeria Lifecycle` przy każdej takiej akcji. Każdy z trzech kontekstów utrzymuje własną, lokalną kopię statusu, zasilaną tymi zdarzeniami — ten sam kompromis (kilka dodatkowych zdarzeń w zamian za brak bezpośredniego sprzężenia między modułami), co przy `ChefTerminationPolicy`. Zdarzenia te są jednocześnie wewnętrznymi zdarzeniami zasilającymi `PizzeriaStatusView` (`331_projections.md`) — jeden fakt, wielu konsumentów.
* ✅ **Czy pierwsza wersja tego dokumentu ujęła wszystkie potrzebne zdarzenia?** Nie. Weryfikacja wykazała, że usługi domenowe z `324_domain_services.md` (`ChefTerminationPolicy`, `PizzeriaOpeningPolicy`) potrzebowały danych operacyjnych z kontekstu, względem którego ich własny kontekst jest upstreamem (Resource Management potrzebuje danych z Kitchen; Pizzeria Lifecycle potrzebuje danych z Resource Management). To nie jest sprzeczne z `312_context_map.md` — upstream/downstream opisuje autorytet modelu, nie dozwolony kierunek zapytań/zdarzeń (patrz „Ważne rozróżnienie" w `312_context_map.md`). Dla `ChefTerminationPolicy` dodano zdarzenia integracyjne (`PizzaTaskStarted`/`PizzaTaskReady`), zasilające lokalną projekcję zamiast odpytywania na żywo — wybór podyktowany częstotliwością operacji (hot-path), nie kierunkiem zależności. Dla `PizzeriaOpeningPolicy` świadomie zostawiono odczyt na żądanie (rzadka operacja, nieopłacalna do modelowania jako trwała projekcja) — udokumentowane w sekcji „Poza zakresem". (Pierwotnie zidentyfikowano też podobną potrzebę dla `MenuItemDisablingPolicy` — usługa ta została od tego czasu usunięta całkowicie, patrz niżej.)
* ✅ **Czy `312_context_map.md` wymaga aktualizacji w związku z tymi ustaleniami?** Tak — dodatkowa komunikacja (Kitchen → Resource Management oraz odczyt Pizzeria Lifecycle → Resource Management) została dopisana do odpowiednich relacji w `312_context_map.md`, razem z jawnym rozróżnieniem między kierunkiem zależności modelu a kierunkiem komunikatów.
* ✅ **Czy `OrderSubmittedForPreparation` i `OrderDelivered` są nadal konsumowane przez `Resource Management`?** Nie, już nie. Wcześniej zasilały lokalną projekcję `MenuItemDisablingPolicy` (`324_domain_services.md`), usuniętą po ograniczeniu wszystkich zmian menu wyłącznie do stanu `Closed` pizzerii (`253_menu_management.md`) — w tym stanie nie istnieją żadne aktywne zamówienia, więc warunek, który ta projekcja sprawdzała, jest zawsze trywialnie spełniony. `OrderSubmittedForPreparation` nadal istnieje dla swojego pierwotnego konsumenta (`Kitchen`). `OrderDelivered` straciło też swojego drugiego konsumenta (`Pizzeria Lifecycle`) — patrz decyzja poniżej o `GuestServiceCompleted` — i pozostało wyłącznie zdarzeniem wewnętrznym `Guest Service`.
* ✅ **Czy `Pizzeria Lifecycle` powinien konsumować `BillClosed` i `OrderDelivered` bezpośrednio?** Nie, już nie — zastąpione przez `GuestServiceCompleted`. Bezpośrednia konsumpcja tych dwóch zdarzeń wymagała, aby `Pizzeria Lifecycle` rozumiał wewnętrzne rozbicie `Guest Service` na `Bill` i `Order` — a ich jedynym zewnętrznym konsumentem był `PizzeriaClosingPolicy`. Zamiast tego główny proces obsługi gości publikuje jeden kamień milowy, `GuestServiceCompleted`, gdy zobowiązania danej wizyty wobec `Guest Service` (rachunek zamknięty, wszystkie zamówienia dostarczone) są spełnione — ten sam wzorzec „kamieni milowych procesu", którego `GuestVisitView` już używa (`331_projections.md`). `BillClosed` i `OrderDelivered` pozostają zdarzeniami wewnętrznymi `Guest Service`, nadal zasilającymi `GuestVisitView`. `TableReleased` (z `Resource Management`) zostaje bez zmian jako drugi trigger `PizzeriaClosingPolicy` — było już właściwie zakresowe, nie ujawniało wewnętrznej struktury żadnego kontekstu.
* ✅ **Czy samo `GuestServiceCompleted` wystarczy, żeby `PizzeriaClosingPolicy` wiedział, że żadna wizyta nie jest w toku?** Nie. Strumień samych zdarzeń zakończenia nie pozwala nigdy stwierdzić, że licznik aktywnych wizyt wrócił do zera — trzeba też wiedzieć, ile się zaczęło. Dodano `GuestServiceStarted` (publikowane przy `BillOpening`) jako parę do `GuestServiceCompleted` — dokładnie ten sam wzorzec „start/koniec zasilający lokalny licznik", którego już używamy dla `PizzaTaskStarted`/`PizzaTaskReady` przy `ChefTerminationPolicy` (`324_domain_services.md`). `GuestServiceStarted` samo w sobie nie wyzwala ponownej ewaluacji polityki (rozpoczęcie wizyty nie może doprowadzić do zamknięcia pizzerii) — tylko zwiększa licznik; ewaluację wyzwalają `GuestServiceCompleted` i `TableReleased`.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
