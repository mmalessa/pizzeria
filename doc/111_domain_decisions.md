# Domain Decisions

## Cel dokumentu

Dokument zawiera decyzje domenowe podjęte podczas analizy biznesowej projektu Pizzeria.

Stanowią one podstawę dalszego modelowania domeny oraz projektowania procesów biznesowych.

---

# Grupa Gości (GuestGroup) jest podstawowym aktorem domenowym

W systemie Pizzeria podstawowym aktorem po stronie klienta jest **GuestGroup** — grupa osób przychodząca do lokalu i obsługiwana jako jedna całość.

System nie modeluje pojedynczych gości ani nie rozróżnia osób wewnątrz grupy. Decyzje o zamówieniach, płatnościach i zachowaniach są podejmowane na poziomie grupy.

## GuestGroup nie ma cyklu życia

`GuestGroup` jest bytem posiadającym tożsamość, wokół którego toczą się wydarzenia, ale **nie posiada własnego cyklu życia**. Nie przechodzi między stanami typu „oczekuje", „siedzi", „płaci".

Cykl życia jest realizowany przez powiązane byty i procesy — przede wszystkim przez `Bill` (rachunek) oraz `Table` (stolik). `GuestGroup` pozostaje stałym odniesieniem przez cały czas trwania obsługi.

---

# Stolik (Table) jest zasobem o ograniczonej pojemności

Każdy stolik posiada określoną maksymalną liczbę miejsc. Host przydziela stolik biorąc pod uwagę:

* liczbę osób w grupie gości,
* optymalizację obsługi pod kątem kelnerów (kelnerzy powinni mieć zbalansowane rewiry).

Stolik może znajdować się w jednym z dwóch stanów:
* **wolny** — dostępny dla nowej grupy gości,
* **zajęty** — przypisany do aktualnie obsługiwanego rachunku.

## Stolik nie jest częścią domeny finansowej rachunku

Cykl życia stolika oraz zarządzanie jego stanem należą do domeny zasobów lokalu. Stolik nie należy do domeny finansowej rachunku (`Bill`).

Główny proces obsługi gości wiąże ze sobą:
* `GuestGroup` — byt tożsamościowy reprezentujący gości,
* `Table` — zasób przydzielany gościom,
* `Bill` — domena finansowa,
* `Order` — domena zamówień z pozycjami menu.

Zamówienie (`Order`) jest bytem domeny produktowej i **nie zna `tableId`**. Dostawa zamówienia do konkretnego stolika jest koordynowana przez główny proces obsługi gości, który posiada odpowiednie identyfikatory w swoim stanie. Rachunek (`Bill`) również nie zna `tableId` — zna wyłącznie pozycje zamówień i ich kwoty.

Szczegóły zarządzania stolikami znajdują się w procesie wspierającym `252_table_management.md`.

---

# Rachunek (Bill) i Zamówienie (Order) są odrębnymi pojęciami domenowymi

**Rachunek (Bill)** jest bytem domenowym otwieranym w momencie zajęcia stolika przez grupę gości. Istnieje przez cały czas pobytu gości przy stoliku. Zostaje zamknięty w momencie zapłaty.

Jedna grupa gości może mieć otwarty **tylko jeden rachunek** w danym czasie.

## Cykl życia obsługi gości = cykl życia rachunku (Bill)

Rachunek jest centralnym bytem procesowym obsługi grupy gości.

* Rachunek jest otwierany, gdy goście zajmują stolik.
* Rachunek gromadzi zamówienia składane przez grupę gości.
* Rachunek jest zamykany w momencie płatności.
* Po zamknięciu rachunku goście opuszczają stolik, stolik jest zwalniany, a `GuestGroup` przestaje być aktywna w systemie.

Rachunek ma uproszczony cykl życia:
* **Otwarty** — rachunek został otwarty, można dodawać do niego pozycje zamówień.
* **Zamknięty** — płatność dokonana, rachunek zakończony.

Decyzję o zamknięciu rachunku podejmuje główny proces obsługi gości, który sprawdza, czy:
* wszystkie zamówienia powiązane z rachunkiem zostały dostarczone do stolika,
* goście złożyli intencję zapłaty (np. poprosili o rachunek),
* goście dokonali płatności.

Rachunek jako byt finansowy nie posiada stanu „oczekuje na płatność". Zamknięcie rachunku bez bezpośredniej prośby gości (np. wymuszone zakończenie dnia) wykracza poza uproszczony model.

