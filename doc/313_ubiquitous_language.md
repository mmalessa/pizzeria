# Ubiquitous Language

## Cel dokumentu

Dokument zbiera i porządkuje język wszechobecny (ubiquitous language) zidentyfikowany podczas odkrywania domeny oraz projektowania strategicznego systemu Pizzeria. Celem jest zapewnienie spójności nazewnictwa we wszystkich dokumentach, modelach i przyszłym kodzie.

## Co to jest Ubiquitous Language?

**Ubiquitous Language** to wspólny język używany przez wszystkie osoby zaangażowane w projekt: programistów, analityków biznesowych, ekspertów domenowych i użytkowników. Język ten powinien być:
* precyzyjny — każdy termin ma jednoznaczne znaczenie,
* spójny — używany w dokumentacji, kodzie, rozmowach i diagramach,
* bliski domenie biznesowej — odzwierciedla rzeczywiste pojęcia z pizzerii.

## Słownik pojęć

### Role i aktorzy

| Termin | Znaczenie | Kontekst |
|--------|-----------|----------|
| **GuestGroup** | Grupa gości przychodząca do pizzerii na jedną wizytę. Definiowana przez użytkownika symulacji (co najmniej liczba osób). Nie jest tożsama z kontem użytkownika. | Wszystkie konteksty operacyjne |
| **Host** | Rola wbudowana, odpowiedzialna za przyjmowanie gości i przydzielanie stolików. Jeden Host w pizzerii. | Guest Service |
| **Waiter** | Kelner przypisany do określonych stolików. Otwiera rachunki, przyjmuje zamówienia, dostarcza zamówienia, przyjmuje płatności. | Guest Service, Resource Management |
| **Chef** | Kucharz pracujący we wspólnej kolejce produkcyjnej kuchni. Przygotowuje pojedyncze pizze. | Kitchen, Resource Management |
| **Kitchen** | Rola koordynująca całą kuchnię: przyjmuje zamówienia, zarządza kolejką, dystrybuuje pizze, zgłasza gotowość. | Kitchen |
| **Manager** | Rola konfiguracyjna. Zarządza stolikami, menu, personelem, stanem pizzerii i parametrami kuchni. | Resource Management, Pizzeria Lifecycle, Kitchen |

### Byty i zasoby

| Termin | Znaczenie | Kontekst |
|--------|-----------|----------|
| **Table** | Stolik w pizzerii. W Resource Management: definicja zasobu (liczba miejsc, przypisanie do kelnera). W Guest Service: powiązanie gości ze stolikiem w ramach wizyty. | Resource Management, Guest Service |
| **MenuItem** | Pozycja menu: nazwa, składniki (widoczne dla gości), sposób przygotowania / receptura (widoczna dla kuchni), cena. | Resource Management, Guest Service, Kitchen |
| **Order** | Zamówienie złożone przez grupę gości w jednym akcie. Zawiera pozycje menu i ilości. Nie zna `tableId`, `billId` ani cen. | Guest Service, Kitchen |
| **OrderLine** | Pojedyncza pozycja zamówienia: `MenuItem` + liczba sztuk. | Guest Service, Kitchen |
| **Bill** | Rachunek finansowy grupy gości. Zawiera pozycje zamówień z cenami z momentu przyjęcia zamówienia. Ma stany `Open` / `Closed`. | Guest Service |
| **GuestGroup** | Grupa gości jako aktor inicjujący i kończący wizytę. | Guest Service |

### Stany i cykle życia

| Termin | Znaczenie | Kontekst |
|--------|-----------|----------|
| `Open` / `Closing` / `Closed` | Stany pizzerii. | Pizzeria Lifecycle |
| `Free` / `Occupied` | Stany stolika. | Resource Management, Guest Service |
| `Active` / `Terminating` / `Terminated` | Stany pracownika (kelnera lub kucharza). | Resource Management |
| `Active` / `Disabled` | Stany pozycji menu. `Disabled` to miękkie usunięcie (soft delete) na poziomie aplikacji. | Resource Management |
| `Open` / `Closed` | Stany rachunku. | Guest Service |
| `Accepted` / `Submitted` / `InPreparation` / `ReadyForDelivery` / `Delivered` | Stany zamówienia z perspektywy obsługi gości i kuchni. | Guest Service, Kitchen |
| `Pending` / `InPreparation` / `Ready` | Stany pojedynczej pizzy w kuchni. | Kitchen |

