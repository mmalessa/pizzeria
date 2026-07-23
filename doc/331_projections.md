# Projekcje (Projections)

## Cel dokumentu

Dokument opisuje, jak każdy model odczytu z `330_read_models.md` jest budowany — jakie zdarzenia go zasilają, jak handler projekcji reaguje na każde z nich, oraz jak wygląda odbudowa i spójność.

## Granica dokumentu

`331_projections.md` opisuje mechanizm aktualizacji modeli odczytu. Nie definiuje ponownie pól modeli (`330_read_models.md`) ani nie opisuje operacji zapytań udostępnianych konsumentom (`332_queries.md`). W tym dokumencie nazywamy też, po raz pierwszy, **wewnętrzne zdarzenia domenowe** poszczególnych agregatów zapisu — potrzebne wyłącznie do zasilania projekcji wewnątrzkontekstowych. To odrębna kategoria od zdarzeń integracyjnych z `325_integration_events.md`: zdarzenia integracyjne przekraczają granicę Bounded Contextu, zdarzenia wewnętrzne opisane tutaj nie wychodzą poza kontekst właściciela (zgodnie z zasadą z `000_project_vision.md` i przykładem już podanym w `325_integration_events.md` dla `PizzaTask`).

## Zasady wspólne dla projekcji

* Handler projekcji jest idempotentny — ponowne dostarczenie tego samego zdarzenia (at-least-once) nie może podwoić efektu.
* Projekcja jest aktualizowana asynchronicznie, po zatwierdzeniu zmiany w agregacie źródłowym — nigdy w tej samej transakcji.
* Projekcję można w pełni odbudować, odtwarzając zdarzenia źródłowe od początku (rebuild) — model odczytu nie jest źródłem prawdy, jest materializowanym skrótem.
* Kolejność zdarzeń dotyczących tego samego bytu (np. dwóch zmian tej samej pozycji menu) musi być zachowana przy aktualizacji projekcji; zdarzenia dotyczące różnych bytów mogą być przetwarzane niezależnie.

---

## Projekcje wewnątrzkontekstowe

### `MenuForGuests`, `MenuForKitchen`, `MenuManagementView` (`Resource Management`)

**Zdarzenia źródłowe (wewnętrzne, `MenuItem`):** `MenuItemCreated`, `MenuItemDescriptionChanged`, `MenuItemPriceChanged`, `MenuItemRetired`, `MenuItemReactivated`, `MenuItemDisabled`.

**Logika:** wszystkie trzy modele są aktualizowane tymi samymi zdarzeniami, różnią się wyłącznie filtrem zastosowanym przy odczycie (patrz `330_read_models.md`) — mogą być realizowane jako jedna tabela/kolekcja z polem `status`, czytana z różnymi warunkami `WHERE`, zamiast trzech osobnych fizycznych projekcji.

### `DiningRoomView` (`Resource Management`)

**Zdarzenia źródłowe (wewnętrzne, `Table`):** `TableCreated`, `TableRenamed`, `TableCapacityChanged`, `TableWaiterAssigned`, `TableOccupied`, `TableReleased` (to samo zdarzenie, które jest też zdarzeniem integracyjnym do `Pizzeria Lifecycle` w `325_integration_events.md` — jeden fakt, wielu konsumentów, ten sam wzorzec co `OrderSubmittedForPreparation`).

**Logika:** `TableWaiterAssigned` wymaga dociągnięcia `waiterName` — odczyt z `Waiter` w tym samym kontekście (bez problemu granicy, oba agregaty są w `Resource Management`).

### `StaffView` (`Resource Management`)

**Zdarzenia źródłowe (wewnętrzne):** `WaiterHired`, `WaiterTerminatingStarted`, `WaiterTerminated`, `WaiterRehired`, `ChefHired`, `ChefTerminatingStarted`, `ChefTerminated`, `ChefRehired`, oraz te same zdarzenia `Table` co dla `DiningRoomView` (do wyliczenia `assignedTableNames` per kelner).

