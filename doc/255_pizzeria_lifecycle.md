# Proces: Cykl życia pizzerii (`Pizzeria` — `Open` / `Closing` / `Closed`)

## Cel procesu

Proces opisuje cykl życia pizzerii jako całości — jej otwarcie, funkcjonowanie w stanie otwartym, rozpoczęcie zamykania oraz przejście do stanu zamkniętego. Proces definiuje, które operacje są dozwolone w poszczególnych stanach pizzerii.

## Zakres

* **Początek procesu:** pizzeria znajduje się w stanie `Closed` lub jest inicjowana po raz pierwszy.
* **Koniec procesu:** pizzeria została zamknięta i nie posiada aktywnych rachunków ani zamówień.

## Role zaangażowane

* **Manager** — zarządza cyklem życia pizzerii: otwiera ją, inicjuje zamykanie i potwierdza zamknięcie.
* **Główny proces obsługi gości** — reaguje na zmiany stanu pizzerii poprzez ograniczanie lub wznawianie obsługi nowych grup gości.
* **Host** — przestaje przyjmować nowe grupy gości w stanie `Closing`.

## Cykl życia pizzerii

| Stan | Opis |
|------|------|
| `Open` | Pizzeria obsługuje gości, działają wszystkie procesy operacyjne. |
| `Closing` | Pizzeria nie przyjmuje nowych grup gości, ale obsługuje istniejące otwarte rachunki. |
| `Closed` | Pizzeria nie przyjmuje nowych gości, nie ma aktywnych rachunków ani zamówień. |

## Przebieg procesu

```mermaid
flowchart TD

A[Pizzeria w stanie `Closed`]
--> B[Manager otwiera pizzerię]
--> C[Pizzeria w stanie `Open`]
--> D{Manager inicjuje zamykanie?}
-->|tak| E[Pizzeria w stanie `Closing`]
--> F{Wszystkie rachunki zamknięte (`Closed`) i wszystkie stoliki wolne (`Free`)?}
-->|tak| G[Pizzeria w stanie `Closed`]
```

## Szczegóły kroków

### 1. Otwarcie pizzerii

`Manager` otwiera pizzerię, przechodząc ze stanu `Closed` do stanu `Open`. Przed otwarciem `Manager` musi zapewnić:
* co najmniej jednego aktywnego (`Active`) kelnera,
* co najmniej jednego aktywnego (`Active`) kucharza,
* co najmniej jeden stolik w konfiguracji.

Czas przygotowania pojedynczej pizzy nie jest zarządzany przez tę subdomenę. Jest to parametr kontekstu **Kitchen**.

Stoliki nie muszą mieć przypisanego kelnera, ale tylko stoliki z aktywnym kelnerem mogą być używane w obsłudze gości. Jeśli brakuje któregokolwiek z wymaganych zasobów, otwarcie pizzerii jest blokowane.

### 2. Praca w stanie otwartym

W stanie `Open` pizzeria działa normalnie:
* Host przyjmuje nowe grupy gości,
* kelnerzy i kucharze realizują zamówienia,
* Manager może modyfikować konfigurację na żywo z ograniczeniami opisanymi w innych procesach wspierających.

### 3. Inicjowanie zamykania pizzerii

`Manager` może zainicjować zamykanie pizzerii, przechodząc ze stanu `Open` do stanu `Closing`. Stan `Closing` oznacza:
* Host nie przyjmuje nowych grup gości,
* istniejące otwarte (`Open`) rachunki mogą nadal otrzymywać zamówienia,
* kelnerzy i kucharze nadal obsługują otwarte rachunki,
* Manager może modyfikować konfigurację z ograniczeniami.

### 4. Automatyczne przejście do stanu zamkniętego

Pizzeria automatycznie przechodzi ze stanu `Closing` do `Closed`, gdy:
* wszystkie rachunki zostały zamknięte (`Closed`),
* wszystkie stoliki są wolne (`Free`),
* nie ma aktywnych zamówień.

W stanie `Closed`:
* Host nie przyjmuje nowych gości,
* nie ma aktywnych rachunków ani zamówień,
* Manager może swobodnie modyfikować konfigurację: stoliki, menu, personel,
* dopuszczalne jest zwolnienie wszystkich kelnerów i kucharzy.

