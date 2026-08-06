<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Handler;

use Pizzeria\Kitchen\Application\Command\PickUpPizzaFromQueue;
use Pizzeria\Kitchen\Application\Port\ActiveChefPoolInterface;
use Pizzeria\Kitchen\Application\Port\BusyChefsInterface;
use Pizzeria\Kitchen\Application\Port\PizzaTaskRepositoryInterface;
use Pizzeria\Kitchen\Application\Service\TaskSelectionPolicy;
use Pizzeria\Kitchen\Domain\ValueObject\ChefId;

/**
 * doc/08_kitchen_aggregates.md §2, invariant 1: the chef must be Active
 * (Active Chef Pool) and currently free (Busy Chefs) — neither fact is
 * something PizzaTask holds on itself.
 */
final class PickUpPizzaFromQueueHandler
{
    public function __construct(
        private readonly ActiveChefPoolInterface $activeChefPool,
        private readonly BusyChefsInterface $busyChefs,
        private readonly TaskSelectionPolicy $taskSelectionPolicy,
        private readonly PizzaTaskRepositoryInterface $pizzaTasks,
    ) {
    }

    public function __invoke(PickUpPizzaFromQueue $command): void
    {
        $chefId = ChefId::fromString($command->chefId);

        if (!$this->activeChefPool->isActive($chefId)) {
            throw new \DomainException(sprintf('Chef %s is not Active.', $chefId->toString()));
        }

        if (!$this->busyChefs->isFree($chefId)) {
            throw new \DomainException(sprintf('Chef %s is already preparing a pizza.', $chefId->toString()));
        }

        $task = $this->taskSelectionPolicy->pickNext();
        if ($task === null) {
            // Empty Production Queue: nothing to pick up.
            return;
        }

        $task->pickUp($chefId);
        $this->pizzaTasks->save($task);
        $this->busyChefs->markBusy($chefId);
    }
}
