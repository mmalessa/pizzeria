# Encje (Entities)

## Cel dokumentu

Dokument opisuje szczegółowo każdą encję domenową zidentyfikowaną w `320_domain_model.md` i umiejscowioną w agregatach opisanych w `321_aggregates.md` — jej atrybuty (z typami), reguły tworzenia, pełny cykl życia (przejścia stanów wraz z warunkami i inicjatorem), niezmienniki właściwe tej encji oraz zachowania (operacje) jakie encja udostępnia.

## Granica dokumentu

`321_aggregates.md` opisywał **granice** agregatów i niezmienniki obejmujące więcej niż jedną encję. `322_entities.md` opisuje **wnętrze** każdej encji z osobna — jej własny stan i przejścia. Obiekty wartości (`Money`, `OrderLine`, `BillLine`, `TableCapacity`, `PreparationTime`) są opisane szczegółowo w `323_value_objects.md`; tutaj pojawiają się wyłącznie jako typy atrybutów. Usługi domenowe (np. polityka wyboru stolika przez `Host`, polityka szacowania czasu w `Kitchen`) są przedmiotem `324_domain_services.md`.

## Zasady wspólne dla encji

* Każda encja ma tożsamość (`xxxId`) i jest porównywana przez tożsamość, nie przez wartość atrybutów.
* Encje wewnętrzne agregatu (`PizzaTask` wewnątrz `Kitchen`) nie mają własnego repozytorium — są tworzone, modyfikowane i usuwane wyłącznie przez swój aggregate root.
* Encje będące jednocześnie aggregate rootami (`Bill`, `Order`, `Table`, `MenuItem`, `Waiter`, `Chef`, `Pizzeria`, `Kitchen`) mają własne repozytorium.
* `GuestGroup` jest encją z tożsamością, ale — zgodnie z `320_domain_model.md` i `321_aggregates.md` — nie jest aggregate rootem z własnymi niezmiennikami transakcyjnymi; jest bytem referencyjnym koordynowanym przez główny proces obsługi gości.

---

## `GuestGroup`

**Tożsamość:** `guestGroupId`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `guestGroupId` | `GuestGroupId` | Identyfikator. |
| `name` | `string` | Nazwa wyświetlana w UI, unikalna — służy wyłącznie do identyfikacji grupy przez użytkownika symulacji. |
| `size` | `int` | Liczba osób w grupie, musi być > 0. |

**Reguły tworzenia:** `GuestGroup` jest definiowana przez użytkownika symulacji przed wejściem do pizzerii, z podaniem co najmniej `size`. Pizzeria nie waliduje procesu jej powstania poza `size > 0`.

**Cykl życia:** brak. `GuestGroup` nie przechodzi między stanami — pozostaje stałym odniesieniem przez cały czas trwania obsługi (`111_domain_decisions.md`).

**Niezmienniki:** `size > 0`.

**Zachowania:** brak zachowań domenowych własnych — `GuestGroup` jest bytem biernym, referencjonowanym przez `Table`, `Bill` i `Order` za pośrednictwem głównego procesu obsługi gości, nie przez bezpośrednie pola tych agregatów.

---

## `Table`

**Tożsamość:** `tableId`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `tableId` | `TableId` | Identyfikator. |
| `name` | `string` | Unikalna nazwa stolika, wyłącznie do identyfikacji w UI (analogicznie do `GuestGroup.name`). |
| `capacity` | `TableCapacity` | Liczba miejsc. |
| `waiterId` | `WaiterId?` | Opcjonalna referencja do `Waiter`. |
| `status` | `TableStatus` | `Free` / `Occupied`. |

