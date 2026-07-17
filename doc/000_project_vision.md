# Project Vision

## Cel projektu

Pizzeria to interaktywna symulacja restauracji serwującej pizze, zaprojektowana jako piaskownica architektoniczna demonstrująca projektowanie oraz implementację systemu zgodnie z zasadami Domain-Driven Design.

System umożliwia użytkownikowi wcielenie się w role uczestników procesu obsługi restauracji — gości, kelnera, kucharza, hosta i menedżera — oraz obserwację zachowań systemu z perspektywy całej pizzerii.

---

# Problem

Stworzyć przykład systemu, który:

- przeprowadza użytkownika przez pełny proces odkrywania domeny,
- implementuje rzeczywiste wzorce takie jak Event Sourcing, Saga, Process Manager,
- demonstruje modularny monolit złożony z niezależnych mikrousług,
- pozwala na interaktywną symulację zachowań domenowych przez Web GUI.

---

# Rozwiązanie

Pizzeria modeluje uproszczony, ale kompletny cykl życia obsługi grupy gości w restauracji — od wejścia, przez zamówienie, przygotowanie, konsumpcję, aż po płatność i opuszczenie lokalu.

System automatyzuje zachowania personelu (host, kelner, kucharz) przy jednoczesnym oddaniu kontroli użytkownikowi nad decyzjami gości i parametrami pizzerii.

**Pizzeria nie jest systemem POS ani systemem zarządzania restauracją w klasycznym sensie. Pizzeria jest symulacją procesów domenowych, w której użytkownik może eksplorować konsekwencje decyzji architektonicznych w czasie rzeczywistym.**

---

# Użytkownicy

Platforma obsługuje jednego użytkownika, który może przyjmować różne perspektywy.

## Użytkownik (człowiek)

Jedyny aktor ludzki w systemie. Przez Web GUI może:

- wcielać się w rolę **Gościa** (wiele niezależnych grup jednocześnie) — decyduje o liczbie osób w każdej grupie, wybiera stolik (lub akceptuje wskazany przez Hosta), składa zamówienia, prosi o rachunek, płaci; może zarządzać wieloma grupami równolegle jako niezależne symulacje,
- wcielać się w rolę **Managera** — konfiguruje pizzerię: zarządza stolikami, menu, personelem (kelnerzy, kucharze) oraz parametrami takimi jak czas przygotowania pizzy,
- obserwować system z perspektywy **Sali Jadalnej** — widok stolików, zajętości, przypisanych kelnerów,
- obserwować system z perspektywy **Kuchni** — kolejka zamówień, zajętość kucharzy, postęp przygotowania.

Zachowania personelu (Host, Kelner, Kucharz) są w pełni automatyczne i nie wymagają interakcji użytkownika.

---

# Cele produktu

Projekt Pizzeria powinien umożliwiać:

- interaktywną symulację cyklu obsługi gości w restauracji,
- demonstrację projektowania architektury zgodnie z DDD (Strategic i Tactical Design),
- prezentację wzorców: Event Sourcing, Saga, Process Manager, CQRS, CQS,
- implementację modularnego monolitu składającego się z niezależnych mikrousług,
- eksplorację różnych serwerów HTTP i technologii w ramach jednego systemu,
- pokazanie komunikacji synchronicznej i asynchronicznej między usługami,
- walidację komunikatów zgodnie ze schematami (JSON-Schema / AVRO / Protobuf),
- uruchomienie w środowisku skonteneryzowanym (Docker) z perspektywą migracji do Kubernetes,
- pokrycie kodu testami jednostkowymi i integracyjnymi (bez TDD).

---

# Założenia projektowe

Podczas projektowania systemu przyjęto następujące założenia:

- model domenowy powinien być otwarty na rozwój (np. magazyn produktów, kasa w przyszłości),
- system jest wyłącznie demonstracyjny — brak uwierzytelniania i autoryzacji,
- mikrousługi komunikują się bezpośrednio (synchronicznie lub asynchronicznie), bez pośrednictwa API Gateway,
- publiczne API Gateway służy wyłącznie do komunikacji z frontendem lub innymi zewnętrznymi klientami,
- każda mikrousługa może mieć własną strukturę katalogów i technologie,
- CRUD jest dopuszczalny tam, gdzie nie ma istotnej logiki biznesowej,
- złożona logika biznesowa powinna być realizowana przez dedykowane mechanizmy (agregaty, process manager, saga),
- zdarzenia integracyjne są preferowanym sposobem komunikacji między kontekstami; zdarzenia domenowe są narzędziem modelowania, nie integracji.

---

# Wartości projektu

## Intuicyjność

Kod i architektura powinny być zrozumiałe bez konieczności studiowania dokumentacji technicznej. Nazewnictwo, struktura i przepływy danych mają odzwierciedlać język domenowy.

## Klarowność procesu

Każdy etap obsługi gości (przywitanie, zamówienie, przygotowanie, dostawa, płatność) ma wyraźnie zdefiniowane granice odpowiedzialności i stanu.

## Modularność

Poszczególne elementy systemu (mikrousługi) działają niezależnie. Zmiana w jednym kontekście nie powinna propagować się w sposób niekontrolowany do innych.

## Skalowalność (horyzontalna)

System jest budowany z myślą o późniejszym rozdzieleniu na oddzielne jednostki wdrożeniowe w Kubernetes, mimo że w fazie budowy działa jako modularny monolit.

---

# Długoterminowa wizja

Docelowo Pizzeria powinna stać się rozbudowanym portfolium architektonicznym, wykorzystywanym do demonstracji:

- różnych podejść do modelowania domenowego,
- ewolucji systemu monolitycznego w kierunku rozproszonego,
- różnych strategii komunikacji między usługami (sync/async, messaging, event-driven),
- różnych baz danych i mechanizmów persistencji per mikrousługa.

W kolejnych iteracjach możliwe jest dodanie domen Magazynu (zarządzanie składnikami) oraz Kasy (rozliczenia, raportowanie dzienne), bez naruszania istniejącego modelu domenowego.