**Zamówienie (Order)** to lista pizz zamówionych przez grupę gości w jednym akcie. Zamówienie może obejmować jedną lub wiele pozycji (pizz). Zamówienie nie zawiera informacji o kosztach — jest pojęciem z domeny produktowej.

Późniejsze dokładanie kolejnych pizz do istniejącego rachunku skutkuje utworzeniem **nowego zamówienia** — nie jest to rozszerzenie poprzedniego zamówienia.

**Rachunek (Bill)** to pojęcie z domeny finansowej. Gdy zamówienie zostaje złożone, rachunek dopisuje do siebie pozycje z tego zamówienia wraz z aktualnymi cenami z menu i oblicza całkowity koszt do zapłaty.

**Polityka cen w rachunku:**
Rachunek przechowuje pozycje zamówień (`OrderLine`) wraz z kwotami. W uproszczonym modelu kwoty te pochodzą z menu w momencie dodawania pozycji do rachunku.

Model jest otwarty na przyszłe polityki gratyfikacji gościa — np. w momencie prośby o rachunek system może porównać zapisane kwoty z aktualnym cennikiem i wybrać opcję korzystniejszą dla klienta. W bardziej odległej przyszłości możliwe jest dodanie programów lojalnościowych, promocji i innych mechanizmów modyfikujących końcową kwotę.

Rachunek zna:
* pozycje zamówień (`OrderLine`) wraz z ich kwotami,
* całkowitą kwotę do zapłaty.

Rachunek **nie zna** `tableId` ani szczegółów cyklu życia zamówienia w kuchni.

**Rozliczenie zamówień:**
Rachunek nie śledzi, które zamówienia zostały dostarczone do stolika. Zna wyłącznie pozycje zamówień (`OrderLine`) oraz ich kwoty. To **główny proces obsługi gości** śledzi statusy zamówień i decyduje, czy wszystkie zamówienia powiązane z rachunkiem zostały dostarczone oraz czy można rachunek zamknąć.

Samo zamówienie kończy swój cykl na stanie **Dostarczone**. Status typu „Zrealizowane" lub „Rozliczone" jest własnością procesu, nie rachunku.

**Relacja:**
```
GuestGroup (1) ---> (1) Bill (aktywny)
Bill (1) ---> (*) Order
Order (1) ---> (*) OrderLine (pizza, ilość)
Bill agreguje pozycje z OrderLine + ceny z menu
```

**Zamówienie nie zna rachunku.**

`Order` jest bytem samowystarczalnym w obrębie swojej domeny produktowej. Zna pozycje menu (`OrderLine`) oraz swój wewnętrzny cykl życia, ale **nie przechowuje `tableId` ani `billId`**. Powiązanie zamówienia ze stolikiem i rachunkiem jest zarządzane przez główny proces obsługi gości. Rachunek agreguje pozycje zamówień w domenie finansowej.

---

# Zamówienie nie jest równoznaczne z pojedynczą pizzą

Pojedyncza pizza nie jest samodzielnym zamówieniem. Zamówienie grupuje pozycje zamówione w jednym akcie składania zamówienia przez gości (np. 4 pizze na raz).

Każda pozycja zamówienia (OrderLine) odnosi się do konkretnej pozycji w menu (MenuItem) i zawiera ilość. Zamówienie nie zawiera informacji o cenach — jest pojęciem z domeny produktowej.

---

# Kelner (Waiter) ma przypisany rewir stolików

Każdy kelner obsługuje określony zestaw stolików (rewir). Przypisanie jest stałe i definiowane przez Managera w momencie „zatrudniania" kelnera.

Host przy przydzielaniu stolika bierze pod uwagę rewir kelnera w celu optymalizacji obciążenia.

W pizzerii może pracować wielu kelnerów. Każdy stolik ma przypisanego dokładnie jednego kelnera.

---

# Kuchnia (Kitchen) przyjmuje zamówienia i rozdziela pizze między kucharzy

Zamówienia trafiają do kuchni jako całość. Kuchnia rozbija zamówienie na pojedyncze pizze i umieszcza je we wspólnej kolejce produkcyjnej. Kucharze (Chef) pobierają z tej kolejki kolejne dostępne pizze, gdy są wolni.

