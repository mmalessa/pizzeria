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
| **OrderLine** | Pojedyncza pozycja zamówienia: `MenuItem` + ilość. | Guest Service, Kitchen |
| **Bill** | Rachunek finansowy grupy gości. Zawiera pozycje zamówień z cenami z momentu przyjęcia zamówienia. Ma stany Otwarty / Zamknięty. | Guest Service |
| **GuestGroup** | Grupa gości jako aktor inicjujący i kończący wizytę. | Guest Service |

### Stany i cykle życia

| Termin | Znaczenie | Kontekst |
|--------|-----------|----------|
| **Otwarta** / **Zamykana** / **Zamknięta** | Stany pizzerii. | Pizzeria Lifecycle |
| **wolny** / **zajęty** | Stany stolika. | Resource Management, Guest Service |
| **Aktywny** / **Zwalniany** / **Zwolniony** | Stany pracownika (kelnera lub kucharza). | Resource Management |
| **Aktywna** / **Wycofywana** | Stany pozycji menu. | Resource Management |
| **Otwarty** / **Zamknięty** | Stany rachunku. | Guest Service |
| **Przyjęte** / **Zamówione** / **W realizacji** / **Gotowe do odbioru** / **Dostarczone** | Stany zamówienia z perspektywy obsługi gości i kuchni. | Guest Service, Kitchen |
| **Oczekująca** / **W przygotowaniu** / **Gotowa** | Stany pojedynczej pizzy w kuchni. | Kitchen |

### Procesy i czynności

| Termin | Znaczenie | Kontekst |
|--------|-----------|----------|
| **Przyjęcie gości** | Proces przydzielenia stolika grupie gości przez Hosta. | Guest Service |
| **Otwarcie rachunku** | Utworzenie rachunku przez kelnera dla usadzonej grupy gości. | Guest Service |
| **Składanie zamówienia** | Proces wyboru pozycji z menu, przyjęcia przez kelnera, przekazania do kuchni i dostarczenia do stolika. | Guest Service, Kitchen |
| **Zakończenie obsługi** | Prośba o rachunek, płatność, zamknięcie rachunku, opuszczenie lokalu, zwolnienie stolika. | Guest Service |
| **Wycofanie pozycji menu** | Przejście pozycji menu ze stanu Aktywna do Wycofywana. Pozycja nie jest dostępna dla nowych zamówień, ale nadal realizowana w istniejących. | Resource Management |
| **Zwolnienie stolika** | Zmiana stanu stolika z zajęty na wolny po zakończeniu obsługi. | Guest Service, Resource Management |

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

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
