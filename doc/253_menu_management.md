# Proces: Zarządzanie menu (`MenuItem` — `Active` / `Retiring`)

## Cel procesu

Proces opisuje zarządzanie menu pizzerii — definiowanie, modyfikowanie i usuwanie pozycji menu (`MenuItem`) przez `Manager`. Menu jest zasobem konfiguracyjnym wykorzystywanym przez gości przy składaniu zamówień oraz przez kuchnię do przygotowywania pizz.

## Zakres

* **Początek procesu:** `Manager` podejmuje decyzję o zmianie menu.
* **Koniec procesu:** pozycja menu została dodana, zmodyfikowana lub usunięta zgodnie z ograniczeniami procesu.

## Role zaangażowane

* **Manager** — definiuje, modyfikuje i usuwa pozycje menu.
* **GuestGroup** — przegląda menu i wybiera pozycje do zamówienia (z perspektywy `213_ordering.md`).
* **Kitchen** — korzysta z menu do identyfikacji pizz do przygotowania (z perspektywy `251_kitchen_order_fulfillment.md`).

## Cykl życia pozycji menu

| Stan | Opis |
|------|------|
| `Active` | Pozycja menu jest dostępna do zamówienia przez gości. |
| `Retiring` | Pozycja menu została wycofana z oferty dla nowych zamówień, ale nadal jest dostępna dla aktualnie realizowanych zamówień. |

Z punktu widzenia procesu domenowego pozycja menu może być dostępna do zamówienia, wycofywana lub usunięta z aktywnego menu. Ewentualne przechowywanie całkowicie usuniętych pozycji dla celów raportowania, historii zamówień i rachunków jest decyzją techniczną leżącą poza tym procesem.

## Przebieg procesu

```mermaid
flowchart TD

A[Manager tworzy pozycję menu]
--> B[Pozycja w stanie `Active`]
--> C{Manager modyfikuje pozycję}
--> D[Zaktualizowana pozycja menu]
--> E{Manager wycofuje pozycję (`MenuItemRetirement`)}
-->|tak| F[Sprawdzenie ograniczeń]
--> G[Pozycja w stanie `Retiring`]
--> H{Wszystkie zamówienia z pozycją dostarczone?}
-->|tak| I[Pozycja usunięta z aktywnego menu]
```

## Szczegóły kroków

### 1. Tworzenie pozycji menu

`Manager` dodaje nową pozycję menu (`MenuItem`). Każda pozycja zawiera:
* nazwę,
* podstawowe składniki (opis widoczny dla gości),
* sposób przygotowania / recepturę (widoczna dla kuchni),
* cenę.

Nowa pozycja powstaje w stanie `Active` i jest od razu dostępna do zamówienia przez gości.

### 2. Modyfikacja pozycji menu

`Manager` może modyfikować istniejącą pozycję menu. W uproszczonym modelu modyfikacja może dotyczyć:
* nazwy,
* opisu / składników (dla gości),
* sposobu przygotowania / receptury (dla kuchni),
* ceny.

Modyfikacja ceny wpływa na nowe zamówienia. Pozycje już dopisane do otwartych lub zamkniętych rachunków zachowują cenę z momentu przyjęcia zamówienia.

### 3. Wycofanie pozycji menu (`MenuItemRetirement`)

`Manager` może wycofać pozycję menu z oferty w dowolnym momencie. Pozycja przechodzi w stan `Retiring`.

Pozycja w stanie `Retiring`:
* nie jest widoczna dla gości jako dostępna do zamówienia,
* nadal może występować w otwartych (`Open`) rachunkach i aktualnie realizowanych zamówieniach,
* jest dostępna dla kuchni do przygotowania pizz w ramach istniejących zamówień.

Gdy wszystkie zamówienia zawierające daną pozycję zostaną dostarczone (`Delivered`), pozycja może zostać usunięta z aktywnego menu. Ewentualne przechowywanie usuniętej pozycji dla celów historycznych i raportowania jest decyzją techniczną leżącą poza tym procesem.

## Zarządzanie menu na żywo

`Manager` może modyfikować menu na żywo, pod warunkiem że nie narusza to aktualnie trwających procesów:

**Dozwolone na żywo:**
* dodawanie nowych pozycji menu,
* modyfikacja nazwy i opisu pozycji menu,
* modyfikacja ceny pozycji menu (nowe zamówienia będą miały nową cenę),
* rozpoczęcie `MenuItemRetirement` w dowolnym momencie.