**Zasady:**
* zamówienie trafia do kuchni jako całość,
* kuchnia dystrybuuje pojedyncze pizze do pierwszych dostępnych kucharzy,
* każdy kucharz przygotowuje jedną pizzę naraz,
* kuchnia śledzi postęp przygotowania zamówienia,
* zamówienie jest oznaczane jako gotowe dopiero wtedy, gdy wszystkie jego pozycje zostały przygotowane,
* czas przygotowania pojedynczej pizzy jest stały i konfigurowalny przez Managera,
* całkowity czas realizacji zamówienia zależy od liczby pizz w zamówieniu oraz liczby pracujących kucharzy.

Przykład:
* 1 kucharz, zamówienie na 10 pizz, czas 1 pizzy = 10 min → łączny czas: 100 minut
* 2 kucharzy, zamówienie na 10 pizz, czas 1 pizzy = 10 min → łączny czas: 50 minut

---

# Czas przygotowania pizzy jest parametrem globalnym

Czas potrzebny na przygotowanie jednej pizzy jest stały dla całej pizzerii, niezależny od typu pizzy. Jest definiowany przez Managera w panelu konfiguracyjnym.

W przyszłości model może zostać rozszerzony o różne czasy dla różnych pizz, ale na ten moment przyjmujemy uproszczenie.

---

# Dostawa zamówienia odbywa się po jego pełnym przygotowaniu

Kuchnia przygotowuje wszystkie pozycje zamówienia. Gdy całe zamówienie jest gotowe, kuchnia zgłasza kelnerowi gotowość do odbioru.

Kelner odbiera gotowe zamówienie z kuchni i dostarcza je do stolika jako całość. Zamówienie ma jeden moment „gotowe" i jeden moment „dostarczone".

Z punktu widzenia domeny zamówienie jest uznawane za zrealizowane, gdy wszystkie jego pozycje zostały dostarczone do stolika.

---

# Host jest jedynym punktem wejścia gości do systemu obsługi

W pizzerii jest dokładnie **jeden Host**. Host jest zawsze dostępny i automatycznie obsługuje przychodzących gości.

Host nie otwiera rachunku — jego rolą jest wyłącznie powitanie gości i przydzielenie stolika. Rachunek otwiera kelner przypisany do stolika.

---

# Menu (Menu) jest zarządzane przez Managera

Manager definiuje listę dostępnych pizz (MenuItem). Każda pozycja menu zawiera:

* nazwę,
* podstawowe składniki (opis),
* cenę.

Kuchnia widzi pełne szczegóły (nazwa, składniki, sposób przyrządzania — jeśli zostanie dodany w przyszłości), ale dla gości widoczne są wyłącznie nazwa, podstawowe składniki i cena.

Menu nie jest statyczne — Manager może dodawać, modyfikować i usuwać pozycje.

---

# Manager nie uczestniczy w procesie obsługi gości

Manager nie pojawia się w procesie obsługi grupy gości w trakcie jej trwania. Jego rola ogranicza się do konfiguracji pizzerii:

* zarządzanie stolikami,
* zarządzanie menu,
* zatrudnianie i zwalnianie personelu (kelnerzy, kucharze),
* definiowanie globalnych parametrów (czas przygotowania pizzy),
* zarządzanie statusem pizzerii (otwarta / zamknięta).

---

# Pizzeria ma status otwarta / zamykana / zamknięta

System rozróżnia trzy stany pizzerii:

* **Otwarta** — pizzeria obsługuje gości, działają wszystkie procesy operacyjne.
* **Zamykana** — pizzeria nie przyjmuje nowych grup gości, ale obsługuje istniejące otwarte rachunki do końca.
* **Zamknięta** — pizzeria nie przyjmuje nowych gości, nie ma aktywnych rachunków ani zamówień.

**Konsekwencje:**

| Stan | Nowe grupy gości | Nowe zamówienia do istniejących rachunków | Płatności | Konfiguracja na żywo |
|------|------------------|-------------------------------------------|-----------|----------------------|
| Otwarta | ✅ tak | ✅ tak | ✅ tak | ✅ z ograniczeniami |
| Zamykana | ❌ nie | ✅ tak | ✅ tak | ✅ z ograniczeniami |
| Zamknięta | ❌ nie | ❌ nie | ❌ nie | ✅ bez ograniczeń |

* Host przyjmuje nowe grupy gości wyłącznie, gdy pizzeria jest otwarta.
* W stanie zamykanej goście przy otwartych rachunkach mogą dokładać kolejne zamówienia.
* Pizzeria automatycznie przechodzi ze stanu zamykanej do zamkniętej, gdy wszystkie rachunki są zamknięte i wszystkie stoliki są wolne.
* Przed otwarciem pizzerii Manager musi zapewnić minimum jednego kelnera i jednego kucharza oraz upewnić się, że wszystkie stoliki mają przypisanego kelnera.
* Podczas pracy pizzerii (stany otwarta lub zamykana) nie można zwolnić ostatniego kelnera ani ostatniego kucharza.

