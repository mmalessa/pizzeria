# Domain Decisions

## Cel dokumentu

Dokument zawiera decyzje domenowe podjęte podczas analizy biznesowej projektu Pizzeria.

Stanowią one podstawę dalszego modelowania domeny oraz projektowania procesów biznesowych.

---

# Grupa Gości (GuestGroup) jest podstawowym aktorem domenowym

W systemie Pizzeria podstawowym aktorem po stronie klienta jest **GuestGroup** — grupa osób przychodząca do lokalu i obsługiwana jako jedna całość.

System nie modeluje pojedynczych gości ani nie rozróżnia osób wewnątrz grupy. Decyzje o zamówieniach, płatnościach i zachowaniach są podejmowane na poziomie grupy.

---

# Stolik (Table) jest zasobem o ograniczonej pojemności

Każdy stolik posiada określoną maksymalną liczbę miejsc. Host przydziela stolik biorąc pod uwagę:

* liczbę osób w grupie gości,
* optymalizację obsługi pod kątem kelnerów (kelnerzy powinni mieć zbalansowane rewiry).

Stolik może znajdować się w jednym z trzech stanów:
* **wolny** — dostępny dla nowej grupy gości,
* **zajęty** — przypisany do aktualnie obsługiwanego rachunku,
* **niedostępny** — wyłączony z użytkowania przez Managera.

---

# Rachunek (Bill) i Zamówienie (Order) są odrębnymi pojęciami domenowymi

**Rachunek (Bill)** jest bytem domenowym otwieranym w momencie zajęcia stolika przez grupę gości. Istnieje przez cały czas pobytu gości przy stoliku. Zostaje zamknięty w momencie zapłaty.

Jedna grupa gości może mieć otwarty **tylko jeden rachunek** w danym czasie.

**Zamówienie (Order)** to lista pizz zamówionych przez grupę gości w jednym akcie. Zamówienie może obejmować jedną lub wiele pozycji (pizz). Zamówienie nie zawiera informacji o kosztach — jest pojęciem z domeny produktowej.

Późniejsze dokładanie kolejnych pizz do istniejącego rachunku skutkuje utworzeniem **nowego zamówienia** — nie jest to rozszerzenie poprzedniego zamówienia.

**Rachunek (Bill)** to pojęcie z domeny finansowej. Gdy zamówienie zostaje złożone, rachunek dopisuje do siebie pozycje z tego zamówienia wraz z aktualnymi cenami z menu i oblicza całkowity koszt do zapłaty.

**Relacja:**
```
GuestGroup (1) ---> (1) Bill (aktywny)
Bill (1) ---> (*) Order
Order (1) ---> (*) OrderLine (pizza, ilość)
Bill agreguje pozycje z OrderLine + ceny z menu
```

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

# Kuchnia (Kitchen) działa jako wspólna kolejka

Zamówienia trafiają do wspólnej kolejki zamówień w kuchni. Kucharze (Chef) sami pobierają zamówienia z kolejki, gdy są wolni.

**Zasady:**
* zamówienie jest realizowane przez pierwszego dostępnego kucharza,
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

# Dostawa pizzy odbywa się indywidualnie

Kelner dostarcza pojedyncze pizze do stolika wtedy, gdy ma czas — nie czeka na zakończenie całego zamówienia.

Oznacza to, że zamówienie jako całość nie ma jednego momentu „dostarczenia". Zamiast tego każda pizza ma własny cykl: zamówiona → w przygotowaniu → gotowa → dostarczona.

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
* definiowanie globalnych parametrów (czas przygotowania pizzy).

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

## OQ-001 — Czy zamówienie może być anulowane po przekazaniu do kuchni?

Nie rozstrzygnięto, czy goście mogą anulować zamówienie (lub jego część) po tym, jak kelner przekaże je do kuchni, oraz jakie byłyby tego konsekwencje domenowe.

## OQ-002 — Czy grupa gości może zmienić stolik w trakcie wizyty?

Nie rozstrzygnięto, czy istnieje scenariusz przesiadki gości do innego stolika z jednoczesnym przeniesieniem rachunku.

## OQ-003 — Czy rachunek może być dzielony?

Nie rozstrzygnięto, czy grupa gości może prosić o podział rachunku na części (np. per osoba).

## OQ-004 — Czy kucharz może przygotowywać wiele pizz równolegle?

Aktualny model zakłada, że kucharz pracuje nad jedną pizzą na raz. W przyszłości można rozważyć równoległe przygotowywanie (np. pieczenie wielu pizz w jednym piecu).

## OQ-005 — Czy menu może zawierać pozycje czasowo niedostępne?

Obecnie zakładamy, że wszystkie pozycje menu są zawsze dostępne. W przyszłości można rozważyć flagę dostępności powiązaną z magazynem.

## OQ-006 — Czy goście mogą zamawiać spoza menu?

Nie rozstrzygnięto, czy system powinien obsługiwać niestandardowe zamówienia (np. dodatkowe składniki, pół pizzy).

## OQ-007 — Czy zamówienie powinno zawierać szacowany czas realizacji?

Kelner zwraca gościom informację o szacowanym czasie oczekiwania. Nie rozstrzygnięto, czy czas ten jest częścią modelu zamówienia, czy tylko wartością wyliczaną na bieżąco.

## OQ-008 — Jak definiowany jest „czas, gdy kelner ma czas"?

Kelner dostarcza pizzę „wtedy, gdy ma czas". Nie rozstrzygnięto, czy istnieje kolejka zadań kelnera, czy też dostawa odbywa się natychmiast po przygotowaniu przez kucharza.
