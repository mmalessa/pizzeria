# Context Map

## Cel dokumentu

Dokument przedstawia relacje między Bounded Contextami zidentyfikowanymi w `311_bounded_contexts.md`. Context Map pokazuje, jak konteksty komunikują się ze sobą, jakie są ich zależności oraz jaki wzorzec integracji jest proponowany dla każdej relacji.

## Co to jest Context Map?

**Context Map** to diagram lub opis relacji między Bounded Contextami w systemie. Określa:
* kierunek zależności (upstream / downstream),
* wzorce integracji między kontekstami,
* sposób tłumaczenia modeli przy przekraczaniu granic kontekstów.

Podstawowe wzorce relacji według DDD:
* **Partnership** — współpraca dwóch kontekstów przy wspólnych zmianach.
* **Shared Kernel** — współdzielony fragment modelu między kontekstami.
* **Customer-Supplier** — jeden kontekst jest dostawcą (upstream), drugi odbiorcą (downstream).
* **Conformist** — downstream akceptuje model upstream bez wpływu na niego.
* **Anti-Corruption Layer** — downstream izoluje się od modelu upstream przez warstwę tłumaczenia.
* **Open Host Service** — upstream udostępnia zestaw usług dla wielu downstreamów.
* **Published Language** — wspólny język komunikacji między kontekstami.
* **Separate Ways** — konteksty nie komunikują się ze sobą.

**Ważne rozróżnienie:** kierunek upstream/downstream opisuje, **czyj model jest autorytatywny** — kto akceptuje (conformuje się do) czyjego modelu i kontraktu, nie kierunek pojedynczych komunikatów (command/event) ani zapytań między kontekstami. Kontekst upstream może swobodnie konsumować zdarzenia integracyjne publikowane przez swój downstream, i odwrotnie — to nie jest wyjątek wymagający uzasadnienia względem „głównego kierunku zależności", tylko normalna integracja operacyjna. Wybór między zdarzeniem integracyjnym a odczytem na żądanie (Open Host Service) jest osobną decyzją dotyczącą integracji (częstotliwość operacji, potrzeba autonomii od dostępności źródła danych) — niezależną od tego, który kontekst jest upstream. Czy taki odczyt czy publikacja zdarzenia są w implementacji blokujące, jaki protokół transportu jest użyty itd. — to zagadnienie architektury (`340_architecture.md`), poza zakresem tego dokumentu.

## Bounded Contexty w systemie

Na podstawie `311_bounded_contexts.md` system składa się z czterech Bounded Contextów:

1. **Guest Service** — Core Domain, koordynacja obsługi gości.
2. **Kitchen** — Supporting, realizacja zamówień w kuchni.
3. **Resource Management** — Generic/Supporting, konfiguracja stolików, menu i personelu.
4. **Pizzeria Lifecycle** — Supporting, zarządzanie stanem całej pizzerii.

## Relacje między kontekstami

### Pizzeria Lifecycle → Guest Service

* **Kierunek zależności:** Guest Service jest downstream, Pizzeria Lifecycle jest upstream.
* **Opis:** Guest Service musi sprawdzać stan pizzerii przed przyjęciem nowych gości, złożeniem zamówień czy zamknięciem rachunku. Stan `Closing` blokuje nowe grupy gości, a `Closed` blokuje wszystkie procesy operacyjne.
* **Wzorzec:** **Conformist**. Guest Service akceptuje model stanów pizzerii zdefiniowany przez Pizzeria Lifecycle bez wpływu na jego definicję.
* **Mechanizm:** Pizzeria Lifecycle publikuje `PizzeriaOpened`/`PizzeriaClosingStarted`/`PizzeriaClosed`; Guest Service utrzymuje własną, lokalną kopię statusu zasilaną tymi zdarzeniami, zamiast odpytywać Pizzeria Lifecycle synchronicznie przy każdej bramkowanej akcji (szczegóły w `325_integration_events.md`).
* **Dodatkowa komunikacja:** główny proces obsługi gości (Guest Service) publikuje parę zdarzeń integracyjnych `GuestServiceStarted`/`GuestServiceCompleted` (wizyta rozpoczęta / zobowiązania danej wizyty wobec Guest Service zakończone), konsumowaną przez Pizzeria Lifecycle jako licznik aktywnych wizyt, używany razem z `TableReleased`, aby umożliwić automatyczne przejście `Closing → Closed` (`PizzeriaClosingPolicy`, `324_domain_services.md`; szczegóły zdarzeń w `325_integration_events.md`). Para zdarzeń jest publikowana przez proces, nie przez `Bill`/`Order` bezpośrednio — Pizzeria Lifecycle nie powinien znać wewnętrznego rozbicia Guest Service na te dwa agregaty.

