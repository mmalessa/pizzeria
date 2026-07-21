# Role domenowe

## Cel dokumentu

Dokument opisuje wszystkie role domenowe zidentyfikowane w systemie Pizzeria — ich zakres odpowiedzialności, zasięg oraz relacje między nimi.

Role nie są pojęciami technicznymi (uprawnienia, ACL). Są pojęciami biznesowymi opisującymi, kto co robi w domenie.

---

## Podział ról

System Pizzeria rozróżnia trzy główne grupy aktorów:

* **Klienci (Goście)** — grupy osób konsumujących posiłki
* **Personel (Front of House + Kitchen)** — automatyczne role zachowań systemowych
* **Zarząd (Manager)** — administrator konfiguracji pizzerii

---

## Role klientów

### GuestGroup (Grupa Gości)

**Zasięg:** jedna wizyta w pizzerii

**Odpowiedzialności:**
* pojawia się w pizzerii (inicjuje proces obsługi),
* akceptuje lub wybiera stolik (jeśli dana jest taka możliwość),
* przegląda menu i wybiera pizze,
* składa zamówienia u kelnera,
* konsumuje dostarczone pizze,
* decyduje o zamówieniu kolejnych pizz,
* prosi o rachunek i dokonuje zapłaty,
* opuszcza lokal.

**Kto przypisuje:** rola przyjmowana przez użytkownika ludzkiego przez Web GUI — nie jest przypisywana przez system

**Uwaga domenowa:** GuestGroup nie jest tożsama z żadnym zarejestrowanym kontem. Jest to pojęcie tymczasowe, istniejące wyłącznie w czasie jednej wizyty. Użytkownik może jednocześnie sterować wieloma niezależnymi grupami gości — każda grupa jest osobną instancją procesu obsługi, niezależną od pozostałych. Symulacja wielu grup pozwala na obserwację równoległych procesów w pizzerii.

---

## Role personelu (Front of House)

### Host

**Zasięg:** cała pizzeria

**Odpowiedzialności:**
* witają grupę gości przy wejściu,
* wybierają wolny stolik odpowiedni pod kątem liczby osób,
* optymalizują przydział stolików pod kątem obciążenia kelnerów,
* umieszczają gości przy wybranym stoliku.

**Kto przypisuje:** rola wbudowana — w pizzerii jest dokładnie jeden Host

**Ograniczenie:** Host nie otwiera rachunku ani nie przyjmuje zamówień. Po przydzieleniu stolika kontrolę przejmuje kelner.

---

### Waiter (Kelner)

**Zasięg:** przypisane stoliki

**Odpowiedzialności:**
* witają gości przy stoliku po przydzieleniu przez Hosta,
* otwierają rachunek dla grupy gości,
* przyjmują zamówienia na pizze,
* przekazują zamówienia do kuchni,
* informują gości o szacowanym czasie oczekiwania,
* odbierają gotowe zamówienie z kuchni,
* dostarczają zamówienie do stolika,
* przyjmują zapłatę i zamykają rachunek.

**Kto przypisuje:** Manager przy zatrudnianiu — przypisuje stoliki

**Ograniczenie:** Kelner może obsługiwać wyłącznie przypisane do niego stoliki.

---

## Role personelu (Kitchen)

### Kitchen (Kuchnia)

**Zasięg:** cała kuchnia jako rolę koordynująca przygotowanie zamówień

**Odpowiedzialności:**
* przyjmuje zamówienia od kelnerów jako całość,
* rozbija zamówienie na pojedyncze pizze i umieszcza je w kolejce produkcyjnej,
* dystrybuuje pizze do wolnych kucharzy,
* śledzi postęp przygotowania każdego zamówienia,
* oznacza zamówienie jako gotowe do odbioru, gdy wszystkie jego pozycje zostały przygotowane,
* zgłasza kelnerowi gotowość zamówienia do odbioru.

**Kto przypisuje:** rola wbudowana — kuchnia istnieje jako wspólna kolejka koordynująca pracę kucharzy

---

### Chef (Kucharz)

**Zasięg:** kuchnia (wspólna kolejka produkcyjna pizz)

**Odpowiedzialności:**
* pobierają kolejne pizze z wspólnej kolejki produkcyjnej kuchni,
* przygotowują pizzę zgodnie z recepturą,
* zgłaszają kuchni zakończenie przygotowania danej pizzy.

**Kto przypisuje:** Manager przy zatrudnianiu