**Reguły tworzenia:** tworzony przez `Manager` z `capacity > 0` i unikalną `name`; `status = Free`; `waiterId` opcjonalny (może być pusty).

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Free` | `Occupied` | stolik ma wystarczającą pojemność i przypisanego aktywnego (`Active`) kelnera | `Host` (`211_guest_arrival.md`) |
| `Occupied` | `Free` | rachunek powiązany z wizytą jest `Closed`, goście opuścili lokal | główny proces obsługi gości, operacja `TableRelease` (`252_table_management.md`) |

**Niezmienniki:**
* `waiterId` może się zmienić wyłącznie, gdy `status = Free`,
* `capacity` może się zmienić wyłącznie, gdy `status = Free`,
* nie można usunąć encji, gdy `status = Occupied`,
* `name` musi być unikalna wśród wszystkich `Table` — sprawdzane przez usługę domenową przy tworzeniu lub zmianie nazwy (wymaga odpytania innych instancji `Table`, poza granicą pojedynczego agregatu).

**Zachowania:** `assignWaiter(waiterId)` (guard: `Free`), `changeCapacity(newCapacity)` (guard: `Free`), `rename(newName)` (guard: unikalność wśród wszystkich `Table`), `occupy()` (guard: `Free`, wystarczająca pojemność, aktywny kelner — sprawdzane przez `Host` przed wywołaniem), `release()` (guard: `Occupied`).

**Uwaga:** `Table` nie przechowuje `guestGroupId` — powiązanie z konkretną `GuestGroup` podczas wizyty utrzymuje główny proces obsługi gości, zgodnie z zasadą modelowania z `320_domain_model.md`.

---

## `Bill`

**Tożsamość:** `billId`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `billId` | `BillId` | Identyfikator. |
| `openedAt` | `timestamp` | Czas otwarcia. |
| `closedAt` | `timestamp?` | Czas zamknięcia, ustawiany wyłącznie przy przejściu do `Closed`. |
| `lines` | `BillLine[]` | Pozycje z cenami z momentu przyjęcia zamówienia. |
| `totalAmount` | `Money` | Suma `totalPrice` wszystkich `lines`. |
| `status` | `BillStatus` | `Open` / `Closed`. |

**Reguły tworzenia:** tworzony przez `Waiter` po usadzeniu `GuestGroup` (`BillOpening`); `openedAt = teraz`, `lines = []`, `totalAmount = 0`, `status = Open`.

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Open` | `Closed` | zapłacona kwota równa `totalAmount`, lub `totalAmount = 0` (płatność pomijana) | `Waiter`, na polecenie głównego procesu obsługi gości |

**Niezmienniki:**
* `lines` mogą być dopisywane wyłącznie, gdy `status = Open`,
* `totalAmount` zawsze równa się sumie `totalPrice` wszystkich `lines`,
* po `Closed` agregat jest niemodyfikowalny.

**Zachowania:** `addLine(menuItemId, quantity, unitPrice)` (guard: `Open`; dopisuje `BillLine`, przelicza `totalAmount`), `close(paymentAmount)` (guard: `Open` i `paymentAmount = totalAmount`).

**Uwaga o „anulowaniu" rachunku:** `212_bill_management.md` opisuje możliwość „anulowania" rachunku otwartego bez zamówień. Mechanicznie jest to ten sam przypadek co zamknięcie z `totalAmount = 0` — rachunek bez pozycji ma zawsze `totalAmount = 0`, więc `close()` pomija krok płatności i zamyka rachunek. Nie jest to osobny stan encji, tylko szczególny przypadek zwykłego zamknięcia.

---

## `Order`

**Tożsamość:** `orderId`, generowany automatycznie w momencie przyjęcia przez `Waiter`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `orderId` | `OrderId` | Identyfikator, generowany automatycznie. |
| `lines` | `OrderLine[]` | Pozycja menu + ilość, co najmniej jedna. |
| `status` | `OrderStatus` | `Accepted` / `Submitted` / `InPreparation` / `ReadyForDelivery` / `Delivered`. |

