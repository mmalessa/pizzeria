# Modele odczytu (Read Models)

## Cel dokumentu

Dokument otwiera stronę odczytu i kataloguje modele odczytu (read models) potrzebne systemowi Pizzeria — denormalizowane widoki zoptymalizowane pod konkretne potrzeby konsumenta (UI, rola, inny Bounded Context), oddzielone od modelu zapisu (agregatów z `320`–`325`) zgodnie z zasadą CQRS (`000_project_vision.md`).

## Granica dokumentu

`330_read_models.md` odpowiada na pytanie **jakie** modele odczytu istnieją, kto z nich korzysta i jakie pola zawierają. Nie opisuje mechanizmu ich budowania (które zdarzenia je zasilają, jak wygląda handler) — to `331_projections.md`. Nie opisuje konkretnych operacji zapytań (sygnatur, parametrów) udostępnianych konsumentom — to `332_queries.md`.

## Zasady projektowania modeli odczytu

* Model odczytu jest denormalizowany i zoptymalizowany pod konkretny widok — nie jest kopią 1:1 żadnego agregatu. Może łączyć dane z wielu agregatów, a nawet wielu Bounded Contextów (np. nazwy pozycji menu skopiowane do widoku rachunku, zamiast trzymania wyłącznie `menuItemId`).
* Model odczytu nie zawiera logiki biznesowej ani niezmienników — to rola agregatów (`321_aggregates.md`). Może zawierać proste wyliczenia prezentacyjne (np. sumę pozycji), ale nie decyzje domenowe.
* Model odczytu jest **spójny ostatecznie** względem modelu zapisu — aktualizowany asynchronicznie po zatwierdzeniu zmiany w agregacie źródłowym (`331_projections.md`).
* Każdy model odczytu istnieje, ponieważ konkretny konsument (rola, widok, inny kontekst) go potrzebuje — nie tworzymy „jednego dużego modelu" dla wszystkiego.
* Rozróżniamy dwa rodzaje modeli odczytu w tym systemie:
  1. **Wewnątrzkontekstowe** — budowane i udostępniane przez ten sam Bounded Context, który jest właścicielem danych źródłowych (np. `Resource Management` buduje i udostępnia widok menu). Inne konteksty odpytują je przez Open Host Service (`312_context_map.md`), zamiast utrzymywać własną kopię.
  2. **Międzykontekstowe (projekcje z integracji)** — budowane przez jeden kontekst na podstawie zdarzeń integracyjnych opublikowanych przez inny (np. lokalna projekcja „zajętości kucharza" w `Resource Management`, zasilana zdarzeniami z `Kitchen`). Te już istnieją i są w pełni opisane w `325_integration_events.md` (przy `ChefTerminationPolicy`) — nie powtarzamy ich tutaj, ponieważ są techniczne (nie mają odbiorcy-użytkownika) i służą wyłącznie usługom domenowym.

---

## Modele odczytu — szczegółowy opis

### `MenuForGuests`

**Konsument:** `GuestGroup` (wybór pozycji przy składaniu zamówienia, `213_ordering.md`).

**Cel:** lista pozycji menu dostępnych do zamówienia — wyłącznie pola widoczne dla gości.

**Pola:** `menuItemId`, `name`, `ingredients`, `price`.

**Filtr:** wyłącznie `MenuItem` w stanie `Active` (`253_menu_management.md`).

**Rodzaj:** wewnątrzkontekstowy (`Resource Management`), udostępniany `Guest Service` przez Open Host Service.

### `MenuForKitchen`

**Konsument:** `Kitchen` (przygotowanie pizz wg receptury, `251_kitchen_order_fulfillment.md`).

**Cel:** pełne dane pozycji menu potrzebne do produkcji.

**Pola:** `menuItemId`, `name`, `ingredients`, `recipe`.

**Filtr:** wyłącznie `MenuItem` w stanie `Active` (`Disabled` nigdy nie jest widoczne dla kuchni). Nie istnieje już stan pośredni `Retiring` — skoro zmiany menu są dozwolone wyłącznie, gdy pizzeria jest `Closed` (`253_menu_management.md`), a w tym stanie nie ma żadnych aktywnych zamówień, kuchnia nigdy nie musi mieć dostępu do pozycji „w trakcie wycofywania".

**Rodzaj:** wewnątrzkontekstowy (`Resource Management`), udostępniany `Kitchen` przez Open Host Service.

### `MenuManagementView`

**Konsument:** `Manager` (`253_menu_management.md`).

**Cel:** pełny wgląd we wszystkie pozycje menu, niezależnie od stanu — w tym `Disabled`, aby Manager mógł je przywrócić.

**Pola:** `menuItemId`, `name`, `ingredients`, `recipe`, `price`, `status`.

**Filtr:** brak — wszystkie stany.

**Rodzaj:** wewnątrzkontekstowy (`Resource Management`).

### `DiningRoomView`

**Konsument:** perspektywa „Sala Jadalna" (`000_project_vision.md`), `Manager` (konfiguracja stolików, `252_table_management.md`).

**Cel:** widok wszystkich stolików — pojemność, stan zajętości, przypisany kelner.

**Pola:** `tableId`, `name`, `capacity`, `status` (`Free`/`Occupied`), `waiterId`, `waiterName` (denormalizowane z `Waiter` do wyświetlenia bez dodatkowego zapytania).

**Rodzaj:** wewnątrzkontekstowy (`Resource Management`).

### `StaffView`

**Konsument:** `Manager` (`254_staff_management.md`), obserwacja ogólna.

**Cel:** lista personelu z ich statusem oraz — dla kelnerów — listą przypisanych stolików.

**Pola:** `waiters[]` (`waiterId`, `name`, `status`, `assignedTableNames[]`), `chefs[]` (`chefId`, `name`, `status`).

**Uwaga:** `assignedTableNames` jest polem **istniejącym wyłącznie w modelu odczytu** — `Waiter` (agregat) świadomie nie przechowuje tej listy (`321_aggregates.md`: `Table` jest jedynym źródłem prawdy relacji). Ten model odczytu jest dokładnie tym miejscem, gdzie odwrotna zależność „kelner → jego stoliki" jest materializowana do wyświetlenia, poprzez zapytanie `findByWaiterId` w obrębie tego samego kontekstu (`Resource Management`), a nie przez duplikowanie stanu w agregacie zapisu.

**Rodzaj:** wewnątrzkontekstowy (`Resource Management`).

### `KitchenQueueView`

**Konsument:** perspektywa „Kuchnia" (`000_project_vision.md`).

**Cel:** kolejka produkcyjna, zajętość kucharzy, postęp realizacji zamówień.

**Pola:** `pizzaTasks[]` (`pizzaTaskId`, `orderId`, `menuItemName`, `status`, `chefName?`), `chefs[]` (`chefId`, `name`, `busy: bool`), `orderProgress[]` (`orderId`, `totalPizzas`, `readyPizzas`).

**Rodzaj:** wewnątrzkontekstowy (`Kitchen`).

### `PizzeriaStatusView`

**Konsument:** wszystkie role i konteksty, które muszą znać bieżący stan pizzerii (`Host`, `Waiter`, `Kitchen`, `Manager`).

**Cel:** aktualny stan pizzerii.

**Pola:** `status` (`Open`/`Closing`/`Closed`).

**Rodzaj:** mieszany. W `Pizzeria Lifecycle` — wewnątrzkontekstowy, źródłowy. Dodatkowo `Guest Service`, `Kitchen` i `Resource Management` utrzymują każdy własną, lokalną kopię tego samego pola `status`, zasilaną zdarzeniami integracyjnymi `PizzeriaOpened`/`PizzeriaClosingStarted`/`PizzeriaClosed` (`325_integration_events.md`) — międzykontekstowe, ale w odróżnieniu od technicznych projekcji jak ta z `ChefTerminationPolicy`, mają bezpośredniego odbiorcę-użytkownika (`Host`, `Waiter`, `Kitchen`, `Manager`), więc katalogowane tu jako jeden model, budowany lokalnie w czterech miejscach zamiast odpytywany na żądanie z jednego.

### `GuestVisitView`

**Konsument:** `GuestGroup` (podgląd własnej wizyty, rachunku, statusu zamówień), `Waiter` (kontekst bieżącej obsługi).

**Cel:** jedyny widok łączący `GuestGroup`, `Table`, `Bill` i `Order` w ramach jednej wizyty — żaden agregat zapisu nie zna wszystkich naraz (`320_domain_model.md`, `321_aggregates.md`).

**Pola:** `guestGroupId`, `guestGroupName`, `tableId`, `tableName`, `bill` (`billId`, `status`, `totalAmount`, `lines[]` z `menuItemName`, `quantity`, `unitPrice`, `totalPrice`), `orders[]` (`orderId`, `status`).

**Rodzaj:** międzykontekstowy — patrz `331_projections.md`. Źródłem jest przede wszystkim `Guest Service` (`Bill`, `Order`), z domieszką `tableName` z `Resource Management`. Korelacja `GuestGroup` ↔ `Table` ↔ `Bill` sama w sobie pochodzi z głównego procesu obsługi gości (proces/saga, `324_domain_services.md`, projektowany szczegółowo w `340_architecture.md`) — ten model odczytu konsumuje jego kamienie milowe, nie surowe zdarzenia poszczególnych agregatów bezpośrednio.

---

## Decyzje ostateczne

* ✅ **Czy istnieje jeden wspólny model odczytu dla całego systemu?** Nie. Każdy model odpowiada na potrzebę konkretnego konsumenta — stąd 8 osobnych modeli powyżej, plus techniczne projekcje już opisane w `325_integration_events.md`.
* ✅ **Czy modele odczytu mogą łączyć dane z wielu Bounded Contextów?** Tak, przez denormalizację (np. `waiterName` w `DiningRoomView`, `menuItemName` w `GuestVisitView`) — to standardowa praktyka CQRS, nie narusza granic agregatów zapisu.
* ✅ **Czy `StaffView.assignedTableNames` narusza decyzję, że `Waiter` nie przechowuje listy stolików?** Nie. Decyzja z `321_aggregates.md` dotyczy agregatu zapisu. Model odczytu może i powinien materializować tę relację do wyświetlenia — to właśnie po to istnieje strona odczytu.
* ✅ **Czy `GuestVisitView` może być w pełni zaprojektowany bez ukończonego projektu procesu/sagi obsługi gości?** Częściowo. Pola i konsument są jasne już teraz; dokładne zdarzenia zasilające ten model zostaną dopracowane razem z projektem sagi w `340_architecture.md` — `331_projections.md` opisuje to na poziomie kamieni milowych, nie ostatecznych nazw zdarzeń.

## Pytania do dalszej analizy

* Ostateczne nazwy zdarzeń publikowanych przez proces/sagę obsługi gości (zasilających `GuestVisitView`) zostaną ustalone w `340_architecture.md`, równolegle z projektem samej sagi.