## Konsekwencje stanów pizzerii

| Stan | Nowe grupy gości | Nowe zamówienia do istniejących rachunków | Płatności | Konfiguracja na żywo |
|------|------------------|-------------------------------------------|-----------|----------------------|
| `Open` | ✅ tak | ✅ tak | ✅ tak | ✅ z ograniczeniami |
| `Closing` | ❌ nie | ✅ tak | ✅ tak | ✅ z ograniczeniami |
| `Closed` | ❌ nie | ❌ nie | ❌ nie | ✅ bez ograniczeń |

## Dane wyjściowe procesu

W wyniku cyklu życia pizzerii:
* pizzeria znajduje się w jednym ze stanów: `Open`, `Closing`, `Closed`,
* w stanie `Open` mogą działać wszystkie procesy operacyjne,
* w stanie `Closing` kończone są istniejące obsługi, ale nie rozpoczynają się nowe,
* w stanie `Closed` konfiguracja może być swobodnie modyfikowana.

## Granice procesu

Proces cyklu życia pizzerii **nie obejmuje**:
* szczegółów obsługi gości — to procesy `200_guest_service.md`, `211_guest_arrival.md`, `212_bill_management.md`, `213_ordering.md`,
* zarządzania menu — to proces `253_menu_management.md`,
* zarządzania stolikami — to proces `252_table_management.md`,
* zarządzania personelem — to proces `254_staff_management.md`,
* zarządzania parametrami kuchni — to proces `251_kitchen_order_fulfillment.md`.

## Decyzje domenowe zastosowane w tym procesie

* Pizzeria może znajdować się w trzech stanach: `Open`, `Closing`, `Closed`.
* Stan `Closing` pozwala dokończyć obsługę istniejących gości bez przyjmowania nowych.
* Przejście ze stanu `Closing` do `Closed` jest automatyczne po zakończeniu wszystkich obsług.
* Otwarcie pizzerii wymaga minimum jednego aktywnego (`Active`) kelnera, jednego aktywnego (`Active`) kucharza i jednego stolika.

## Decyzje ostateczne

* ✅ **Czy przejście ze stanu `Closing` do `Closed` jest automatyczne?** Tak. Pizzeria automatycznie przechodzi do stanu `Closed`, gdy wszystkie rachunki są zamknięte (`Closed`), wszystkie stoliki są wolne (`Free`) i nie ma aktywnych zamówień.
* ✅ **Czy w stanie `Closing` można złożyć zamówienie do istniejącego rachunku?** Tak. Istniejące otwarte (`Open`) rachunki mogą nadal otrzymywać zamówienia, dopóki pizzeria nie przejdzie do stanu `Closed`.
* ✅ **Czy w stanie `Closing` Host może przyjąć nową grupę gości?** Nie. Host nie przyjmuje nowych grup gości w stanie `Closing`.
* ✅ **Czy w stanie `Closed` można zwolnić wszystkich kelnerów i kucharzy?** Tak. W stanie `Closed` Manager może swobodnie modyfikować konfigurację, w tym zwalniać wszystkich pracowników.
* ✅ **Czy otwarcie pizzerii wymaga przypisanych stolików do kelnera?** Nie. Otwarcie pizzerii wymaga co najmniej jednego stolika w konfiguracji oraz co najmniej jednego aktywnego (`Active`) kelnera i kucharza. Stolik nie musi mieć przypisanego kelnera, ale tylko stoliki z aktywnym (`Active`) kelnerem mogą być używane w obsłudze gości.
* ✅ **Czy Manager może zmodyfikować konfigurację w stanie `Closed` bez ograniczeń?** Tak. W stanie `Closed` nie ma aktywnych rachunków ani zamówień, więc Manager może swobodnie zarządzać stolikami, menu i personelem.
* ✅ **Czy czas przygotowania pizzy jest zarządzany przez Cykl życia pizzerii?** Nie. Czas przygotowania pojedynczej pizzy jest parametrem kontekstu **Kitchen**. Cykl życia pizzerii zarządza wyłącznie stanem `Open` / `Closing` / `Closed`.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym procesie.