### Procesy i czynności

| Termin | Znaczenie | Kontekst |
|--------|-----------|----------|
| `GuestArrival` | Process of assigning a table to a guest group by the Host. | Guest Service |
| `BillOpening` | Creation of a bill by the waiter for a seated guest group. | Guest Service |
| `OrderPlacement` | Process of selecting menu items, accepting the order by the waiter, passing it to the kitchen, and delivering it to the table. | Guest Service, Kitchen |
| `ServiceCompletion` | Request for the bill, payment, closing the bill, leaving the premises, and releasing the table. | Guest Service |
| `MenuItemDisabling` | Soft-delete transition of a menu item from `Active` to `Disabled`. Allowed only while the pizzeria is `Closed`. Reversible directly back to `Active`. | Resource Management |
| `TableRelease` | Changing the table state from `Occupied` to `Free` after service is completed. | Guest Service, Resource Management |

## Terminy wyłącznie techniczne

Niektóre terminy nie są częścią języka domenowego, ale są potrzebne do opisu technicznego systemu:

| Termin | Znaczenie |
|--------|-----------|
| **Bounded Context** | Wyraźna granica rozwiązania z własnym modelem domenowym. |
| **Subdomena** | Obszar problemu biznesowego o określonym znaczeniu dla organizacji. |
| **Upstream / Downstream** | Kierunek zależności między kontekstami. Downstream zależy od upstreamu. |
| **Core Domain** | Kluczowa subdomena, w której organizacja wygrywa na rynku. |
| **Supporting Subdomain** | Subdomena potrzebna do działania Core Domain, ale niewyróżniająca organizacji. |
| **Generic Subdomain** | Subdomena rozwiązywana gotowymi, powtarzalnymi rozwiązaniami. |
| **Domain Event** | Narzędzie modelowania — fakt, który zaszedł w domenie. Nie jest mechanizmem integracji między kontekstami (`000_project_vision.md`). |
| **Integration Event** | Zdarzenie publikowane przez agregat w jednym Bounded Contexcie i konsumowane przez agregat w innym — preferowany sposób komunikacji między kontekstami (`000_project_vision.md`, `325_integration_events.md`). |

## Terminy celowo nieużywane

| Termin | Dlaczego nie jest używany |
|--------|---------------------------|
| **Rewir** | Zastąpiony relacją „kelner ma przypisane stoliki". Termin „rewir" nie wnosi istotnej wartości do modelu i został usunięty, aby uprościć język. |
| **Rezerwacja** | Model zakłada przyjmowanie gości wyłącznie na żywo. Rezerwacje wykraczają poza uproszczony model. |
| **Napiwek** | Napiwki wykraczają poza uproszczony model płatności. Płatność pokrywa wyłącznie kwotę rachunku. |

## Zasady używania języka

* W dokumentacji i kodzie należy używać terminów z tego słownika.
* Jeśli termin ma różne znaczenie w różnych kontekstach (np. `Table`, `Chef`), należy to wyraźnie oznaczyć nazwą kontekstu.
* Nie należy wprowadzać synonimów dla bytów domenowych bez aktualizacji tego dokumentu.
* Wszelkie nowe terminy powinny zostać dodane do słownika przed użyciem w kodzie.

## Decyzje ostateczne

* ✅ **Czy język wszechobecny jest spójny z procesami biznesowymi?** Tak. Terminy odzwierciedlają pojęcia zidentyfikowane podczas Event Stormingu i projektowania strategicznego.
* ✅ **Czy istnieją terminy wieloznaczne?** Tak. `Table` i `Chef` mają różne znaczenia w różnych Bounded Contextach. Różnice zostały wyjaśnione w słowniku.
* ✅ **Czy „rewir" powinien być częścią języka?** Nie. Termin został usunięty na rzecz prostszej relacji „kelner — przypisane stoliki".
* ✅ **Czy stany i kluczowe procesy powinny mieć angielskie odpowiedniki gotowe do użycia w kodzie?** Tak. Wszystkie stany cyklu życia oraz procesy/czynności posiadają termin angielski, który może być bezpośrednio użyty w kodzie (enum, nazwy zdarzeń, metody).

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