**Uwaga domenowa:** Kucharze nie mają przypisanych konkretnych zamówień z góry. Pobierają pojedyncze pizze z wewnętrznej kolejki produkcyjnej kuchni. Liczba aktywnych kucharzy wpływa bezpośrednio na czas realizacji zamówień.

---

## Role zarządcze

### Manager

**Zasięg:** cała pizzeria (konfiguracja)

**Odpowiedzialności:**
* definiuje stoliki (liczba miejsc),
* zarządza menu (dodawanie, edycja, usuwanie pozycji),
* zatrudnia i zwalnia kelnerów oraz kucharzy,
* przypisuje stoliki do kelnerów,
* ustala globalne parametry (czas przygotowania pojedynczej pizzy),
* zarządza statusem pizzerii (otwarta / zamknięta).

**Kto przypisuje:** rola wbudowana — jeden Manager w systemie

**Uwaga domenowa:** Manager nie uczestniczy w bieżącej obsłudze gości. Jego rola ogranicza się do konfiguracji systemu i personelu.

---

## Relacje między rolami

* **Host** → przydziela stolik → **GuestGroup**, uwzględniając liczbę gości oraz obciążenie kelnerów (optymalizacja przydziału stolików)
* **Waiter** → otwiera rachunek i obsługuje → **GuestGroup** (przez cały cykl wizyty)
* **Waiter** → składa zamówienie → **Kitchen**
* **Kitchen** → rozbija zamówienie i dystrybuuje pizze → **Chef**
* **Chef** → przygotowuje pizzę i zgłasza gotowość → **Kitchen**
* **Kitchen** → oznacza całe zamówienie jako gotowe → **Waiter**
* **Waiter** → odbiera gotowe zamówienie i dostarcza → **GuestGroup**
* **GuestGroup** → dokonuje płatności → **Waiter**
* **Waiter** → zamyka rachunek → **Bill**
* **Manager** → konfiguruje zasoby (stoliki, menu, personel) wykorzystywane przez wszystkie pozostałe role

---

## Podsumowanie mapy ról

### Relacje konfiguracyjne

* **Manager** definiuje stoliki, menu oraz personel — zasoby wykorzystywane przez pozostałe role.
* **Manager** zatrudnia **kelnerów (Waiter)** i przypisuje im stoliki.
* **Manager** zatrudnia **kucharzy (Chef)** do pracy w kuchni.

### Relacje operacyjne

* **Host** wita **GuestGroup** i przydziela jej stolik.
* **Host** przy przydzielaniu stolika uwzględnia obciążenie **kelnera (Waiter)** — liczbę przypisanych mu stolików.
* **Waiter** otwiera rachunek dla **GuestGroup** po przydzieleniu stolika.
* **GuestGroup** składa zamówienie u **kelnera (Waiter)**.
* **Waiter** przekazuje zamówienie do **kuchni (Kitchen)**.
* **Kitchen** przygotowuje zamówienie i zgłasza **kelnerowi (Waiter)** gotowość do odbioru.
* **Waiter** odbiera gotowe zamówienie z **kuchni (Kitchen)** i dostarcza je do **GuestGroup**.
* **GuestGroup** dokonuje płatności u **kelnera (Waiter)**, który zamyka rachunek.
* Po zamknięciu rachunku **GuestGroup** opuszcza stolik, a stolik staje się wolny.

### Zaangażowanie ról w główny proces

| Krok | Rola | Działanie |
|------|------|-----------|
| 1 | **GuestGroup** | Pojawia się w pizzerii |
| 2 | **Host** | Przydziela stolik |
| 3 | **Waiter** | Otwiera rachunek |
| 4 | **GuestGroup** | Składa zamówienie |
| 5 | **Waiter** | Przekazuje zamówienie do kuchni |
| 6 | **Kitchen / Chef** | Przygotowuje zamówienie |
| 7 | **Kitchen** | Zgłasza gotowość zamówienia |
| 8 | **Waiter** | Odbiera i dostarcza zamówienie |
| 9 | **GuestGroup** | Konsumuje i prosi o rachunek |
| 10 | **Waiter** | Przyjmuje płatność i zamyka rachunek |

---

## Hotspoty

### ✅ HS-012-001 — Czy kelner może obsługiwać stoliki, które nie są do niego przypisane?

**Decyzja:** Nie. Kelner obsługuje wyłącznie stoliki przypisane do niego przez Managera.

Przypisanie jest stałe i definiowane przez Managera. Host przy przydziale stolika optymalizuje obciążenie kelnerów, ale sam kelner nie może przejąć stolika, który do niego nie należy. Dynamiczne przejmowanie stolików znacząco komplikowałoby model personelu bez istotnej wartości w uproszczonej symulacji.

