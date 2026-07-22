# Obiekty wartości (Value Objects)

## Cel dokumentu

Dokument opisuje szczegółowo obiekty wartości wymienione wstępnie w `320_domain_model.md` (`Money`, `OrderLine`, `BillLine`, `TableCapacity`, `PreparationTime`) — ich składniki (z typami), niezmienniki, operacje oraz zasady równości. Krótko klasyfikuje też identyfikatory encji i enumeracje stanów jako obiekty wartości, bez powtarzania szczegółów opisanych już w `322_entities.md`.

## Granica dokumentu

`322_entities.md` opisywał encje, w których obiekty wartości pojawiają się jako typy atrybutów. `323_value_objects.md` opisuje **wnętrze** tych obiektów wartości — z czego się składają i jakie mają niezmienniki. Nie opisuje encji ani agregatów (`321_aggregates.md`, `322_entities.md`) ani usług domenowych, które ich używają (`324_domain_services.md`).

## Zasady wspólne dla obiektów wartości

* Brak tożsamości — dwa obiekty wartości o tych samych składnikach są równe (równość strukturalna, nie przez `id`).
* Niemodyfikowalność (immutability) — zmiana wartości oznacza zastąpienie całego obiektu nowym, nigdy mutację w miejscu.
* Samowalidujące się — niezmienniki (np. `quantity > 0`) są wymuszane w konstruktorze; nie istnieje stan pośredni, w którym obiekt wartości narusza własny niezmiennik.
* Obiekt wartości nie ma własnego repozytorium — żyje wyłącznie jako część encji lub agregatu, który go przechowuje.

---

## `Money`

**Składniki:**

| Składnik | Typ | Opis |
|----------|-----|------|
| `amount` | `decimal` | Kwota. |
| `currency` | `string` | Waluta. |

**Niezmienniki:**
* `amount ≥ 0` — model nie przewiduje ujemnych kwot (brak zwrotów, korekt czy rabatów w uproszczonym modelu, `111_domain_decisions.md`).

**Operacje:** `add(Money) → Money` (używane przy przeliczaniu `Bill.totalAmount` i `BillLine.totalPrice`), `equals(Money)` (równość strukturalna po `amount` i `currency`).