**Reguły tworzenia:** tworzony przez `Waiter` w momencie przyjęcia wyboru gości; wymaga co najmniej jednej `OrderLine`, z których każda odnosi się do `MenuItem` w stanie `Active` w chwili przyjęcia. `status = Accepted`.

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Accepted` | `Submitted` | brak dodatkowego warunku | `Waiter` |
| `Submitted` | `InPreparation` | `Kitchen` przyjęła zamówienie | zdarzenie domenowe z `Kitchen` |
| `InPreparation` | `ReadyForDelivery` | wszystkie `PizzaTask` zamówienia są `Ready` | zdarzenie domenowe z `Kitchen` |
| `ReadyForDelivery` | `Delivered` | kelner ma dostępność w kolejce zadań | `Waiter` |

Cykl jest ściśle liniowy i jednokierunkowy — brak pomijania stanów, cofania i anulowania w dowolnym momencie (`111_domain_decisions.md`, OQ-001).

**Niezmienniki:**
* nie może powstać bez co najmniej jednej `OrderLine`,
* nie przechowuje `tableId`, `billId` ani cen,
* po `Delivered` agregat jest niemodyfikowalny.

**Zachowania:** `submit()` (guard: `Accepted`), `startPreparation()` (guard: `Submitted`; wywoływane w reakcji na zdarzenie z `Kitchen`), `markReadyForDelivery()` (guard: `InPreparation`; wywoływane w reakcji na zdarzenie z `Kitchen`), `deliver()` (guard: `ReadyForDelivery`).

---

## `MenuItem`

**Tożsamość:** `menuItemId`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `menuItemId` | `MenuItemId` | Identyfikator. |
| `name` | `string` | Nazwa. |
| `ingredients` | `string` | Składniki widoczne dla gości. |
| `recipe` | `string` | Sposób przygotowania / receptura widoczna dla kuchni. |
| `price` | `Money` | Cena. |
| `status` | `MenuItemStatus` | `Active` / `Retiring` / `Disabled`. |

**Reguły tworzenia:** tworzony przez `Manager`; `status = Active`.

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Active` | `Retiring` | brak warunku, dowolny moment | `Manager` (`MenuItemRetirement`) |
| `Retiring` | `Active` | brak warunku, dowolny moment | `Manager` (cofnięcie wycofania) |
| `Retiring` | `Disabled` | wszystkie zamówienia zawierające tę pozycję są `Delivered` | `Manager` (miękkie usunięcie) |
| `Disabled` | `Active` | brak warunku, dowolny moment | `Manager` (przywrócenie) |

Cykl `Active → Retiring → Disabled → Active` może się powtarzać wielokrotnie. `Disabled` to miękkie usunięcie (soft delete) na poziomie aplikacji — pozycja jest całkowicie niewidoczna i nieużywalna dla gości i kuchni, ale jej dane są zachowane, nie trwale skasowane. Bezpośrednie przejścia `Active → Disabled` i `Disabled → Retiring` nie są dozwolone.

**Niezmienniki:**
* zmiana `price` nie wpływa na `BillLine` już zapisane w istniejących rachunkach (te są kopiami wykonanymi w momencie przyjęcia zamówienia),
* przejście do `Disabled` wymaga, aby wszystkie zamówienia zawierające tę pozycję były `Delivered` (kuchnia musi mieć dostęp do receptury, dopóki trwa ich realizacja).

**Zachowania:** `retire()` (`Active → Retiring`), `reactivate()` (`Retiring → Active` lub `Disabled → Active`), `disable()` (`Retiring → Disabled`, guard: wszystkie zamówienia z pozycją `Delivered`), `changePrice(newPrice)`, `changeDescription(name, ingredients, recipe)`.

---

## `Waiter`

**Tożsamość:** `waiterId`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `waiterId` | `WaiterId` | Identyfikator. |
| `name` | `string` | Nazwa / identyfikator wyświetlany. |
| `status` | `WaiterStatus` | `Active` / `Terminating` / `Terminated`. |

`Waiter` nie przechowuje listy przypisanych stolików — patrz `321_aggregates.md` (`Table.waiterId` jest jedynym źródłem prawdy; stoliki kelnera są zapytaniem `findByWaiterId` do repozytorium `Table`).

