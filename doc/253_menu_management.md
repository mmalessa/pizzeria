# Proces: Zarządzanie menu (`MenuItem` — `Active` / `Disabled`)

## Cel procesu

Proces opisuje zarządzanie menu pizzerii — definiowanie, modyfikowanie i miękkie usuwanie (`Disabled`) pozycji menu (`MenuItem`) przez `Manager`. Menu jest zasobem konfiguracyjnym wykorzystywanym przez gości przy składaniu zamówień oraz przez kuchnię do przygotowywania pizz. Wszystkie operacje na menu są dozwolone wyłącznie, gdy pizzeria jest w stanie `Closed` (`255_pizzeria_lifecycle.md`) — patrz sekcja „Ograniczenia wynikające ze stanu pizzerii".

## Zakres

* **Początek procesu:** `Manager` podejmuje decyzję o zmianie menu, gdy pizzeria jest w stanie `Closed`.
* **Koniec procesu:** pozycja menu została dodana, zmodyfikowana lub usunięta.

## Role zaangażowane

* **Manager** — definiuje, modyfikuje i usuwa pozycje menu.
* **GuestGroup** — przegląda menu i wybiera pozycje do zamówienia (z perspektywy `213_ordering.md`).
* **Kitchen** — korzysta z menu do identyfikacji pizz do przygotowania (z perspektywy `251_kitchen_order_fulfillment.md`).

## Cykl życia pozycji menu

| Stan | Opis |
|------|------|
| `Active` | Pozycja menu jest dostępna do zamówienia przez gości. |
| `Disabled` | Pozycja jest miękko usunięta (soft delete na poziomie aplikacji) — całkowicie niewidoczna i nieużywalna, zarówno dla gości, jak i dla kuchni. Dane pozycji są zachowane i `Manager` może ją w każdej chwili przywrócić do `Active`. |

Cykl `Active → Disabled → Active` może się powtarzać wielokrotnie — przejścia w obie strony są bezpośrednie.

## Przebieg procesu

Wszystkie kroki poniżej wymagają, aby pizzeria była w stanie `Closed`.

```mermaid
flowchart TD

A[Manager tworzy pozycję menu]
--> B[Pozycja w stanie `Active`]
--> C{Manager modyfikuje pozycję}
--> D[Zaktualizowana pozycja menu]
--> E{Manager usuwa pozycję (`MenuItemDisabling`)}
-->|tak| J[Pozycja w stanie `Disabled`]
--> K{Manager przywraca pozycję?}
-->|tak| B
```

## Szczegóły kroków

### 1. Tworzenie pozycji menu

`Manager` dodaje nową pozycję menu (`MenuItem`), wyłącznie gdy pizzeria jest w stanie `Closed`. Każda pozycja zawiera:
* nazwę,
* podstawowe składniki (opis widoczny dla gości),
* sposób przygotowania / recepturę (widoczna dla kuchni),
* cenę.

Nowa pozycja powstaje w stanie `Active` i jest dostępna do zamówienia przez gości od najbliższego otwarcia pizzerii.

### 2. Modyfikacja pozycji menu

`Manager` może modyfikować istniejącą pozycję menu, wyłącznie gdy pizzeria jest w stanie `Closed`. W uproszczonym modelu modyfikacja może dotyczyć:
* nazwy,
* opisu / składników (dla gości),
* sposobu przygotowania / receptury (dla kuchni),
* ceny.

Ponieważ modyfikacja jest możliwa wyłącznie w stanie `Closed` — a w tym stanie nie istnieją żadne otwarte rachunki ani zamówienia (`255_pizzeria_lifecycle.md`) — nowa cena obowiązuje od razu po najbliższym otwarciu pizzerii, jednolicie dla wszystkich gości. Nie może dojść do sytuacji, w której gość widzi jedną cenę na menu, a zostaje obciążony inną w trakcie tej samej sesji obsługi.

### 3. Miękkie usunięcie pozycji menu (`MenuItemDisabling`)

`Manager` może miękko usunąć (soft delete) pozycję menu, wyłącznie gdy pizzeria jest w stanie `Closed`. Pozycja przechodzi bezpośrednio `Active → Disabled`.

