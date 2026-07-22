# Proces: Przyjęcie gości do lokalu (`GuestArrival`)

## Cel procesu

Proces opisuje przyjęcie grupy gości do pizzerii i umieszczenie jej przy stoliku.

## Zakres

* **Początek procesu:** otrzymano zadanie przyjęcia wskazanej `GuestGroup` do lokalu.
* **Koniec procesu:** `GuestGroup` została usadzona przy stoliku (`Occupied`).

## Role zaangażowane

* **GuestGroup** — grupa gości, dla której realizowane jest przyjęcie do lokalu.
* **Host** — rola wbudowana, odpowiedzialna za przyjęcie gości i przydzielenie stolika.

## Warunki początkowe

* Pizzeria musi być w stanie `Open`.
* `GuestGroup` została wskazana przez główny proces jako podmiot do przyjęcia.
* Musi istnieć co najmniej jeden wolny (`Free`) stolik o odpowiedniej pojemności.
* Wybrany stolik musi mieć przypisanego aktywnego kelnera (w stanie `Active`). Stoliki bez przypisanego kelnera lub z kelnerem w stanie `Terminating` / `Terminated` nie są brane pod uwagę.

## Przebieg procesu

```mermaid
flowchart TD

A[Główny proces deleguje przyjęcie GuestGroup]
--> B{Host znajduje wolny (`Free`) stolik?}

B -->|nie| C[Host odmawia przyjęcia]
C --> D[Główny proces kończy obsługę GuestGroup]

B -->|tak| E[Host wybiera stolik uwzględniając liczbę gości i politykę wyboru]
--> F[Host umieszcza GuestGroup przy stoliku]
--> G[Proces przyjęcia zakończony - informacja wraca do głównego procesu]
```

## Szczegóły kroków

### 1. Delegacja przyjęcia z głównego procesu

`GuestGroup` jest bytem posiadającym tożsamość, który może istnieć niezależnie od konkretnej wizyty w pizzerii. Zgłoszenie intencji wejścia do pizzerii należy do głównego procesu obsługi gości (`200_guest_service.md`).

Proces `211_guest_arrival` rozpoczyna się, gdy główny proces deleguje do niego zadanie przyjęcia wskazanej `GuestGroup`. W ramach delegacji przekazywane są informacje niezbędne do obsługi, w szczególności liczba osób w grupie oraz identyfikator `GuestGroup`.

### 2. Wyszukiwanie stolika

`Host` wyszukuje wolny stolik spełniający warunki:
* stolik jest w stanie `Free`,
* liczba miejsc przy stoliku jest wystarczająca dla liczby gości,
* stolik ma przypisanego aktywnego kelnera (w stanie `Active`). Stoliki bez kelnera lub z kelnerem w stanie `Terminating` / `Terminated` nie kwalifikują się do przyjęcia gości.

Jeśli nie ma odpowiedniego stolika, `Host` odmawia przyjęcia. `GuestGroup` opuszcza pizzerię.

### 3. Wybór stolika

Jeśli istnieje więcej niż jeden odpowiedni stolik, `Host` stosuje politykę wyboru stolika. Wybór może być oparty na różnych czynnikach domenowych, takich jak:

* obciążenie kelnerów (najmniej zajęty stolików),
* optymalne wykorzystanie pojemności stolika (np. preferowanie najmniejszego wystarczającego stolika),
* kolejność zajmowania stolików (np. najbliżej wejścia),
* inne reguły biznesowe konfigurowane dla danej pizzerii.

W uproszczonym modelu przyjmujemy domyślną politykę **zbalansowania obciążenia kelnerów** — `Host` wybiera stolik, którego kelner ma najmniej zajętych (`Occupied`) stolików.

Polityka wyboru stolika jest wydzielonym aspektem domeny. W przyszłości można ją rozszerzyć o dodatkowe strategie bez ingerencji w główny przebieg procesu przyjęcia gości.

### 4. Umieszczenie gości przy stoliku

`Host` przypisuje `GuestGroup` do wybranego stolika. Stolik przechodzi w stan `Occupied`. Główny proces obsługi gości zapisuje powiązanie między `GuestGroup` a `Table`.

## Dane wyjściowe procesu

Po zakończeniu procesu przyjęcia:
* `GuestGroup` jest powiązana ze stolikiem,
* stolik jest w stanie `Occupied`,
* główny proces obsługi gości posiada `tableId` dla danej instancji obsługi.

## Granice procesu

Proces przyjęcia gości **nie obejmuje**:
* konfiguracji stolików w pizzerii (definiowanie, edycja, usuwanie) — to proces `252_table_management.md`,
* konfiguracji personelu i przypisań stolików do kelnerów — to proces `254_staff_management.md`.

## Decyzje domenowe zastosowane w tym procesie

* Host przyjmuje wyłącznie na żywo — brak rezerwacji.
* Host odmawia przyjęcia, gdy nie ma wolnego stolika o odpowiedniej pojemności.
* Stolik może istnieć bez przypisanego kelnera, ale nie bierze wtedy udziału w obsłudze gości.
* Kelner obsługuje wyłącznie stoliki przypisane do niego przez Managera.

## Decyzje ostateczne

* ✅ **Czy Host może zaproponować gościom mniejszy stolik, jeśli idealny nie jest dostępny?** Nie. Host przydziela stolik, który spełnia warunek pojemności. Nie ma negocjacji ani propozycji alternatywnych stolików.
* ✅ **Czy grupa gości może odmówić przydzielonego stolika i opuścić pizzerię?** Nie. Jeśli Host znajdzie odpowiedni stolik, grupa zostaje przy nim usadzona. Decyzją procesu jest przyjęcie lub odmowa na wejściu, a nie akceptacja konkretnego stolika przez gości.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym procesie.
