<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\EventHandler;

use Pizzeria\Kitchen\Application\Port\ActiveChefPoolInterface;
use Pizzeria\Kitchen\Domain\ValueObject\ChefId;
use Psr\Log\LoggerInterface;

/**
 * Consumes Resource Management's Chef* events over Kafka into the Active
 * Chef Pool read model (doc/08_kitchen_integration_events.md) — employment
 * status only, not "currently busy" (doc/08_kitchen_domain_model.md §4).
 *
 * A Terminating chef is marked inactive immediately, not only once fully
 * Terminated: PickUpPizzaFromQueue's guard requires status = Active
 * specifically (doc/08_kitchen_aggregates.md §1, invariant 1), and
 * Terminating is a distinct state a Terminating chef should not be offered
 * new work in — the same "stops being offered new work" shape Waiter uses
 * during its own Terminating window (doc/05_connect_message_flows.md
 * Scenario 5).
 */
final class ChefPoolReplicationHandler
{
    public function __construct(
        private readonly ActiveChefPoolInterface $activeChefPool,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param array{chefId: string} $payload */
    public function onChefHired(array $payload): void
    {
        $this->activeChefPool->markActive(ChefId::fromString($payload['chefId']));
        $this->logger->info('[ActiveChefPool] hired/active chefId {id}', ['id' => $payload['chefId']]);
    }

    /** @param array{chefId: string} $payload */
    public function onChefRehired(array $payload): void
    {
        $this->activeChefPool->markActive(ChefId::fromString($payload['chefId']));
        $this->logger->info('[ActiveChefPool] rehired/active chefId {id}', ['id' => $payload['chefId']]);
    }

    /** @param array{chefId: string} $payload */
    public function onChefTerminationStarted(array $payload): void
    {
        $this->activeChefPool->markInactive(ChefId::fromString($payload['chefId']));
        $this->logger->info('[ActiveChefPool] termination started/inactive chefId {id}', ['id' => $payload['chefId']]);
    }

    /** @param array{chefId: string} $payload */
    public function onChefTerminated(array $payload): void
    {
        $this->activeChefPool->markInactive(ChefId::fromString($payload['chefId']));
        $this->logger->info('[ActiveChefPool] terminated/inactive chefId {id}', ['id' => $payload['chefId']]);
    }
}