Ponieważ w stanie `Closed` nie istnieją żadne aktywne rachunki ani zamówienia (`255_pizzeria_lifecycle.md`), usunięcie nie wymaga żadnego dodatkowego warunku (np. „wszystkie zamówienia z tą pozycją dostarczone") — nie ma już nic do ochrony w trakcie przejścia.

Pozycja w stanie `Disabled`:
* jest całkowicie niewidoczna i nieużywalna — nie jest widoczna dla gości ani dla kuchni,
* nie może występować w nowych zamówieniach,
* zachowuje swoje dane (nazwa, składniki, receptura, cena) — to miękkie usunięcie na poziomie aplikacji, nie trwałe skasowanie rekordu.

`Manager` może, wyłącznie gdy pizzeria jest w stanie `Closed`, przywrócić pozycję z `Disabled` bezpośrednio do `Active`.

## Ograniczenia wynikające ze stanu pizzerii

**Dozwolone wyłącznie w stanie `Closed`:**
* dodawanie nowych pozycji menu,
* modyfikacja nazwy, opisu, receptury i ceny istniejącej pozycji,
* miękkie usunięcie (`Active → Disabled`) i przywrócenie (`Disabled → Active`) pozycji.

**Zablokowane w stanach `Open` i `Closing`:** wszystkie powyższe operacje — próba ich wykonania jest odrzucana.

To świadome uproszczenie modelu: pizzeria nie zmienia menu w trakcie trwania obsługi gości (np. z powodu braku składnika) — taki scenariusz jest poza zakresem tego modelu. Konsekwencją jest też, że menu jest przez cały czas trwania stanu `Open`/`Closing` niezmienne — co eliminuje ryzyko rozjazdu między ceną widoczną gościowi a ceną faktycznie doliczoną do rachunku.

## Dane wyjściowe procesu

W wyniku zarządzania menu:
* menu zawiera pozycje w stanach `Active` i `Disabled`,
* goście widzą wyłącznie pozycje w stanie `Active` oraz wyłącznie nazwę, składniki i cenę,
* kuchnia widzi pełne szczegóły pozycji `Active`: nazwę, składniki, sposób przygotowania / recepturę. Pozycje `Disabled` są niewidoczne dla gości i kuchni. Ponieważ zmiana menu jest możliwa wyłącznie w stanie `Closed` (gdy nie istnieją żadne zamówienia), kuchnia nigdy nie musi mieć dostępu do pozycji „w trakcie wycofywania" — nie istnieje już stan pośredni `Retiring`.

## Granice procesu

Proces zarządzania menu **nie obejmuje**:
* składania zamówień przez gości — to proces `213_ordering.md`,
* przygotowywania pizz w kuchni — to proces `251_kitchen_order_fulfillment.md`,
* zarządzania personelem — to proces `254_staff_management.md`,
* zarządzania cyklem życia pizzerii — to proces `255_pizzeria_lifecycle.md`.

## Decyzje domenowe zastosowane w tym procesie

* Menu jest zarządzane wyłącznie przez `Manager`.
* Każda pozycja menu zawiera nazwę, składniki (dla gości), sposób przygotowania / recepturę (dla kuchni) i cenę.
* Pozycja menu może być w stanie `Active` lub `Disabled`.
* Wszystkie operacje na menu (tworzenie, modyfikacja, usunięcie, przywrócenie) są dozwolone wyłącznie, gdy pizzeria jest w stanie `Closed`.
* Goście widzą wyłącznie pozycje w stanie `Active` oraz wyłącznie nazwę, składniki i cenę.
* Kuchnia widzi pełne szczegóły pozycji `Active`: nazwę, składniki, sposób przygotowania / recepturę.
* Cena jest brana z menu w chwili przyjęcia zamówienia przez kelnera.
* `Disabled` to miękkie usunięcie (soft delete) na poziomie aplikacji — dane pozycji są zachowane i `Manager` może ją przywrócić bezpośrednio do `Active`.

## Decyzje ostateczne

* ✅ **Dlaczego wszystkie zmiany menu wymagają stanu `Closed`?** Eliminuje to ryzyko rozjazdu między ceną widoczną gościowi w momencie przeglądania menu a ceną faktycznie doliczoną do rachunku — cena jest kopiowana z menu dopiero przy przyjęciu zamówienia przez kelnera (`213_ordering.md`), więc każda zmiana ceny w trakcie trwania obsługi mogłaby doprowadzić do rozbieżności między tym, co gość widział, a tym, za co zapłaci. Skoro menu nie może się zmienić podczas `Open`/`Closing`, taki rozjazd nie może wystąpić.
* ✅ **Czy istnieje stan pośredni `Retiring`?** Nie — usunięty. Istniał wyłącznie po to, by chronić już złożone zamówienia podczas wycofywania pozycji w trakcie trwającego serwisu. Skoro wycofanie (`Disabled`) jest teraz możliwe wyłącznie w stanie `Closed`, w którym z definicji nie ma żadnych aktywnych zamówień (`255_pizzeria_lifecycle.md`), nie ma już niczego do ochrony. Cykl życia `MenuItem` uproszczono z `Active → Retiring → Disabled → Active` do `Active ↔ Disabled`.
* ✅ **Czy modyfikacja ceny wpływa na już otwarte rachunki?** Nie. Cena jest pobierana z menu w momencie przyjęcia zamówienia przez kelnera i dopisywana do rachunku. Ponieważ zmiana ceny jest możliwa wyłącznie w stanie `Closed`, kiedy nie istnieją żadne otwarte rachunki, to pytanie ma dziś czysto teoretyczny charakter — do zmiany ceny nigdy nie dochodzi w trakcie istnienia otwartego rachunku.
* ✅ **Czy pozycję menu można usunąć, jeśli jest w aktywnym zamówieniu?** Pytanie nie ma zastosowania — usunięcie pozycji jest możliwe wyłącznie w stanie `Closed`, w którym nie istnieją żadne aktywne zamówienia.
* ✅ **Czy goście widzą pozycje w trakcie wycofywania?** Nie dotyczy — nie istnieje już stan pośredni. Goście widzą wyłącznie pozycje w stanie `Active`.
* ✅ **Czy kuchnia widzi pozycje w trakcie wycofywania?** Nie dotyczy — z tego samego powodu. Kuchnia widzi wyłącznie pozycje `Active`; pozycje `Disabled` są niewidoczne.
* ✅ **Czym jest stan `Disabled` i czym różni się od „usunięcia z menu"?** To miękkie usunięcie (soft delete) na poziomie aplikacji: pozycja jest całkowicie ukryta i nieużywalna, ale jej dane są zachowane, a nie trwale skasowane.
* ✅ **Czy pozycję w stanie `Disabled` można przywrócić?** Tak. `Manager` może, wyłącznie gdy pizzeria jest w stanie `Closed`, przywrócić pozycję z `Disabled` bezpośrednio do `Active`. Cykl `Active ↔ Disabled` może się powtarzać wielokrotnie.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym procesie.
