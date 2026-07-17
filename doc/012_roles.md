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

**Zasięg:** przypisany rewir stolików

**Odpowiedzialności:**
* witają gości przy stoliku po przydzieleniu przez Hosta,
* otwierają rachunek dla grupy gości,
* przyjmują zamówienia na pizze,
* przekazują zamówienia do kuchni,
* informują gości o szacowanym czasie oczekiwania,
* odbierają gotowe pizze z kuchni,
* dostarczają pizze do stolika wtedy, gdy mają czas,
* przyjmują zapłatę i zamykają rachunek.

**Kto przypisuje:** Manager przy zatrudnianiu — definiuje rewir (zbiór stolików)

**Ograniczenie:** Kelner może obsługiwać wyłącznie stoliki z przypisanego mu rewiru.

---

## Role personelu (Kitchen)

### Chef (Kucharz)

**Zasięg:** kuchnia (wspólna kolejka zamówień)

**Odpowiedzialności:**
* pobierają zamówienia z wspólnej kolejki,
* przygotowują pizze zgodnie z recepturą,
* oznaczają zamówienie jako gotowe do odbioru przez kelnera.

**Kto przypisuje:** Manager przy zatrudnianiu

**Uwaga domenowa:** Kucharze nie mają przypisanych konkretnych zamówień z góry. Pobierają pierwsze dostępne z kolejki. Liczba aktywnych kucharzy wpływa bezpośrednio na czas realizacji zamówień.

---

## Role zarządcze

### Manager

**Zasięg:** cała pizzeria (konfiguracja)

**Odpowiedzialności:**
* definiuje stoliki (liczba miejsc, dostępność),
* zarządza menu (dodawanie, edycja, usuwanie pozycji),
* zatrudnia i zwalnia kelnerów oraz kucharzy,
* przypisuje stoliki do rewirów kelnerów,
* ustala globalne parametry (czas przygotowania pojedynczej pizzy).

**Kto przypisuje:** rola wbudowana — jeden Manager w systemie

**Uwaga domenowa:** Manager nie uczestniczy w bieżącej obsłudze gości. Jego rola ogranicza się do konfiguracji systemu i personelu.

---

## Relacje między rolami

* **Host** → przydziela stolik → **GuestGroup** (inicjuje proces)
* **Host** → bierze pod uwagę rewir → **Waiter** (optymalizacja przydziału)
* **Waiter** → otwiera rachunek i obsługuje → **GuestGroup** (przez cały cykl wizyty)
* **Waiter** → składa zamówienie → **Chef** (przez kolejkę kuchenną)
* **Chef** → przygotowuje pizzę → **Waiter** odbiera i dostarcza
* **Manager** → konfiguruje zasoby (stoliki, menu, personel) wykorzystywane przez wszystkie pozostałe role

---

## Podsumowanie mapy ról

```
┌─────────────────────────────────────────────────────────────┐
│                        Manager                               │
│  (konfiguracja: stoliki, menu, personel, parametry)         │
└─────────────┬───────────────┬───────────────────────────────┘
              │               │
              ▼               ▼
┌─────────────────┐   ┌─────────────────┐
│      Host       │   │   Waiter(s)     │
│  (1 w pizzerii) │   │  (rewir/stoliki)│
└────────┬────────┘   └────────┬────────┘
         │                     │
         ▼                     ▼
┌─────────────────────────────────────────┐
│           GuestGroup (Goście)           │
│        (przychodzą, zamawiają,        │
│         płacą, wychodzą)              │
└─────────────────────────────────────────┘
              │
              ▼
┌─────────────────────────────────────────┐
│           Kitchen (Kucharze)            │
│      (wspólna kolejka zamówień)       │
└─────────────────────────────────────────┘
```

---

## Hotspoty

### HS-012-001 — Czy kelner może obsługiwać stoliki spoza swojego rewiru?

Aktualny model zakłada sztywne przypisanie. W sytuacji przeciążenia jednego kelnera, a wolności innego — czy system powinien dopuszczać przejęcie stolika?

### HS-012-002 — Czy Host może odmówić przyjęcia gości?

Jeśli wszystkie stoliki są zajęte lub brakuje odpowiedniego stolika pod kątem liczby osób — czy Host odmawia przyjęcia, czy goście czekają w kolejce?

### HS-012-003 — Czy Manager może modyfikować konfigurację w trakcie pracy pizzerii?

Nie rozstrzygnięto, czy zmiana menu, stolików lub personelu wymaga „zamknięcia" pizzerii, czy może nastąpić na żywo.

### HS-012-004 — Co się dzieje, gdy ostatni kelner lub kucharz zostanie zwolniony?

Nie rozstrzygnięto, czy system dopuszcza scenariusz, w którym pizzeria działa bez kelnerów lub bez kucharzy, oraz jakie są konsekwencje domenowe.
