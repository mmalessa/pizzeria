<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Handler;

use Pizzeria\Kitchen\Application\Command\AcceptOrder;
use Pizzeria\Kitchen\Application\Port\ActiveChefPoolInterface;
use Pizzeria\Kitchen\Application\Port\KitchenOrderRepositoryInterface;
use Pizzeria\Kitchen\Application\Port\OutboxInterface;
use Pizzeria\Kitchen\Application\Port\PizzaTaskRepositoryInterface;
use Pizzeria\Kitchen\Application\Service\WaitTimeEstimationPolicy;
use Pizzeria\Kitchen\Domain\Aggregate\KitchenOrder;
use Pizzeria\Kitchen\Domain\Aggregate\PizzaTask;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderLine;
use Pizzeria\Kitchen\Domain\ValueObject\MenuItemId;
use Pizzeria\Kitchen\Domain\ValueObject\PizzaTaskId;
use Psr\Log\LoggerInterface;

/**
 * Triggered by consuming OrderSentToKitchen (doc/08_kitchen_integration_events.md).
 *
 * doc/08_kitchen_aggregates.md §1, invariant 2: idempotent by
 * kitchenOrderId. The existence check below IS that invariant — a
 * redelivered OrderSentToKitchen must no-op here, not create a duplicate
 * KitchenOrder/PizzaTask set.
 */
final class AcceptOrderHandler
{
    public function __construct(
        private readonly KitchenOrderRepositoryInterface $kitchenOrders,
        private readonly PizzaTaskRepositoryInterface $pizzaTasks,
        private readonly ActiveChefPoolInterface $activeChefPool,
        private readonly WaitTimeEstimationPolicy $waitTimeEstimationPolicy,
        private readonly OutboxInterface $outbox,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(AcceptOrder $command): void
    {
        $kitchenOrderId = KitchenOrderId::fromString($command->kitchenOrderId);

        if ($this->kitchenOrders->exists($kitchenOrderId)) {
            // Redelivery of an already-accepted OrderSentToKitchen: no-op
            // (doc/08_kitchen_aggregates.md §1, invariant 2).
            $this->logger->info('[AcceptOrder] ignored duplicate for kitchenOrderId {id}', ['id' => $kitchenOrderId->toString()]);

            return;
        }

        $lines = array_map(
            static fn (array $line): KitchenOrderLine => new KitchenOrderLine(
                MenuItemId::fromString($line['menuItemId']),
                $line['quantity'],
            ),
            $command->lines,
        );

        $kitchenOrder = KitchenOrder::accept($kitchenOrderId, $lines, $command->correlationId);
        $this->kitchenOrders->save($kitchenOrder);

        $pizzasQueuedBefore = count($this->pizzaTasks->findPendingOldestFirst());
        $createdPizzaTaskIds = [];

        foreach ($lines as $line) {
            for ($i = 0; $i < $line->quantity(); $i++) {
                $pizzaTask = PizzaTask::createPending(
                    PizzaTaskId::generate(),
                    $kitchenOrderId,
                    $line->menuItemId(),
                    $command->correlationId,
                );
                $this->pizzaTasks->save($pizzaTask);
                $createdPizzaTaskIds[] = $pizzaTask->pizzaTaskId()->toString();
            }
        }

        $this->logger->info('[AcceptOrder] created pizzaTasks {ids} for kitchenOrderId {orderId}', [
            'ids' => implode(',', $createdPizzaTaskIds),
            'orderId' => $kitchenOrderId->toString(),
        ]);

        $estimatedWaitTime = $this->waitTimeEstimationPolicy->estimate(
            $pizzasQueuedBefore,
            $this->activeChefPool->countActive(),
            $kitchenOrder->totalPizzaCount(),
        );

        $this->outbox->publish(
            'pizzeria.kitchen.order-accepted',
            1,
            [
                'orderId' => $kitchenOrderId->toString(),
                'estimatedWaitTimeSeconds' => $estimatedWaitTime->seconds(),
            ],
            $command->correlationId,
        );
    }
}