**Reguły tworzenia:** zatrudniany przez `Manager`; `status = Active`; bez przypisanych stolików.

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Active` | `Terminating` | kelner nie jest ostatnim aktywnym kelnerem podczas pracy pizzerii (`Open`/`Closing`) | `Manager` |
| `Terminating` | `Terminated` | brak przypisanego zajętego (`Occupied`) stolika (zapytanie do `Table`) | automatycznie lub ręcznie |
| `Terminated` | `Active` | brak warunku | `Manager` (ponowne zatrudnienie) |

Bezpośrednie przejście `Terminating → Active` nie jest dozwolone — powrót do `Active` prowadzi wyłącznie przez `Terminated`. Ponownie zatrudniony kelner zaczyna bez przypisanych stolików, tak jak przy pierwszym zatrudnieniu.

**Niezmienniki:** brak niezmienników wewnątrz samej encji poza kierunkiem przejść — warunki dotyczące ostatniego aktywnego kelnera i zajętych stolików są sprawdzane międzyagregatowo (`321_aggregates.md`).

**Zachowania:** `startTerminating()`, `completeTermination()`, `rehire()`.

**Uwaga:** `Table.waiterId` może wskazywać na kelnera, który nie jest już `Active` (np. `Terminated`) — taki stolik istnieje w konfiguracji, ale `Host` pomija go przy przydzielaniu gości, dopóki `Manager` nie przypisze innego aktywnego kelnera.

---

## `Chef`

**Tożsamość:** `chefId`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `chefId` | `ChefId` | Identyfikator. |
| `name` | `string` | Nazwa / identyfikator wyświetlany. |
| `status` | `ChefStatus` | `Active` / `Terminating` / `Terminated`. |

**Reguły tworzenia:** zatrudniany przez `Manager`; `status = Active`; automatycznie dostępny w puli kucharzy kuchni, bez dodatkowego przypisania.

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Active` | `Terminating` | kucharz nie jest ostatnim aktywnym kucharzem podczas pracy pizzerii (`Open`/`Closing`) | `Manager` |
| `Terminating` | `Terminated` | brak `PizzaTask` w stanie `InPreparation` przypisanego temu kucharzowi (zapytanie do `Kitchen`) | automatycznie lub ręcznie |
| `Terminated` | `Active` | brak warunku | `Manager` (ponowne zatrudnienie) |

Bezpośrednie przejście `Terminating → Active` nie jest dozwolone. Ponownie zatrudniony kucharz jest natychmiast dostępny w puli kuchni.

**Niezmienniki:** brak niezmienników wewnątrz samej encji poza kierunkiem przejść.

**Zachowania:** `startTerminating()`, `completeTermination()`, `rehire()`.

---

## `Pizzeria`

**Tożsamość:** singleton w ramach systemu.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `status` | `PizzeriaStatus` | `Open` / `Closing` / `Closed`. |