**Logika:** `assignedTableNames[waiterId]` jest utrzymywane jako odwrotny indeks budowany z `TableWaiterAssigned` — dokładnie ta materializacja odwrotnej relacji, której świadomie brakuje w agregacie `Waiter` (`321_aggregates.md`).

### `KitchenQueueView` (`Kitchen`)

**Zdarzenia źródłowe:** wewnętrzne `PizzaTaskCreated` (przy `Kitchen.acceptOrder(...)`, patrz przykład w `325_integration_events.md`), `PizzaTaskStarted`, `PizzaTaskReady` (te dwa są jednocześnie zdarzeniami integracyjnymi do `Resource Management` — ponownie jeden fakt, wielu konsumentów).

**Logika:** `orderProgress[orderId]` jest wyliczane jako `count(pizzaTasks gdzie orderId=X i status=Ready) / count(pizzaTasks gdzie orderId=X)`. Pole `chefName` w liście `chefs[]` wymaga danych z `Resource Management` (imię/identyfikator kucharza) — ponieważ `Chef` jako zasób personelu należy do `Resource Management` (`320_domain_model.md`), `Kitchen` pobiera tę nazwę przez Open Host Service (odczyt na żądanie lub podręczna kopia odświeżana rzadko — nazwa kucharza zmienia się praktycznie nigdy), a nie przez zdarzenie integracyjne.

### `PizzeriaStatusView` (`Pizzeria Lifecycle`)

**Zdarzenia źródłowe:** `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed`.

**Logika:** trywialna — pojedyncze pole `status` nadpisywane przy każdym zdarzeniu. Te same trzy zdarzenia są też zdarzeniami integracyjnymi (`325_integration_events.md`) — patrz „Lokalna kopia statusu pizzerii" pod „Projekcje międzykontekstowe" poniżej.

---

## Projekcje międzykontekstowe

### Lokalna kopia statusu pizzerii (`Guest Service`, `Kitchen`, `Resource Management`)

**Zdarzenia źródłowe (integracyjne, `325_integration_events.md`):** `PizzeriaOpened`, `PizzeriaClosingStarted`, `PizzeriaClosed`, publikowane przez `Pizzeria` (`Pizzeria Lifecycle`).

**Logika:** identyczna w każdym z trzech kontekstów — trywialna, pojedyncze pole `status` nadpisywane przy każdym skonsumowanym zdarzeniu. Każdy kontekst czyta wyłącznie tę lokalną kopię przy bramkowaniu własnych akcji (`Host` przy przyjęciu gości, `Waiter`/`Kitchen` przy zamówieniach, `Manager` przy zmianach konfiguracji — w tym menu, `253_menu_management.md`), zamiast odpytywać `Pizzeria Lifecycle` synchronicznie. Ten sam wzorzec jednego faktu z wieloma konsumentami co przy `TableReleased` czy `OrderSubmittedForPreparation`.

### Licznik aktywnych wizyt (`Pizzeria Lifecycle`)

**Zdarzenia źródłowe (integracyjne, `325_integration_events.md`):** `GuestServiceStarted` (+1), `GuestServiceCompleted` (−1), publikowane przez główny proces obsługi gości (`Guest Service`).

**Logika:** pojedynczy licznik całkowity, zwiększany/zmniejszany przy każdym skonsumowanym zdarzeniu. `PizzeriaClosingPolicy` (`324_domain_services.md`) uznaje warunek „żadna wizyta w toku" za spełniony, gdy licznik wynosi `0` — dokładnie ten sam wzorzec „start/koniec zasilający lokalny licznik", co projekcja „zajęty/wolny" per `chefId` przy `ChefTerminationPolicy` (`325_integration_events.md`), tylko tu licznik zbiorczy, nie per-encja.

### `GuestVisitView`

Ten model jest budowany inaczej niż pozostałe — nie ma pojedynczego kontekstu-właściciela całej korelacji `GuestGroup` ↔ `Table` ↔ `Bill` ↔ `Order` (`320_domain_model.md`, `321_aggregates.md`). Zdarzenia zasilające go dzielą się na trzy grupy:

