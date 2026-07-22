# Usługi domenowe (Domain Services)

## Cel dokumentu

Dokument opisuje usługi domenowe — operacje domenowe, które nie należą naturalnie do żadnej pojedynczej encji ani agregatu, ponieważ wymagają danych z więcej niż jednego agregatu. Zbiera w jednym miejscu wszystkie miejsca, które `320_domain_model.md`, `321_aggregates.md` i `322_entities.md` opisywały jako „sprawdzane przez proces/usługę domenową", i nadaje im nazwy oraz konkretną sygnaturę.

## Granica dokumentu

Usługa domenowa w tym dokumencie to **bezstanowa** operacja: nie ma własnej tożsamości ani przechowywanego stanu, odpytuje repozytoria potrzebnych agregatów w momencie wywołania i zwraca wynik (decyzję lub wartość) bez zapisywania czegokolwiek samodzielnie — zapis należy zawsze do agregatu, który wywołuje usługę jako pomoc przy podjęciu decyzji. Nie obejmuje to głównego procesu obsługi gości, który koreluje `GuestGroup` ↔ `Table` ↔ `Bill` ↔ `Order` przez cały czas trwania wizyty — to wymaga trwałego stanu korelacji między krokami, więc jest procesem/sagą, nie usługą domenową (patrz sekcja „Poza zakresem" na końcu). Zdarzenia domenowe, którymi usługi mogą być wyzwalane (np. automatyczne sprawdzenie zamknięcia pizzerii), są przedmiotem `325_integration_events.md`.

## Zasady wspólne dla usług domenowych

* Usługa domenowa jest bezstanowa — wywoływana z aktualnymi danymi, nie przechowuje ich między wywołaniami.
* Usługa domenowa nie zapisuje zmian bezpośrednio w cudzych agregatach — zwraca decyzję/wartość, a zapisu (np. zmiany stanu) dokonuje agregat, który ją wywołał, we własnej transakcji.
* Usługa domenowa odpytuje repozytoria (nie żąda całych agregatów przez referencję obiektową) — spójne z zasadą z `321_aggregates.md`: agregaty referencjonują się wyłącznie przez ID.
* Nazwa usługi wyraża politykę/regułę biznesową (np. `TableAssignmentPolicy`), nie technikalia (nie: `TableHelper`, `TableUtils`).
* Usługa domenowa może przyjąć jako wejście cały agregat (nie tylko wyciągnięte z niego pole) — to nie narusza zasady „referencje między agregatami wyłącznie przez ID" z `321_aggregates.md`, ponieważ ta zasada dotyczy trwałych referencji przechowywanych w stanie agregatu, a nie doraźnego odczytu przekazanego jako parametr metody w ramach jednego wywołania. Przekazanie całego agregatu jest wskazane, gdy różne implementacje tej samej polityki (interfejsu) mogą potrzebować różnego zakresu danych z niego — patrz `KitchenTimeEstimationPolicy`.

---

## `TableAssignmentPolicy`

**Kontekst:** `Guest Service` (z danymi z `Resource Management`).

**Wywoływana przez:** `Host`, w ramach `GuestArrival` (`211_guest_arrival.md`).

**Wejście:** `guestGroup: GuestGroup` (cały byt, nie tylko `size` — patrz uzasadnienie poniżej).

**Wyjście:** `TableId?` — wybrany stolik, albo brak (Host odmawia przyjęcia).

**Logika:**
1. Znajdź wszystkie `Table` w stanie `Free`, z `capacity ≥ guestGroup.size`, z przypisanym `waiterId` wskazującym na `Waiter` w stanie `Active`.
2. Jeśli brak wyników — zwróć brak (Host odmawia przyjęcia, `GuestGroup` opuszcza pizzerię).
3. Jeśli jest więcej niż jeden wynik — wybierz stolik, którego kelner ma najmniej zajętych (`Occupied`) stolików (domyślna polityka zbalansowania obciążenia kelnerów, `211_guest_arrival.md`).

**Zależności:** repozytorium `Table` (odczyt + `findByWaiterId` do liczenia obciążenia), repozytorium `Waiter` (sprawdzenie `status = Active`).

**Uwaga:** polityka wyboru jest wydzielona celowo jako zamienna (interfejs) — `211_guest_arrival.md` przewiduje możliwość zmiany strategii wyboru w przyszłości (np. optymalizacja pojemności, kolejność wejścia) bez zmiany przebiegu głównego procesu przyjęcia gości. Wejściem interfejsu jest cały `guestGroup`, a nie wyłącznie `size` — uproszczona implementacja korzysta dziś jedynie z tego pola, ale przyszłe strategie (np. uwzględniające dodatkowe atrybuty grupy, gdyby model `GuestGroup` się rozszerzył) będą miały do niego dostęp bez zmiany sygnatury interfejsu ani kodu wywołującego w `Host`.

---

## `PizzeriaOpeningPolicy`

**Kontekst:** `Pizzeria Lifecycle`.

**Wywoływana przez:** `Manager`, przy próbie `Pizzeria.open()` (`255_pizzeria_lifecycle.md`).

**Wejście:** brak (operuje na aktualnym stanie systemu).

**Wyjście:** `bool` — czy otwarcie jest dozwolone.

**Logika:** zwraca `true` wyłącznie, gdy istnieje co najmniej jeden `Waiter` w stanie `Active`, co najmniej jeden `Chef` w stanie `Active` i co najmniej jeden `Table` w konfiguracji.

**Zależności:** repozytoria `Waiter`, `Chef`, `Table` (odczyt/zliczanie).

---

## `PizzeriaClosingPolicy`

**Kontekst:** `Pizzeria Lifecycle`.

**Wywoływana przez:** handler zdarzeń reagujący na zamknięcie rachunku, zwolnienie stolika lub dostarczenie zamówienia, gdy pizzeria jest w stanie `Closing` (szczegóły wyzwalania w `325_integration_events.md`).

**Wejście:** brak.

**Wyjście:** `bool` — czy pizzeria może automatycznie przejść `Closing → Closed`.

**Logika:** zwraca `true` wyłącznie, gdy wszystkie `Bill` są `Closed`, wszystkie `Table` są `Free` i nie ma żadnego `Order` poza stanem `Delivered`.

**Zależności:** repozytoria `Bill`, `Table`, `Order` (odczyt/zliczanie).

**Uwaga:** sama usługa jest bezstanową funkcją odpowiadającą na pytanie „czy można zamknąć teraz?" — mechanizm wyzwalania jej po każdej istotnej zmianie (a nie odpytywanie w pętli) jest zagadnieniem integracyjnym, nie domenowym.

---

## `WaiterTerminationPolicy`

**Kontekst:** `Resource Management`.

**Wywoływana przez:** `Manager`, przy `Waiter.startTerminating()` i `Waiter.completeTermination()` (`254_staff_management.md`).

**Operacje:**
* `canStartTerminating(waiterId): bool` — `false`, jeśli ten kelner jest ostatnim `Active` kelnerem, a pizzeria jest w stanie `Open` lub `Closing`.
* `canCompleteTermination(waiterId): bool` — `true` wyłącznie, gdy żaden `Table` przypisany do tego kelnera (`findByWaiterId`) nie jest w stanie `Occupied`.

**Zależności:** repozytorium `Waiter` (zliczanie innych `Active` kelnerów), repozytorium `Table` (`findByWaiterId`), `Pizzeria` (odczyt `status`).

---

## `ChefTerminationPolicy`

**Kontekst:** `Resource Management` (z danymi z `Kitchen`).

**Wywoływana przez:** `Manager`, przy `Chef.startTerminating()` i `Chef.completeTermination()` (`254_staff_management.md`).

**Operacje:**
* `canStartTerminating(chefId): bool` — `false`, jeśli ten kucharz jest ostatnim `Active` kucharzem, a pizzeria jest w stanie `Open` lub `Closing`.
* `canCompleteTermination(chefId): bool` — `true` wyłącznie, gdy żaden `PizzaTask` przypisany temu kucharzowi (w `Kitchen`) nie jest w stanie `InPreparation`.

**Zależności:** repozytorium `Chef` (zliczanie innych `Active` kucharzy), agregat/repozytorium `Kitchen` (zapytanie o `PizzaTask` po `chefId`), `Pizzeria` (odczyt `status`).

---

## `MenuItemDisablingPolicy`

**Kontekst:** `Resource Management` (z danymi z `Guest Service`).

**Wywoływana przez:** `Manager`, przy `MenuItem.disable()` (`253_menu_management.md`).

**Wejście:** `menuItemId: MenuItemId`.

**Wyjście:** `bool` — czy przejście `Retiring → Disabled` jest dozwolone.

**Logika:** zwraca `true` wyłącznie, gdy nie istnieje żaden `Order` zawierający ten `menuItemId` w stanie innym niż `Delivered`.

**Zależności:** repozytorium `Order` (zapytanie o zamówienia zawierające daną pozycję menu, poza stanem `Delivered`).

---

## `KitchenTimeEstimationPolicy`

**Kontekst:** `Kitchen`.

**Wywoływana przez:** `Kitchen`, po przyjęciu zamówienia do realizacji (`251_kitchen_order_fulfillment.md`), oraz opcjonalnie przez `Waiter` do informowania gości o szacowanym czasie oczekiwania.

**Wejście:** `order: Order` (cały agregat, nie tylko liczba pozycji — patrz uzasadnienie poniżej).

**Wyjście:** szacowany czas realizacji (w jednostkach symulacji).

**Logika:** uproszczona implementacja szacuje na podstawie: liczby pizz w `order.lines` (sumy `quantity`), aktualnego obciążenia kolejki produkcyjnej (`PizzaTask` w stanach `Pending`/`InPreparation`), liczby aktywnych (`Active`) kucharzy oraz skonfigurowanego `PreparationTime`. Wynik nie jest przechowywany w `Order` — jest wyliczany na bieżąco (`111_domain_decisions.md`, OQ-007).

**Zależności:** `order.lines` (`OrderLine[]`), stan `Kitchen` (kolejka `PizzaTask`, `pizzaPreparationTime`), repozytorium `Chef` (liczba `Active`).

**Uwaga:** wydzielona celowo jako zamienna polityka (interfejs) — `251_kitchen_order_fulfillment.md` przewiduje przyszłe rozszerzenia (priorytety zamówień, różne czasy dla różnych pizz, predykcja na podstawie historii) bez zmiany przebiegu głównego procesu realizacji zamówienia w kuchni. Wejściem interfejsu jest cały `order`, a nie wyłącznie zagregowana liczba pizz — uproszczona implementacja korzysta dziś jedynie z sumy `quantity`, ale alternatywne implementacje (np. różne czasy per `menuItemId`) potrzebują dostępu do poszczególnych `OrderLine`, których agregat count by nie ujawnił. Zmiana implementacji polityki nie wymaga wtedy zmiany sygnatury interfejsu ani kodu wywołującego w `Kitchen`.

---

## Poza zakresem: proces obsługi gości

Wielokrotnie w `320`–`323` pojawia się odniesienie do „głównego procesu obsługi gości", który koreluje `GuestGroup` ↔ `Table` ↔ `Bill` ↔ `Order` (żaden z tych agregatów nie zna pozostałych bezpośrednio — patrz `320_domain_model.md`, `321_aggregates.md`). W odróżnieniu od usług domenowych opisanych powyżej, ten mechanizm:
* musi pamiętać skojarzenia między agregatami przez cały czas trwania wizyty (który `Table`, który `Bill`, które `Order` należą do danej `GuestGroup`),
* podejmuje wieloetapowe decyzje rozłożone w czasie (np. „czy można zamknąć rachunek" wymaga śledzenia statusów wielu `Order` w miarę ich powstawania i dostarczania).

To cechy procesu/sagi (stateful process manager), nie bezstanowej usługi domenowej. Jego szczegółowy projekt (magazyn korelacji, sposób wyzwalania kolejnych kroków) należy do etapu architektury — `340_architecture.md`.

---

## Decyzje ostateczne

* ✅ **Czy polityka wyboru stolika przez `Host` jest usługą domenową?** Tak — `TableAssignmentPolicy`, bezstanowa, odpytuje `Table` i `Waiter`.
* ✅ **Czy `TableAssignmentPolicy` powinna przyjmować `guestGroupSize: int` czy cały `guestGroup: GuestGroup`?** Cały `guestGroup` — ten sam argument co dla `KitchenTimeEstimationPolicy`: przekazanie samego `size` obcięłoby dane, do których przyszłe implementacje interfejsu mogłyby potrzebować dostępu, gdyby `GuestGroup` zyskała nowe atrybuty. Doraźny odczyt w ramach jednego wywołania, nie trwała referencja.
* ✅ **Czy szacowanie czasu realizacji zamówienia jest usługą domenową?** Tak — `KitchenTimeEstimationPolicy`, wynik nigdy nie jest zapisywany w `Order`.
* ✅ **Czy `KitchenTimeEstimationPolicy` powinna przyjmować `pizzaCount: int` czy cały `order: Order`?** Cały `order`. Przekazanie samej zagregowanej liczby pizz obcięłoby dane, których alternatywne implementacje interfejsu będą potrzebować (np. różne czasy per `menuItemId`, jawnie przewidziane w `251_kitchen_order_fulfillment.md`). Nie narusza to zasady „referencje między agregatami wyłącznie przez ID" — to doraźny odczyt w ramach jednego wywołania, nie trwała referencja przechowywana w stanie `Kitchen`.
* ✅ **Czy `Waiter` i `Chef` mają wspólną usługę „terminacji", skoro mają analogiczne reguły?** Nie. Zgodnie z decyzją z `320_domain_model.md` (`Waiter`/`Chef` to osobne agregaty bez wspólnych niezmienników), usługi też są osobne — `WaiterTerminationPolicy` odpytuje `Table`, `ChefTerminationPolicy` odpytuje `Kitchen`.
* ✅ **Czy główny proces obsługi gości jest usługą domenową?** Nie. Wymaga trwałej korelacji stanu między agregatami w czasie — to proces/saga, projektowany w `340_architecture.md`, poza zakresem tego dokumentu.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