### Pizzeria Lifecycle → Resource Management

* **Kierunek zależności:** Resource Management jest downstream, Pizzeria Lifecycle jest upstream.
* **Opis:** Resource Management musi respektować stan pizzerii przy modyfikacjach konfiguracji. Niektóre zmiany są zablokowane, gdy pizzeria jest w stanie `Open` lub `Closing`.
* **Wzorzec:** **Conformist**. Resource Management akceptuje reguły stanów pizzerii zdefiniowane przez Pizzeria Lifecycle.
* **Mechanizm:** jak w relacji powyżej — Resource Management utrzymuje własną, lokalną kopię statusu pizzerii, zasilaną `PizzeriaOpened`/`PizzeriaClosingStarted`/`PizzeriaClosed`, i na niej opiera bramkę „zmiany menu/personelu/stolików dozwolone wyłącznie gdy `Closed`" (`253_menu_management.md`, `254_staff_management.md`, `252_table_management.md`).
* **Dodatkowa komunikacja:** `Table` (Resource Management) publikuje `TableReleased`, konsumowane przez `PizzeriaClosingPolicy`. Dodatkowo `PizzeriaOpeningPolicy` (`324_domain_services.md`) odczytuje na żądanie z Resource Management liczbę aktywnych kelnerów, kucharzy i stolików przy próbie otwarcia pizzerii — modelowana jako odczyt (Open Host Service), a nie zdarzenie, ponieważ jest to rzadka, świadoma operacja Managera, dla której trwała lokalna projekcja byłaby nieopłacalna (uzasadnienie w `325_integration_events.md`).

### Pizzeria Lifecycle → Kitchen

* **Kierunek zależności:** Kitchen jest downstream, Pizzeria Lifecycle jest upstream.
* **Opis:** Kitchen musi respektować stan pizzerii. W stanie `Closed` kuchnia nie przyjmuje nowych zamówień. W stanie `Closing` kuchnia dokończa realizację istniejących zamówień.
* **Wzorzec:** **Conformist**. Kitchen akceptuje stan pizzerii zdefiniowany przez Pizzeria Lifecycle.
* **Mechanizm:** jak w relacjach powyżej — Kitchen utrzymuje własną, lokalną kopię statusu pizzerii, zasilaną `PizzeriaOpened`/`PizzeriaClosingStarted`/`PizzeriaClosed` (`325_integration_events.md`).

### Resource Management → Guest Service

* **Kierunek zależności:** Guest Service jest downstream, Resource Management jest upstream.
* **Opis:** Guest Service korzysta ze stolików, menu i informacji o kelnerach zdefiniowanych w Resource Management. Host przydziela tylko stoliki z aktywnym kelnerem. Menu jest używane przy składaniu zamówień.
* **Wzorzec:** **Open Host Service + Published Language**. Resource Management udostępnia standardowy zestaw informacji o zasobach (stoliki, menu, personel), z których korzysta Guest Service (i Kitchen).
* **Dodatkowa komunikacja:** brak. (Wcześniej `Order` publikował `OrderSubmittedForPreparation` i `OrderDelivered`, konsumowane też przez `MenuItemDisablingPolicy` — usługa ta została usunięta po ograniczeniu wszystkich zmian menu wyłącznie do stanu `Closed` pizzerii, `253_menu_management.md`, `325_integration_events.md`.)

### Resource Management → Kitchen

* **Kierunek zależności:** Kitchen jest downstream, Resource Management jest upstream.
* **Opis:** Kitchen korzysta z menu (receptury) oraz z personelu kuchennego zdefiniowanego w Resource Management. Każda pizza jest przygotowywana zgodnie z recepturą z `MenuItem`.
* **Wzorzec:** **Conformist** lub **Open Host Service**. Kitchen akceptuje model menu i personelu z Resource Management. Jeśli model ten ewoluuje, Kitchen dostosowuje się do zmian.
* **Dodatkowa komunikacja:** `PizzaTask` (Kitchen) publikuje `PizzaTaskStarted` i `PizzaTaskReady`, konsumowane przez `ChefTerminationPolicy` (`324_domain_services.md`) w Resource Management, aby sprawdzić obciążenie kucharza bez bezpośredniego odpytywania Kitchen w hot-pathcie (szczegóły w `325_integration_events.md`).

