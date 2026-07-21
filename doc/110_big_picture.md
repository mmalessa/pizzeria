# Event Storming – Big Picture

## Cel

Celem pierwszego etapu Event Stormingu jest odkrycie głównych procesów biznesowych zachodzących w Pizzerii, bez projektowania modelu domenowego, agregatów oraz bounded contextów.

Na tym etapie odpowiadamy wyłącznie na pytanie:

> **Co dzieje się w świecie Pizzerii?**

---

# Wizja produktu

Pizzeria jest interaktywną symulacją restauracji, w której użytkownik wciela się w rolę gości lub obserwuje zachowania automatycznego personelu. Sercem domeny jest pełny cykl życia obsługi grupy gości — od wejścia do lokalu, przez zajęcie stolika, składanie zamówień, ich przygotowanie w kuchni, dostarczenie, konsumpcję, aż po zapłatę i opuszczenie lokalu.

---

# Uczestnicy procesu

Na poziomie Big Picture zidentyfikowano następujących uczestników:

* **GuestGroup** — grupa osób przychodząca do pizzerii, zamawiająca, konsumująca, płacąca
* **Host** — witający gości przy wejściu, przydzielający stolik (dokładnie jeden w pizzerii)
* **Waiter** — kelner obsługujący stolik, przyjmujący zamówienia, przekazujący je do kuchni, dostarczający pizze, przyjmujący płatność
* **Chef** — kucharz pracujący w kuchni, przygotowujący pizze z kolejki zamówień
* **Manager** — konfigurujący pizzerię: stoliki, menu, personel, parametry przygotowania

---

# Odkryte procesy biznesowe

## 1. Przyjęcie gości do lokalu

Proces rozpoczyna się w momencie pojawienia się grupy gości w pizzerii.

```mermaid
flowchart TD

A[Grupa gości pojawiła się w pizzerii]
--> B[Host powitał gości]
--> C[Host wybrał wolny stolik]
--> D[Goście zostali umieszczeni przy stoliku]
--> E[Kelner otworzył rachunek]
```

---

## 2. Zamówienie i przygotowanie pizzy

Goście składają zamówienie składające się z jednej lub wielu pozycji (pizz), które trafia do kuchni i jest realizowane przez kucharzy.

```mermaid
flowchart TD

A[Goście wybrali pizze z menu]
--> B[Kelner przyjął zamówienie]
--> C[Kelner przekazał zamówienie do kuchni]
--> D[Kuchnia przyjęła zamówienie i rozdzieliła je na pizze]
--> E[Kucharze przygotowali wszystkie pizze z zamówienia]
--> F[Kuchnia oznaczyła zamówienie jako gotowe]
--> G[Kelner odebrał gotowe zamówienie z kuchni]
--> H[Kelner dostarczył zamówienie do gości]
```

**Uwaga domenowa:** Zamówienie trafia do kuchni jako całość, ale kuchnia rozbija je na pojedyncze pizze i dystrybuuje je do wolnych kucharzy. Każdy kucharz przygotowuje jedną pizzę naraz. Gdy wszystkie pozycje zamówienia są gotowe, kuchnia oznacza zamówienie jako gotowe do odbioru, a kelner dostarcza je do stolika jako całość.

---

## 3. Rozszerzenie zamówienia

Goście mogą zamawiać kolejne pizze w ramach tego samego rachunku.

```mermaid
flowchart TD

A[Goście zdecydowali się na kolejną pizzę]
--> B[Kelner przyjął nowe zamówienie]
--> C[Kelner przekazał zamówienie do kuchni]
--> D[Kuchnia przygotowała wszystkie pizze z zamówienia]
--> E[Kelner odebrał gotowe zamówienie i dostarczył je do gości]
```

**Uwaga domenowa:** Zamówienie (Order) to pojęcie z domeny produktowej — grupa pizz zamówionych w jednym akcie przez gości. Zamówienie nie zawiera informacji o kosztach. Rachunek (Bill) to pojęcie z domeny finansowej — zbiera pozycje z jednego lub wielu zamówień i oblicza całkowity koszt do zapłaty. Każde zamówienie jest osobnym bytem, ale rachunek agreguje je w jedną transakcję płatniczą.

---

## 4. Płatność i opuszczenie lokalu

Goście proszą o rachunek, dokonują zapłaty i opuszczają pizzerię.

```mermaid
flowchart TD

A[Goście poprosili o rachunek]
--> B[Kelner przedstawił rachunek]
--> C[Goście dokonali zapłaty]
--> D[Kelner zamknął rachunek]
--> E[Goście opuścili stolik]
--> F[Stolik został zwolniony]
```

---

## 5. Zarządzanie stolikami

Manager definiuje dostępne stoliki w pizzerii.

```mermaid
flowchart TD

A[Manager zdefiniował stolik]
--> B[Stolik jest dostępny dla gości]
--> C[Manager zmienił liczbę miejsc przy stoliku]
--> D[Manager usunął stolik]
```

---

## 6. Zarządzanie menu

Manager definiuje pizzę dostępną w ofercie.

```mermaid
flowchart TD

A[Manager dodał pizzę do menu]
--> B[Pizza jest widoczna w menu dla gości]
--> C[Manager zaktualizował składniki lub cenę]
--> D[Manager usunął pizzę z menu]
```

---

## 7. Zarządzanie personelem

Manager zatrudnia personel i przypisuje go do stolików lub kuchni.