---

# Brak Magazynu i Kasy w bieżącym zakresie

Celowo na ten moment nie implementujemy:

* **Magazynu produktów spożywczych** — zakładamy nieograniczoną dostępność składników,
* **Kasy** — płatność jest symboliczna, bez rozliczeń, paragonów ani raportowania dziennego.

Być może zostanie to zrobione w kolejnej iteracji. Model domenowy powinien być otwarty na takie rozszerzenie.

---

# Model domenowy powinien być otwarty na rozwój

Na obecnym etapie projektu świadomie unikamy ograniczeń, które mogłyby utrudnić rozwój systemu.

Nowe funkcjonalności — takie jak magazyn, kasa, rezerwacje stolików, system napiwków, modyfikacje zamówień — powinny być możliwe do dodania bez konieczności przebudowy modelu domenowego.

---

# Open Questions

## ✅ OQ-001 — Czy zamówienie może być anulowane po przekazaniu do kuchni?

**Decyzja:** Zamówienie nie może być anulowane po przekazaniu do kuchni.

Po przekazaniu zamówienia do kuchni jest ono realizowane do końca. Brak anulowania upraszcza cykl życia zamówienia i eliminuje konieczność obsługi zwrotów oraz cofania składników.

## ✅ OQ-002 — Czy grupa gości może zmienić stolik w trakcie wizyty?

**Decyzja:** Grupa gości nie może zmienić stolika w trakcie wizyty.

Grupa pozostaje przy stoliku przydzielonym przez Hosta przez cały cykl wizyty. Funkcjonalność zmiany stolika nie wnosi istotnej wartości do uproszczonego modelu.

## ✅ OQ-003 — Czy rachunek może być dzielony?

**Decyzja:** Rachunek nie może być dzielony.

Cała grupa gości płaci jednym rachunkiem. `GuestGroup` nie jest rozbijana na osoby, więc nie ma podstaw do dzielenia płatności.

## ✅ OQ-004 — Czy kucharz może przygotowywać wiele pizz równolegle?

**Decyzja:** Jeden kucharz przygotowuje jedną pizzę naraz.

Dopuszczamy natomiast wielu kucharzy pracujących równolegle, co daje dynamikę symulacji bez komplikowania modelu pojedynczego kucharza.

## ✅ OQ-005 — Czy menu może zawierać pozycje czasowo niedostępne?

**Decyzja:** Menu zawiera wyłącznie dostępne pozycje.

Nie modelujemy stanu magazynowego składników ani czasowej niedostępności — to wykracza poza uproszczoną domenę symulacji.

## ✅ OQ-006 — Czy goście mogą zamawiać spoza menu?

**Decyzja:** Goście mogą zamawiać wyłącznie pozycje z menu.

Zamówienia są składane z gotowych pozycji menu. Brak modyfikacji, dodatków i zamówień spoza menu upraszcza model zamówienia.

## ✅ OQ-007 — Czy zamówienie powinno zawierać szacowany czas realizacji?

**Decyzja:** Szacowany czas realizacji **nie jest częścią modelu zamówienia**.

Kelner może przekazywać gościom informację o szacowanym czasie oczekiwania, ale jest to wartość wyliczana na bieżąco (projekcja / read model) na podstawie aktualnego obciążenia kuchni i kolejki zamówień. Nie jest trwały w `Order`.

## ✅ OQ-008 — Jak definiowany jest „czas, gdy kelner ma czas"?

**Decyzja:** Każdy kelner ma własną kolejkę zadań i wykonuje je sekwencyjnie, jedno na raz.

„Czas, gdy kelner ma czas" oznacza brak innych zadań w kolejce. Do kolejki trafiają m.in.:
- przyjęcie zamówienia od gości,
- przekazanie zamówienia do kuchni,
- odebranie gotowego zamówienia z kuchni,
- dostarczenie zamówienia do stolika,
- obsługa płatności.

**Otwarte:** Czy kolejka zadań kelnera jest typu FIFO, czy stosuje priorytety (np. dostarczenie gotowej pizzy przed przyjęciem nowego zamówienia)? Decyzja do rozstrzygnięcia na etapie modelowania procesów.
