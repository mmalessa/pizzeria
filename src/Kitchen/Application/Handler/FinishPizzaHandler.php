<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Handler;

use Pizzeria\Kitchen\Application\Command\FinishPizza;
use Pizzeria\Kitchen\Application\Port\BusyChefsInterface;
use Pizzeria\Kitchen\Application\Port\KitchenOrderRepositoryInterface;
use Pizzeria\Kitchen\Application\Port\OrderProgressInterface;
use Pizzeria\Kitchen\Application\Port\OutboxInterface;
use Pizzeria\Kitchen\Application\Port\PizzaTaskRepositoryInterface;
use Pizzeria\Kitchen\Application\Service\OrderReadinessCheck;
use Pizzeria\Kitchen\Domain\ValueObject\PizzaTaskId;

/**
 * doc/08_kitchen_aggregates.md §2, invariant 2: raises PizzaPrepared
 * (internal, not modelled as a class in this pass — see AGENTS notes) and
 * ChefFinishedPizza (external) together. Also drives Order Progress and,
 * once every pizza is Ready, KitchenOrder's own transition to
 * ReadyForPickup (doc/08_kitchen_domain_services.md's OrderReadinessCheck).
 */
final class FinishPizzaHandler
{
    public function __construct(
        private readonly PizzaTaskRepositoryInterface $pizzaTasks,
        private readonly KitchenOrderRepositoryInterface $kitchenOrders,
        private readonly BusyChefsInterface $busyChefs,
        private readonly OrderProgressInterface $orderProgress,
        private readonly OrderReadinessCheck $orderReadinessCheck,
        private readonly OutboxInterface $outbox,
    ) {
    }

    public function __invoke(FinishPizza $command): void
    {
        $task = $this->pizzaTasks->get(PizzaTaskId::fromString($command->pizzaTaskId));
        $chefId = $task->chefId();
        if ($chefId === null) {
            throw new \LogicException(sprintf('PizzaTask %s has no chef assigned.', $command->pizzaTaskId));
        }

        $task->finish();
        $this->pizzaTasks->save($task);

        $this->busyChefs->markFree($chefId);

        // ChefFinishedPizza is published unconditionally on every FinishPizza
        // — Kitchen doesn't check whether the chef is Terminating, Resource
        // Management's Chef decides on its own side (08_kitchen_integration_events.md).
        $this->outbox->publish(
            'pizzeria.kitchen.chef-finished-pizza',
            1,
            ['chefId' => $chefId->toString()],
            $task->correlationId(),
        );

        $this->orderProgress->markCompleted($task->kitchenOrderId(), $task->pizzaTaskId());

        $kitchenOrder = $this->kitchenOrders->get($task->kitchenOrderId());
        if (!$this->orderReadinessCheck->isReady($kitchenOrder)) {
            return;
        }

        $kitchenOrder->markReady();
        $this->kitchenOrders->save($kitchenOrder);

        $this->outbox->publish(
            'pizzeria.kitchen.order-ready-for-pickup',
            1,
            ['orderId' => $kitchenOrder->kitchenOrderId()->toString()],
            $kitchenOrder->correlationId(),
        );
    }
}
