# Pizzeria

Piaskownica architektoniczna demonstrująca projektowanie oraz implementację systemu zgodnie z zasadami Domain-Driven Design.

## O projekcie

Pizzeria to interaktywna symulacja restauracji serwującej pizze. Użytkownik przez Web GUI może wcielać się w role uczestników procesu obsługi restauracji — gości, kelnera, kucharza, hosta i menedżera — oraz obserwować zachowania systemu z perspektywy całej pizzerii.

Celem projektu jest pokazanie pełnego procesu odkrywania domeny, modelowania strategicznego i taktycznego, a następnie implementacji z wykorzystaniem wzorców takich jak Event Sourcing, Saga, Process Manager, CQRS i CQS.

System jest budowany jako modularny monolit złożony z niezależnych mikrousług, przygotowany na przyszłą migrację do Kubernetes.

## Dokumentacja

Projekt prowadzony jest zgodnie z metodologią DDD. Aktualny postęp prac oraz kolejne etapy znajdują się w dokumencie [doc/README.md](doc/README.md).

Katalog `doc/` zawiera pełną dokumentację z etapu odkrywania domeny — wizję projektu, odkryte procesy biznesowe, decyzje domenowe oraz zidentyfikowane role.