### Guest Service → Kitchen

* **Kierunek zależności:** Kitchen jest downstream, Guest Service jest upstream dla przepływu zamówień.
* **Opis:** Guest Service przekazuje zamówienia do Kitchen w celu realizacji. Kitchen nie zarządza zamówieniem jako takim — otrzymuje je do przygotowania i zgłasza gotowość.
* **Wzorzec:** **Customer-Supplier**. Guest Service jest klientem, który zleca przygotowanie zamówienia. Kitchen jest dostawcą usługi produkcyjnej.
* **Dodatkowa komunikacja:** Gdy zamówienie jest gotowe, Kitchen informuje o tym Guest Service zdarzeniem integracyjnym (`OrderPreparationStarted`, `OrderReadyForDelivery` — `325_integration_events.md`).

## Legenda diagramu

Diagram Context Map używa pełnych strzałek (`-->`) do oznaczenia relacji zależności upstream/downstream. Strzałka wskazuje od upstreamu do downstreamu (downstream zależy od upstreamu). Przepływ komunikatów (command/event) między kontekstami nie jest przedstawiony na diagramie, ponieważ Context Map koncentruje się na relacjach zależności, a nie na szczegółach komunikacji.

## Diagram Context Map

```mermaid
flowchart TD

subgraph Core["Core Domain"]
  GS[Guest Service]
end

subgraph Supporting["Supporting Subdomains"]
  K[Kitchen]
  PL[Pizzeria Lifecycle]
end

subgraph Generic["Generic Subdomains"]
  RM[Resource Management]
end

GS -->|zależy od stanu| PL
RM -->|zależy od stanu| PL
K -->|zależy od stanu| PL
GS -->|zależy od zasobów| RM
K -->|zależy od menu i personelu| RM
```

## Podsumowanie wzorców integracji

| Relacja | Upstream | Downstream | Wzorzec |
|---------|----------|------------|---------|
| Pizzeria Lifecycle → Guest Service | Pizzeria Lifecycle | Guest Service | Conformist |
| Pizzeria Lifecycle → Resource Management | Pizzeria Lifecycle | Resource Management | Conformist |
| Pizzeria Lifecycle → Kitchen | Pizzeria Lifecycle | Kitchen | Conformist |
| Resource Management → Guest Service | Resource Management | Guest Service | Open Host Service + Published Language |
| Resource Management → Kitchen | Resource Management | Kitchen | Conformist / Open Host Service |
| Guest Service ↔ Kitchen | Guest Service (dla zamówień) / Kitchen (dla gotowości) | Kitchen / Guest Service | Customer-Supplier + async events |