### ✅ HS-012-002 — Czy Host może odmówić przyjęcia gości?

**Decyzja:** Tak. Host odmawia przyjęcia gości, gdy nie ma wolnego stolika odpowiedniego pod kątem liczby osób w grupie.

Odmówiona grupa opuszcza pizzerię i nie tworzy rachunku. Nie modelujemy kolejki oczekujących gości — wprowadziłaby ona dodatkową złożoność (timeout'y, zarządzanie kolejką) bez istotnej wartości w uproszczonym modelu.

### ✅ HS-012-003 — Czy Manager może modyfikować konfigurację w trakcie pracy pizzerii?

**Decyzja:** Tak. Manager może modyfikować konfigurację na żywo, ale system blokuje zmiany naruszające aktualnie trwające procesy.

**Dozwolone na żywo:**
* zmiana czasu przygotowania pizzy — wpływa na nowe zamówienia,
* dodawanie / edycja / usuwanie pozycji menu, które nie są obecnie w realizacji,
* dodawanie nowych stolików,
* zatrudnianie nowych kelnerów i kucharzy.

**Zablokowane lub ograniczone:**
* usunięcie stolika, który jest aktualnie zajęty,
* zmiana liczby miejsc przy zajętym stoliku,
* usunięcie pozycji menu znajdującej się w aktywnym zamówieniu,
* zwolnienie kelnera, który ma otwarty rachunek,
* zwolnienie kucharza, który aktualnie przygotowuje pizzę.

### ✅ HS-012-004 — Co się dzieje, gdy ostatni kelner lub kucharz zostanie zwolniony?

**Decyzja:** Podczas pracy pizzerii nie można zwolnić ostatniego kelnera ani ostatniego kucharza.

Pizzeria wymaga minimum jednego kelnera i minimum jednego kucharza do funkcjonowania. System blokuje zwolnienie, które doprowadziłoby do braku przedstawiciela danej roli podczas otwartej pizzerii.

**Stoliki bez przypisanego kelnera:** Nie mogą istnieć. Każdy stolik musi mieć przypisanego aktywnego kelnera. Host przydziela gościom wyłącznie stoliki obsługiwane przez aktywnego kelnera.

---

## Status pizzerii: otwarta / zamknięta

### Decyzja

System rozróżnia trzy stany pizzerii:
* **Otwarta** — pizzeria obsługuje gości, działają wszystkie procesy operacyjne.
* **Zamykana** — pizzeria nie przyjmuje nowych grup gości, ale obsługuje istniejące otwarte rachunki.
* **Zamknięta** — pizzeria nie przyjmuje nowych gości, nie ma aktywnych rachunków ani zamówień.

### Konsekwencje

| Stan | Nowe grupy gości | Nowe zamówienia do istniejących rachunków | Płatności | Konfiguracja na żywo |
|------|------------------|-------------------------------------------|-----------|----------------------|
| Otwarta | ✅ tak | ✅ tak | ✅ tak | ✅ z ograniczeniami |
| Zamykana | ❌ nie | ✅ tak | ✅ tak | ✅ z ograniczeniami |
| Zamknięta | ❌ nie | ❌ nie | ❌ nie | ✅ bez ograniczeń |

**Pizzeria otwarta:**
* Host może przyjmować nowe grupy gości.
* Kelnerzy i kucharze są aktywni i wykonują swoje zadania.
* Manager może modyfikować konfigurację z ograniczeniami opisanymi w HS-012-003.
* Nie można zwolnić ostatniego kelnera ani ostatniego kucharza.

**Pizzeria zamykana:**
* Host nie przyjmuje nowych grup gości.
* Istniejące grupy mogą dokładać kolejne zamówienia do otwartych rachunków.
* Kelnerzy i kucharze nadal obsługują otwarte rachunki.
* Manager może modyfikować konfigurację z ograniczeniami.
* Pizzeria automatycznie przechodzi do stanu zamkniętej, gdy wszystkie rachunki są zamknięte i wszystkie stoliki są wolne.

**Pizzeria zamknięta:**
* Host nie przyjmuje nowych gości.
* Nie ma aktywnych rachunków ani zamówień.
* Manager może swobodnie modyfikować konfigurację: stoliki, menu, personel, parametry.
* Dopuszczalne jest zwolnienie wszystkich kelnerów i kucharzy.
* Przed ponownym otwarciem pizzerii Manager musi zapewnić minimum jednego kelnera i jednego kucharza oraz upewnić się, że wszystkie stoliki mają przypisanego kelnera.
