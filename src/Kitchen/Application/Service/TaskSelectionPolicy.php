<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Service;

use Pizzeria\Kitchen\Application\Port\PizzaTaskRepositoryInterface;
use Pizzeria\Kitchen\Domain\Aggregate\PizzaTask;

/**
 * doc/08_kitchen_aggregates.md §2, invariant 3 — strictly FIFO: the oldest
 * Pending PizzaTask is always next.
 */
final class TaskSelectionPolicy
{
    public function __construct(private readonly PizzaTaskRepositoryInterface $pizzaTasks)
    {
    }

    public function pickNext(): ?PizzaTask
    {
        $pending = $this->pizzaTasks->findPendingOldestFirst();

        return $pending[0] ?? null;
    }
}
