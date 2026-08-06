<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\Consumer;

use Pizzeria\Kitchen\Application\Command\AcceptOrder;
use Pizzeria\Kitchen\Application\Command\FinishPizza;
use Pizzeria\Kitchen\Application\Command\PickUpPizzaFromQueue;
use Pizzeria\Kitchen\Application\EventHandler\ChefPoolReplicationHandler;
use Pizzeria\Kitchen\Application\EventHandler\MenuCatalogReplicationHandler;
use Pizzeria\Kitchen\Application\Handler\AcceptOrderHandler;
use Pizzeria\Kitchen\Application\Handler\FinishPizzaHandler;
use Pizzeria\Kitchen\Application\Handler\PickUpPizzaFromQueueHandler;
use Pizzeria\Shared\SchemaValidatorInterface;
use Psr\Log\LoggerInterface;

/**
 * The single entrypoint every consumed job — whether it arrived over the
 * Kafka pipeline or the RabbitMQ pipeline (doc/09_architecture.md §5
 * "Broker assignment") — is routed through, by eventType alone. This is
 * exactly what this pass exists to prove: one worker-side dispatch path
 * regardless of which broker RoadRunner's jobs plugin pulled the message
 * from (doc/09_architecture.md §3).
 */
final class JobDispatcher
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly AcceptOrderHandler $acceptOrderHandler,
        private readonly PickUpPizzaFromQueueHandler $pickUpPizzaFromQueueHandler,
        private readonly FinishPizzaHandler $finishPizzaHandler,
        private readonly MenuCatalogReplicationHandler $menuCatalogReplicationHandler,
        private readonly ChefPoolReplicationHandler $chefPoolReplicationHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function dispatch(string $rawEnvelope): void
    {
        /** @var array{eventType: string, schemaVersion: int, correlationId: string, payload: array<string, mixed>} $envelope */
        $envelope = json_decode($rawEnvelope, true, flags: JSON_THROW_ON_ERROR);

        $eventType = $envelope['eventType'];
        $schemaVersion = (string) $envelope['schemaVersion'];
        $payload = $envelope['payload'];
        $correlationId = $envelope['correlationId'];

        $this->schemaValidator->validate($eventType, $schemaVersion, $payload);

        match ($eventType) {
            // RabbitMQ pipeline (doc/09_architecture.md §5)
            'pizzeria.guest-service.order-sent-to-kitchen' => ($this->acceptOrderHandler)(
                new AcceptOrder($payload['orderId'], $payload['lines'], $correlationId),
            ),
            // Internal operational commands, NOT integration events crossing
            // a Bounded Context boundary (doc/08_kitchen_integration_events.md
            // lists none of these) — in the real system a Chef's automated
            // behaviour (doc/01_understand.md §2.2) triggers these directly,
            // in-process. Routed through the same RabbitMQ pipeline and
            // therefore the same jobs-worker process as AcceptOrder above,
            // purely so this pass's in-memory repositories stay visible to
            // each other for manual verification (session decision).
            'pizzeria.kitchen.internal.pick-up-pizza-from-queue' => ($this->pickUpPizzaFromQueueHandler)(
                new PickUpPizzaFromQueue($payload['chefId']),
            ),
            'pizzeria.kitchen.internal.finish-pizza' => ($this->finishPizzaHandler)(
                new FinishPizza($payload['pizzaTaskId']),
            ),
            // Kafka pipeline (doc/09_architecture.md §5)
            'pizzeria.resource-management.menu-item-added' => $this->menuCatalogReplicationHandler->onMenuItemAdded($payload),
            'pizzeria.resource-management.menu-item-updated' => $this->menuCatalogReplicationHandler->onMenuItemUpdated($payload),
            'pizzeria.resource-management.menu-item-disabled' => $this->menuCatalogReplicationHandler->onMenuItemDisabled($payload),
            'pizzeria.resource-management.menu-item-enabled' => $this->menuCatalogReplicationHandler->onMenuItemEnabled($payload),
            'pizzeria.resource-management.chef-hired' => $this->chefPoolReplicationHandler->onChefHired($payload),
            'pizzeria.resource-management.chef-termination-started' => $this->chefPoolReplicationHandler->onChefTerminationStarted($payload),
            'pizzeria.resource-management.chef-terminated' => $this->chefPoolReplicationHandler->onChefTerminated($payload),
            'pizzeria.resource-management.chef-rehired' => $this->chefPoolReplicationHandler->onChefRehired($payload),
            default => $this->logger->warning('[jobs] unhandled eventType {eventType}', ['eventType' => $eventType]),
        };
    }
}