**Uwaga o walucie:** model zakłada jedną, stałą walutę dla całego systemu — operacje na `Money` (dodawanie, porównania) zakładają zgodność `currency`. Pole `currency` jest zachowane jawnie (a nie pominięte), aby model pozostał otwarty na przyszłe rozszerzenie o wiele walut bez przebudowy (`111_domain_decisions.md`: „Model domenowy powinien być otwarty na rozwój"), choć obecnie żaden proces go nie wykorzystuje.

---

## `OrderLine`

**Składniki:**

| Składnik | Typ | Opis |
|----------|-----|------|
| `menuItemId` | `MenuItemId` | Referencja do pozycji menu. |
| `quantity` | `int` | Liczba zamawianych sztuk. |

**Niezmienniki:**
* `quantity > 0`.

**Operacje:** brak operacji poza odczytem — `OrderLine` jest czystym nośnikiem danych wykorzystywanym przez `Order` (przy tworzeniu) i `Kitchen` (przy rozbijaniu zamówienia na `PizzaTask`, jeden `PizzaTask` na jednostkę `quantity`).

**Uwaga:** `OrderLine` nie przechowuje ceny — cena jest pobierana z `MenuItem` dopiero przy tworzeniu odpowiadającej `BillLine` (`320_domain_model.md`).

---

## `BillLine`

**Składniki:**

| Składnik | Typ | Opis |
|----------|-----|------|
| `menuItemId` | `MenuItemId` | Referencja do pozycji menu. |
| `quantity` | `int` | Liczba sztuk. |
| `unitPrice` | `Money` | Cena jednostkowa, kopia z menu w momencie przyjęcia zamówienia. |
| `totalPrice` | `Money` | `unitPrice × quantity`. |

**Niezmienniki:**
* `quantity > 0`,
* `totalPrice = unitPrice × quantity` — obliczane w momencie tworzenia, nigdy niezależnie ustawiane,
* `unitPrice` i `totalPrice` są kopiami wykonanymi jednorazowo w chwili przyjęcia zamówienia przez kelnera — późniejsza zmiana `MenuItem.price` nie wpływa na już utworzone `BillLine` (`320_domain_model.md`, `253_menu_management.md`).

**Operacje:** brak operacji modyfikujących — `BillLine` jest tworzona raz przez `Bill.addLine(...)` i nigdy nie jest zmieniana; usunięcie z rachunku nie jest wspierane (`Order` i jego pozycje nie mogą być anulowane, `111_domain_decisions.md` OQ-001).

**Uwaga:** `BillLine` nie przechowuje referencji do `orderId` ani `OrderLine`, z którego powstała — `Bill` agreguje wyłącznie zagregowane pozycje i kwoty, bez śledzenia pochodzenia z konkretnego zamówienia (`320_domain_model.md`).

---

## `TableCapacity`

**Składniki:**

| Składnik | Typ | Opis |
|----------|-----|------|
| `value` | `int` | Liczba miejsc przy stoliku. |

**Niezmienniki:**
* `value > 0`.

**Operacje:** `isEnoughFor(guestGroupSize: int): bool` — używane przez `Host` przy wyszukiwaniu odpowiedniego stolika (`211_guest_arrival.md`).

---

## `PreparationTime`

**Składniki:**

| Składnik | Typ | Opis |
|----------|-----|------|
| `value` | `int` | Czas przygotowania jednej pizzy, w jednostkach symulacji. |

**Niezmienniki:**
* `value > 0`.

**Operacje:** brak operacji poza odczytem — parametr globalny konfigurowany przez `Manager`, przechowywany w `Kitchen` i wykorzystywany zarówno do sterowania czasem przygotowania `PizzaTask`, jak i przez politykę szacowania czasu realizacji zamówienia (`251_kitchen_order_fulfillment.md`, kandydat na usługę domenową w `324_domain_services.md`).

---

## Identyfikatory jako obiekty wartości

Wszystkie identyfikatory encji (`GuestGroupId`, `TableId`, `BillId`, `OrderId`, `MenuItemId`, `WaiterId`, `ChefId`, `PizzaTaskId`) są obiektami wartości opakowującymi pojedynczą wartość (np. UUID lub string) — porównywane strukturalnie, niemodyfikowalne, bez własnych niezmienników poza „niepusty i poprawnie sformatowany". Pełna lista identyfikatorów i encji, do których należą, znajduje się w `322_entities.md`; nie powtarzamy jej tutaj.

## Enumeracje stanów jako obiekty wartości

Stany cyklu życia encji (`TableStatus`, `BillStatus`, `OrderStatus`, `MenuItemStatus`, `WaiterStatus`, `ChefStatus`, `PizzeriaStatus`, `PizzaTaskStatus`) są prostymi enumeracjami — technicznie obiektami wartości o jednej, ograniczonej domenie wartości. Dozwolone przejścia między nimi (z warunkami i inicjatorem) są szczegółowo opisane per-encja w `322_entities.md`; tutaj klasyfikujemy je wyłącznie jako typ, nie powtarzamy tabel przejść.

---

## Decyzje ostateczne

* ✅ **Czy `Money` powinien przechowywać walutę, skoro model zakłada jedną walutę?** Tak, jawnie — pole `currency` nie jest obecnie wykorzystywane operacyjnie (brak konwersji), ale zachowuje otwartość modelu na przyszłe rozszerzenie wielowalutowe bez przebudowy typu.
* ✅ **Czy `BillLine.totalPrice` jest przechowywane niezależnie, czy zawsze wyliczane?** Wyliczane przy tworzeniu (`unitPrice × quantity`) i następnie niezmienne — `BillLine` nigdy nie jest modyfikowana po utworzeniu.
* ✅ **Czy identyfikatory i enumeracje stanów wymagają osobnych, szczegółowych sekcji w tym dokumencie?** Nie. Są klasyfikowane jako obiekty wartości, ale ich szczegóły (przejścia stanów, powiązania z encjami) pozostają w `322_entities.md`, aby uniknąć duplikacji.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