**Zablokowane lub ograniczone:**
* całkowite usunięcie pozycji menu z aktywnego menu, dopóki istnieją niedostarczone zamówienia ją zawierające.

## Dane wyjściowe procesu

W wyniku zarządzania menu:
* menu zawiera pozycje w stanach `Active` i `Retiring`,
* goście widzą wyłącznie pozycje w stanie `Active` oraz wyłącznie nazwę, składniki i cenę,
* kuchnia widzi pełne szczegóły pozycji: nazwę, składniki, sposób przygotowania / recepturę. Widzi pozycje `Active` oraz te `Retiring`, które nadal znajdują się w realizacji zamówień.

## Granice procesu

Proces zarządzania menu **nie obejmuje**:
* składania zamówień przez gości — to proces `213_ordering.md`,
* przygotowywania pizz w kuchni — to proces `251_kitchen_order_fulfillment.md`,
* zarządzania personelem — to proces `254_staff_management.md`,
* zarządzania cyklem życia pizzerii — to proces `255_pizzeria_lifecycle.md`.

## Decyzje domenowe zastosowane w tym procesie

* Menu jest zarządzane wyłącznie przez `Manager`.
* Każda pozycja menu zawiera nazwę, składniki i cenę.
* Menu jest zarządzane wyłącznie przez `Manager`.
* Każda pozycja menu zawiera nazwę, składniki (dla gości), sposób przygotowania / recepturę (dla kuchni) i cenę.
* Pozycja menu może być w stanie `Active` lub `Retiring`.
* Goście widzą wyłącznie pozycje w stanie `Active` oraz wyłącznie nazwę, składniki i cenę.
* Kuchnia widzi pełne szczegóły pozycji: nazwę, składniki, sposób przygotowania / recepturę.
* Cena jest brana z menu w chwili przyjęcia zamówienia przez kelnera.
* Rozpoczęcie `MenuItemRetirement` wymaga, aby pozycja nie znajdowała się w aktywnie realizowanym zamówieniu (może być w stanie `Submitted` lub nowszym, ale `MenuItemRetirement` zablokuje nowe zamówienia).
* Całkowite usunięcie pozycji z aktywnego menu możliwe jest dopiero po dostarczeniu (`Delivered`) wszystkich zamówień ją zawierających.

## Decyzje ostateczne

* ✅ **Czy pozycja menu może mieć status pośredni podczas wycofywania?** Tak. Pozycja może przejść w stan `Retiring`, w którym nie jest już dostępna dla nowych zamówień, ale nadal może występować w aktualnie realizowanych zamówieniach. Po dostarczeniu (`Delivered`) wszystkich zamówień z daną pozycją można ją usunąć z aktywnego menu.
* ✅ **Czy modyfikacja ceny wpływa na już otwarte rachunki?** Nie. Cena jest pobierana z menu w momencie przyjęcia zamówienia przez kelnera i dopisywana do rachunku. Zmiana ceny w menu nie wpływa na pozycje już znajdujące się w otwartych (`Open`) lub zamkniętych (`Closed`) rachunkach.
* ✅ **Czy pozycję menu można wycofać, jeśli jest w aktywnym zamówieniu?** Tak. Rozpoczęcie `MenuItemRetirement` może nastąpić w dowolnej chwili. Pozycja przechodzi w stan `Retiring` i jest nadal realizowana w ramach istniejących zamówień. Całkowite usunięcie pozycji z aktywnego menu możliwe jest dopiero po dostarczeniu (`Delivered`) wszystkich zamówień ją zawierających.
* ✅ **Czy goście widzą wycofywane pozycje menu?** Nie. Goście widzą wyłącznie pozycje w stanie `Active`.
* ✅ **Czy kuchnia widzi wycofywane pozycje menu?** Kuchnia widzi pozycje potrzebne do realizacji bieżących zamówień. W praktyce oznacza to pozycje w stanie `Active` oraz te w stanie `Retiring`, które nadal znajdują się w realizacji.
* ✅ **Czy pozycję w stanie `Retiring` można przywrócić do `Active`?** Tak. `Manager` może cofnąć wycofanie pozycji menu — pozycja wraca do stanu `Active` i jest ponownie dostępna do zamówienia przez gości. Cykl `Active → Retiring → Active` jest dopuszczalny wielokrotnie.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym procesie.