1. **Zdarzenia wewnętrzne `Guest Service` (`Bill`, `Order`):** `BillOpened`, `BillLineAdded`, `BillClosed`, `OrderAccepted`, `OrderSubmitted`, `OrderDelivered`, oraz zdarzenia integracyjne konsumowane z `Kitchen` i już wykorzystywane przez `Order` do własnych przejść stanu — `OrderPreparationStarted`, `OrderReadyForDelivery` (`325_integration_events.md`). `BillClosed` i `OrderDelivered` zasilają też główny proces obsługi gości, który na ich podstawie publikuje `GuestServiceCompleted` do `Pizzeria Lifecycle` (`325_integration_events.md`) — same te zdarzenia jednak nie przekraczają granicy kontekstu bezpośrednio.
2. **Kamień milowy korelacji `GuestGroup` ↔ `Table`:** żaden z powyższych agregatów nie wie, które `tableId` i `guestGroupId` należą do tej samej wizyty — to wie wyłącznie główny proces obsługi gości. Projekcja `GuestVisitView` konsumuje milestone'y publikowane przez ten proces (np. „gość usadzony przy stoliku X", „wizyta zakończona") zamiast surowych zdarzeń `Table`. Dokładne nazwy tych zdarzeń zostaną ustalone razem z projektem procesu/sagi w `340_architecture.md` — na dziś wiadomo tylko, jakich faktów projekcja potrzebuje (patrz `330_read_models.md`, sekcja „Decyzje ostateczne").
3. **Wzbogacenie o `tableName`:** pobierane z `Resource Management` przez Open Host Service (odczyt na żądanie przy renderowaniu widoku, a nie zdarzenie) — nazwa stolika zmienia się rzadko, nie uzasadnia to utrzymywania osobnej, stale synchronizowanej kopii.

**Uwaga:** ten sam zestaw milestone'ów procesu, który zasila `GuestVisitView`, prawdopodobnie pokrywa się ze stanem wewnętrznym samego procesu/sagi (jego pamięcią korelacji) — `340_architecture.md` zdecyduje, czy projekcja czyta bezpośrednio ten stan, czy jest osobnym konsumentem tych samych zdarzeń.

---

## Decyzje ostateczne

* ✅ **Czy każdy model odczytu ma osobny zestaw zdarzeń źródłowych?** Nie zawsze — `MenuForGuests`/`MenuForKitchen`/`MenuManagementView` dzielą ten sam zestaw zdarzeń `MenuItem`, różniąc się wyłącznie filtrem odczytu.
* ✅ **Czy zdarzenie może mieć więcej niż jednego konsumenta (integracyjny + projekcja wewnętrzna)?** Tak — `TableReleased` i `PizzaTaskStarted`/`PizzaTaskReady` już to demonstrują: jeden fakt biznesowy, publikowany raz, konsumowany zarówno przez inny kontekst (jako zdarzenie integracyjne), jak i przez projekcję wewnątrz własnego kontekstu.
* ✅ **Czy `GuestVisitView` może być w pełni sprecyzowany bez ukończonego projektu sagi?** Nie w 100% — zależność jawnie odnotowana i odłożona do `340_architecture.md`, zgodnie z tym, jak `324_domain_services.md` już wcześniej odłożył projekt samego procesu.
* ✅ **Czy `PizzeriaOpened`/`PizzeriaClosingStarted`/`PizzeriaClosed` pozostają wyłącznie wewnętrzne wobec `Pizzeria Lifecycle`?** Nie, już nie. Pierwotnie tak (patrz historia decyzji w `325_integration_events.md`), ale status pizzerii bramkuje realne decyzje biznesowe w `Guest Service`, `Kitchen` i `Resource Management` — więc te same trzy zdarzenia są teraz też zdarzeniami integracyjnymi, zasilającymi lokalną kopię statusu w każdym z tych trzech kontekstów, zamiast synchronicznego odpytywania `Pizzeria Lifecycle` przy każdej bramkowanej akcji.

## Pytania do dalszej analizy

* Ostateczne nazwy zdarzeń milestone publikowanych przez proces/sagę obsługi gości — do ustalenia w `340_architecture.md`.