Trzy relacje powyżej (`Pizzeria Lifecycle → Guest Service`, `Pizzeria Lifecycle → Resource Management`, `Resource Management → Kitchen`) mają dodatkowo udokumentowaną komunikację w przeciwną stronę (zdarzenie integracyjne lub, dla otwarcia pizzerii, odczyt na żądanie) — szczegóły w opisie każdej relacji powyżej i w `325_integration_events.md`. Tabela pokazuje relację zależności modelu (kto czyj model akceptuje), nie kierunek poszczególnych komunikatów — te dwie rzeczy są od siebie niezależne (patrz „Ważne rozróżnienie" na początku dokumentu).

## Przekraczanie granic kontekstów

### Zamówienie między Guest Service a Kitchen

W **Guest Service** zamówienie (`Order`) jest bytem produktowym z pozycjami menu i ilościami. W **Kitchen** zamówienie jest rozbijane na pojedyncze zadania produkcyjne (`PizzaTask`). Przy przekazywaniu zamówienia do kuchni następuje tłumaczenie modelu:
* Guest Service wysyła identyfikator zamówienia oraz listę pozycji menu z ilościami.
* Kitchen interpretuje pozycje jako zestaw pizz do przygotowania.
* Kitchen nie zna `tableId`, `billId` ani cen — korzysta wyłącznie z identyfikatorów menu.

### Stolik między Resource Management a Guest Service

W **Resource Management** stolik (`Table`) jest definicją zasobu z liczbą miejsc i przypisaniem do kelnera. W **Guest Service** stolik jest powiązaniem gości z zasobem w ramach konkretnej wizyty. Główny proces obsługi gości przechowuje to powiązanie, ale rachunek i zamówienie nie znają `tableId`.

### Menu między Resource Management a Kitchen / Guest Service

W **Resource Management** `MenuItem` zawiera pełne informacje: nazwę, składniki, recepturę, cenę. W **Guest Service** widoczne są tylko nazwa, składniki i cena. W **Kitchen** widoczne są nazwa, składniki i receptura. Cena jest ukrywana przed kuchnią.

### Pizzeria Lifecycle jako wspólny upstream

Stan pizzerii jest udostępniany wszystkim kontekstom. Każdy kontekst musi sprawdzać stan przed rozpoczęciem operacji, które są niemożliwe w danym stanie. Nie ma indywidualnych negocjacji — wszystkie konteksty stosują ten sam model stanów.

## Decyzje ostateczne

* ✅ **Czy Pizzeria Lifecycle jest wspólnym upstreamem dla wszystkich kontekstów?** Tak. Stan pizzerii jest współdzielony i respektowany przez Guest Service, Resource Management i Kitchen.
* ✅ **Czy Resource Management udostępnia usługi dla wielu downstreamów?** Tak. Resource Management jest upstreamem dla Guest Service i Kitchen, dostarczając informacje o stolikach, menu i personelu.
* ✅ **Czy Guest Service i Kitchen są w relacji Customer-Supplier?** Tak. Guest Service zleca kuchni przygotowanie zamówienia, a Kitchen zgłasza gotowość. Komunikacja jest asynchroniczna (zdarzenia).
* ✅ **Czy potrzebna jest Anti-Corruption Layer między kontekstami?** Na obecnym etapie nie. Modele są na tyle proste, że wystarczają proste tłumaczenia przy integracji. W przyszłości, przy rozroście modelu, można rozważyć wprowadzenie ACL.
* ✅ **Jaką konwencję strzałek stosuje diagram Context Map?** Pełna strzałka (`-->`) oznacza relację zależności: downstream zależy od upstreamu. Diagram nie przedstawia przepływów komunikatów (command/event), ponieważ Context Map koncentruje się na zależnościach między kontekstami.
* ✅ **Czy istnieje Shared Kernel między kontekstami?** Nie. Każdy kontekst posiada własny model. Wspólne są wyłącznie identyfikatory bytów (np. `orderId`, `tableId`, `menuItemId`) przekazywane jako wartości.
* ✅ **Czy upstream/downstream determinuje kierunek, w którym mogą płynąć zdarzenia integracyjne lub zapytania?** Nie. Upstream/downstream opisuje wyłącznie, czyj model jest autorytatywny (kto czyj model/kontrakt akceptuje) — to osobna sprawa od tego, który kontekst publikuje, a który konsumuje dany komunikat. Kontekst upstream odpytujący lub konsumujący zdarzenia od swojego downstreamu nie jest wyjątkiem ani „kanałem zwrotnym" wymagającym uzasadnienia względem kierunku zależności — to zwyczajna integracja operacyjna. Przykłady już udokumentowane przy poszczególnych relacjach powyżej: `ChefTerminationPolicy` (Resource Management, upstream względem Kitchen w relacji dot. menu/personelu) konsumuje zdarzenia `PizzaTaskStarted`/`PizzaTaskReady` publikowane przez Kitchen; `PizzeriaOpeningPolicy` (Pizzeria Lifecycle, upstream względem Resource Management w relacji dot. stanu pizzerii) odczytuje dane na żądanie z Resource Management; `PizzeriaClosingPolicy` konsumuje `GuestServiceStarted`/`GuestServiceCompleted`/`TableReleased` z downstreamów. Wybór między zdarzeniem integracyjnym a odczytem na żądanie w każdym z tych przypadków wynika z częstotliwości operacji (hot-path vs. rzadka operacja) i potrzeby autonomii od dostępności źródła — nie z kierunku zależności modelu (`324_domain_services.md`, `325_integration_events.md`).

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