**Reguły tworzenia:** jedna instancja na cały system; początkowy stan `Closed`.

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Closed` | `Open` | ≥1 aktywny kelner, ≥1 aktywny kucharz, ≥1 stolik w konfiguracji | `Manager` |
| `Open` | `Closing` | brak warunku | `Manager` |
| `Closing` | `Closed` | wszystkie `Bill` są `Closed`, wszystkie `Table` są `Free`, brak aktywnych `Order` | automatycznie |

**Niezmienniki:** brak niezmienników wewnątrz samej encji — warunki otwarcia i automatycznego zamknięcia są międzyagregatowe (`321_aggregates.md`).

**Zachowania:** `open()` (guard sprawdzany przez usługę domenową), `initiateClosing()` (guard: `Open`). Przejście `Closing → Closed` nie jest wywoływane bezpośrednio — jest efektem spójności ostatecznej reagującej na zdarzenia z `Bill`, `Table` i `Order`.

---

## `Kitchen`

**Tożsamość:** singleton w ramach systemu (jedna instancja koordynująca produkcję, analogicznie do `Pizzeria`).

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `pizzaTasks` | `PizzaTask[]` | Kolejka produkcyjna (encje wewnętrzne). |
| `pizzaPreparationTime` | `PreparationTime` | Parametr globalny, konfigurowany przez `Manager`. |

**Reguły tworzenia:** jedna instancja na cały system; `pizzaTasks = []` na starcie.

**Cykl życia:** brak własnego cyklu życia — `Kitchen` istnieje przez cały czas działania systemu; jej stan to zawartość kolejki `pizzaTasks`.

**Niezmienniki:**
* każdy `PizzaTask` należy do dokładnie jednego `orderId`,
* jeden `chefId` ma co najwyżej jeden `PizzaTask` w stanie `InPreparation` jednocześnie (OQ-004, `111_domain_decisions.md`).

**Zachowania:** `acceptOrder(orderId, lines)` (rozbija zamówienie na `PizzaTask` w stanie `Pending`), `distributeToChefs()` (przypisuje `Pending` zadania do dostępnych aktywnych kucharzy), `reportPizzaReady(pizzaTaskId)` (deleguje do wewnętrznego `PizzaTask`), sprawdzenie „czy wszystkie zadania zamówienia są `Ready`" → publikacja zdarzenia dla `Order` (`325_integration_events.md`).

**Uwaga terminologiczna:** `320_domain_model.md` wspomina osobno „referencje do aktywnych kucharzy (`chefId`)" jako atrybut `Kitchen`. W praktyce są to te same identyfikatory, co `PizzaTask.chefId` — `Kitchen` nie utrzymuje osobnej listy przypisań poza polami swoich `PizzaTask`.

### `PizzaTask` (encja wewnętrzna `Kitchen`)

**Tożsamość:** `pizzaTaskId`, unikalny w obrębie `Kitchen`.

**Atrybuty:**

| Atrybut | Typ | Opis |
|---------|-----|------|
| `pizzaTaskId` | `PizzaTaskId` | Identyfikator. |
| `menuItemId` | `MenuItemId` | Pozycja menu do przygotowania. |
| `orderId` | `OrderId` | Zamówienie, do którego należy pizza. |
| `chefId` | `ChefId?` | Opcjonalnie, kucharz przypisany do zadania. |
| `status` | `PizzaTaskStatus` | `Pending` / `InPreparation` / `Ready`. |

**Reguły tworzenia:** tworzony przez `Kitchen` przy rozbiciu zamówienia na pojedyncze pizze, jedna instancja na sztukę z `OrderLine.quantity`; `status = Pending`, `chefId` puste.

**Cykl życia:**

| Z | Do | Warunek | Kto inicjuje |
|---|----|---------|--------------|
| `Pending` | `InPreparation` | przypisany kucharz jest `Active` i nie ma innego zadania `InPreparation` | `Kitchen` (dystrybucja) |
| `InPreparation` | `Ready` | brak warunku | `Chef` (zgłoszenie gotowości) |

Brak dalszych przejść — `Ready` jest stanem końcowym zadania; usunięcie zadania po dostarczeniu zamówienia jest decyzją techniczną poza zakresem modelu domenowego (analogicznie do usuwania `MenuItem`).

**Niezmienniki:** brak niezmienników poza kierunkiem przejść — reszta jest wymuszana na poziomie `Kitchen` (patrz wyżej).

**Zachowania:** `assignChef(chefId)` (`Pending → InPreparation`), `reportReady()` (`InPreparation → Ready`).

---

## Decyzje ostateczne

* ✅ **Czy „anulowanie" `Bill` bez zamówień to osobny stan?** Nie. To ten sam mechanizm co zamknięcie z `totalAmount = 0` — `Bill` ma wyłącznie stany `Open`/`Closed`.
* ✅ **Czy usunięcie `MenuItem` z aktywnego menu to stan domenowy?** Tak — `Disabled`. To formalny stan reprezentujący miękkie usunięcie (soft delete) na poziomie aplikacji: dane pozycji są zachowane, a `Manager` może ją przywrócić bezpośrednio do `Active`. Zastępuje wcześniejszy opis „operacji technicznej poza modelem".
* ✅ **Czy usunięcie `PizzaTask` po dostarczeniu zamówienia to stan domenowy?** Nie. To wciąż operacja techniczna/repozytoryjna — `PizzaTask` nie ma odpowiednika `Disabled`, ponieważ nie jest samodzielnym agregatem z własnym cyklem odtwarzania (żyje wewnątrz `Kitchen` i znika wraz z realizacją zamówienia).
* ✅ **Czy `Kitchen` przechowuje osobną listę `chefId` poza `PizzaTask`?** Nie. To ten sam zestaw identyfikatorów co `PizzaTask.chefId` — doprecyzowano względem `320_domain_model.md`.
* ✅ **Czy `Table` ma nazwę widoczną w UI?** Tak, atrybut `name`, unikalny wśród wszystkich `Table`, wyłącznie do identyfikacji w interfejsie — analogicznie do `GuestGroup.name`.

## Pytania do dalszej analizy

* Brak otwartych pytań w tym dokumencie.
