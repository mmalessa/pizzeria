# Proces: Zarządzanie stolikami

## Cel procesu

Proces opisuje zarządzanie stolikami w pizzerii — ich definiowanie, przypisywanie do kelnerów oraz śledzenie stanu zajętości. Proces jest odpowiedzialny za zasób `Table`, który jest wykorzystywany przez główny proces obsługi gości (`200_guest_service.md`) oraz proces przyjęcia gości (`211_guest_arrival.md`).

## Zakres

* **Początek procesu:** `Manager` tworzy lub modyfikuje konfigurację stolików.
* **Koniec procesu:** stolik został zwolniony po zakończeniu obsługi gości lub wycofany z konfiguracji przez `Manager` (gdy jest wolny).

## Role zaangażowane

* **Manager** — definiuje stoliki, określa liczbę miejsc, przypisuje stoliki do kelnerów.
* **Host** — korzysta ze stolików podczas przyjmowania gości (z perspektywy `211_guest_arrival.md`).
* **Waiter** — obsługuje przypisane do niego stoliki.
* **Główny proces obsługi gości** — koordynuje zwolnienie stolika po zamknięciu rachunku i opuszczeniu lokalu.

## Cykl życia stolika

| Stan | Opis |
|------|------|
| **wolny** | Stolik jest dostępny i może zostać przydzielony nowej grupie gości. |
| **zajęty** | Stolik jest aktualnie przypisany do obsługiwanej grupy gości. |

## Przebieg procesu operacyjnego

```mermaid
flowchart TD

A[Stolik w stanie wolny]
--> B[Host przydziela stolik GuestGroup - 211]
--> C[Stolik w stanie zajęty]
--> D[Główny proces kończy obsługę]
--> E[GuestGroup opuszcza lokal]
--> F[Stolik w stanie wolny]
```

## Szczegóły kroków

### 1. Konfiguracja stolików przez Managera

`Manager` definiuje stoliki w pizzerii. Każdy stolik ma:
* unikalny identyfikator,
* liczbę miejsc,
* przypisanego kelnera.

Stolik może istnieć w konfiguracji bez przypisanego kelnera. Taki stolik nie bierze udziału w obsłudze gości. Host przyjmuje gości wyłącznie do stolików, które mają przypisanego aktywnego kelnera.

### 2. Przydzielenie stolika gościom

`Host` wyszukuje wolny stolik spełniający warunki opisane w `211_guest_arrival.md`:
* stolik jest w stanie **wolny**,
* liczba miejsc jest wystarczająca dla liczby gości,
* stolik ma przypisanego aktywnego kelnera (w stanie **Aktywny**).

Po wybraniu stolika `Host` zmienia jego stan na **zajęty**. Główny proces obsługi gości zapisuje powiązanie `GuestGroup` ↔ `Table`.

### 3. Zwalnianie stolika

Po zamknięciu rachunku i opuszczeniu lokalu przez gości główny proces zleca zwolnienie stolika. Stolik przechodzi ze stanu **zajęty** do stanu **wolny** i może zostać ponownie przydzielony nowej grupie gości.

Szczegóły zwalniania stolika są realizowane w tym procesie, ale inicjowane przez główny proces obsługi gości (`200_guest_service.md`).

## Konfiguracja na żywo

`Manager` może modyfikować konfigurację stolików na żywo, jednak system blokuje zmiany naruszające aktualnie trwające procesy:

**Dozwolone na żywo:**
* dodawanie nowych stolików,
* przypisanie wolnego stolika do kelnera lub zmiana jego przypisania,
* edycja parametrów stolika, który nie jest aktualnie zajęty.

**Zablokowane lub ograniczone:**
* usunięcie stolika w stanie **zajęty**,
* zmiana liczby miejsc przy zajętym stoliku,
* usunięcie ostatniego stolika w pizzerii,
* zmiana przypisania kelnera do zajętego stolika,
* pozostawienie zajętego stolika bez aktywnego kelnera (np. poprzez zwolnienie jedynego aktywnego kelnera tego stolika).

## Dane wyjściowe procesu

W wyniku zarządzania stolikami:
* stoliki są zdefiniowane w konfiguracji pizzerii,
* stoliki mogą istnieć bez przypisanego kelnera, ale tylko stoliki z aktywnym kelnerem mogą być używane w obsłudze gości,
* stoliki są w stanie **wolny** lub **zajęty**,
* zwolnione stoliki mogą być ponownie przydzielone nowym gościom.

Z punktu widzenia procesu domenowego usunięcie stolika polega na jego wycofaniu z konfiguracji. Ewentualne przechowywanie historii usuniętych stolików dla celów raportowania, logów czy statystyk jest decyzją techniczną leżącą poza tym procesem.

## Granice procesu

Proces zarządzania stolikami **nie obejmuje**:
* przyjęcia gości do lokalu — to proces `211_guest_arrival.md`,
* zarządzania personelem i przypisaniami stolików do kelnerów — to proces `254_staff_management.md`,
* obsługi rachunków i zamówień — to procesy `212_bill_management.md` i `213_ordering.md`,
* zarządzania cyklem życia pizzerii — to proces `255_pizzeria_lifecycle.md`.

## Decyzje domenowe zastosowane w tym procesie

* Stolik posiada określoną liczbę miejsc.
* Stolik może być w stanie **wolny** lub **zajęty**.
* Stolik może istnieć bez przypisanego kelnera, ale nie bierze wtedy udziału w obsłudze gości.
* Stolik zajęty nie może zostać usunięty ani zmodyfikowany w sposób naruszający trwającą obsługę.
* Zwolnienie stolika następuje po zamknięciu rachunku i opuszczeniu lokalu przez gości.

## Decyzje ostateczne

* ✅ **Czy stolik może istnieć bez przypisanego kelnera?** Tak. Stolik może być zdefiniowany w konfiguracji bez przypisanego kelnera, ale nie bierze wtedy udziału w obsłudze gości. Host przydziela gościom wyłącznie stoliki z aktywnym kelnerem.
* ✅ **Czy stolik musi mieć przypisanego kelnera?** Nie. Przypisanie kelnera nie jest wymagane do istnienia stolika w konfiguracji, ale jest wymagane, aby stolik mógł być użyty w obsłudze gości.
* ✅ **Czy stolik może zmienić kelnera w trakcie dnia?** Tak, ale tylko gdy stolik jest wolny. Zajętego stolika nie można przypisać do innego kelnera. Ponadto zajętego stolika nie można pozostawić bez aktywnego kelnera.
* ✅ **Czy liczba miejsc przy stoliku może być modyfikowana na żywo?** Tak, ale tylko dla wolnych stolików. Zmiana liczby miejsc przy zajętym stoliku jest zablokowana.
* ✅ **Czy można usunąć stolik, który jest aktualnie zajęty?** Nie. Usunięcie stolika z konfiguracji jest możliwe wyłącznie, gdy stolik jest wolny.
* ✅ **Czy można usunąć ostatni stolik w pizzerii?** Nie. Pizzeria wymaga co najmniej jednego stolika do funkcjonowania. System blokuje usunięcie ostatniego aktywnego stolika.
* ✅ **Czy zwolnienie stolika jest częścią głównego procesu obsługi gości czy procesu zarządzania stolikami?** Zwolnienie stolika jest operacją realizowaną w ramach procesu zarządzania stolikami (`252_table_management.md`), ale inicjowaną przez główny proces obsługi gości (`200_guest_service.md`) po zamknięciu rachunku i opuszczeniu lokalu.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym procesie.