```mermaid
flowchart TD

A[Manager zatrudnił kelnera]
--> B[Kelnerowi przypisano stoliki]
--> C[Manager zatrudnił kucharza]
--> D[Liczba kucharzy wpłynęła na czas realizacji zamówień]
--> E[Manager zwolnił pracownika]
```

---

## 8. Konfiguracja parametrów pizzerii

Manager ustawia globalne parametry wpływające na działanie kuchni.

```mermaid
flowchart TD

A[Manager ustalił czas przygotowania pojedynczej pizzy]
--> B[Czas ten wpływa na szacowanie i realizację zamówień]
```

---

# Najważniejsze odkrycie

Podczas analizy zauważono, że:

* zamówienie pojedynczej pizzy,
* zamówienie wielu pizz jako jedna transakcja,
* dokładanie pizzy do istniejącego rachunku,

nie stanowią odrębnych bytów domenowych w sensie modelu danych, ale różnią się **kontekstem procesowym**.

Sercem domeny jest nie sama pizza ani stolik, lecz **cykl życia obsługi grupy gości** — złożony proces koordynujący dostępność zasobów (stoliki, kelnerzy, kucharze), przepływ zamówień oraz zamykanie rachunku.

```mermaid
flowchart TD

A[Goście pojawili się w pizzerii]
--> B[Przydzielono stolik i otwarto rachunek]
--> C[Przyjęto i zrealizowano zamówienia]
--> D[Goście zapłacili i opuścili lokal]
--> E[Zwolniono stolik]
```

Oznacza to, że sercem domeny jest koordynacja **przepływu gości przez pizzerię**, a nie statyczny katalog dań czy lista stolików.

---

# Pytania do dalszej analizy

Podczas kolejnych warsztatów należy odpowiedzieć między innymi na następujące pytania:

* Czy stolik może być rezerwowany z wyprzedzeniem, czy wyłącznie na żywo?
  * **Odpowiedź:** Wyłącznie na żywo. Rezerwacje z wyprzedzeniem wykraczają poza uproszczony model symulacji i wymagałyby osobnej domeny (okna czasowe, no-show, potwierdzenia). Stolik ma tylko stany: wolny / zajęty.
* Czy grupa gości może zmienić stolik w trakcie wizyty?
  * **Odpowiedź:** Nie. Grupa gości pozostaje przy stoliku przydzielonym przez Hosta przez cały cykl wizyty. Funkcjonalność zmiany stolika nie wnosi istotnej wartości do uproszczonego modelu.
* Czy rachunek może być dzielony między osobami w grupie?
  * **Odpowiedź:** Nie. Cała grupa gości płaci jednym rachunkiem. `GuestGroup` nie jest rozbijana na osoby, więc nie ma podstaw do dzielenia płatności.
* Czy kelner może obsługiwać więcej niż jeden stolik jednocześnie?
  * **Odpowiedź:** Tak. Kelner ma przypisaną listę stolików, ale wykonuje jedną czynność na raz. Obsługa wielu stolików jest kluczowa dla realistycznej symulacji obciążenia personelu.
* Czy kucharz może przygotowywać więcej niż jedną pizzę naraz?
  * **Odpowiedź:** Nie. Jeden kucharz przygotowuje jedną pizzę naraz. Dopuszczamy natomiast wielu kucharzy pracujących równolegle, co daje dynamikę symulacji bez komplikowania modelu pojedynczego kucharza.
* Czy zamówienie może być anulowane po przekazaniu do kuchni?
  * **Odpowiedź:** Nie. Zamówienie po przekazaniu do kuchni jest zawsze realizowane do końca. Brak anulowania upraszcza cykl życia zamówienia i eliminuje konieczność obsługi zwrotów i cofania składników.
* Czy menu może zawierać pozycje czasowo niedostępne (brak składników)?
  * **Odpowiedź:** Nie. Menu zawiera wyłącznie dostępne pozycje. Nie modelujemy stanu magazynowego składników ani czasowej niedostępności — to wykracza poza uproszczoną domenę symulacji.
* Czy goście mogą zamawiać pozycje spoza menu (np. modyfikacje)?
  * **Odpowiedź:** Nie. Zamówienia są składane wyłącznie z gotowych pozycji menu. Brak modyfikacji, dodatków i zamówień spoza menu upraszcza model zamówienia.
* Czy system powinien obsługiwać napiwki?
  * **Odpowiedź:** Nie. Napiwki wykraczają poza uproszczony model płatności i wprowadzałyby dodatkową domenę finansową (podział, wypłata). Płatność obejmuje wyłącznie kwotę rachunku.

---

# Wniosek

Najważniejszym odkryciem pierwszego etapu Event Stormingu jest to, że **Pizzeria nie jest systemem rezerwacyjnym ani systemem POS**.

Pizzeria jest symulacją koordynacji procesów domenowych, w której centralnym problemem jest zarządzanie równoczesnym przepływem wielu grup gości przez ograniczone zasoby (stoliki, kelnerzy, kucharze) oraz śledzenie ich cyklu życia w czasie rzeczywistym.

Centralnym procesem domenowym jest:

```mermaid
flowchart TD

A[Grupa gości pojawia się w pizzerii]
--> B["Przypisywanie zasobów: stolik i kelner"]
--> C[Przyjmowanie i realizacja zamówień]
--> D[Dostarczanie i konsumpcja]
--> E[Płatność i zamykanie rachunku]
--> F[Zwalnianie zasobów]
```

To właśnie wokół tego procesu powinien zostać zbudowany model domenowy w kolejnych etapach projektowania.
