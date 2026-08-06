<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\InMemory;

use Pizzeria\Kitchen\Application\Port\OrderProgressInterface;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Pizzeria\Kitchen\Domain\ValueObject\PizzaTaskId;

/**
 * A SET of completed pizzaTaskIds per kitchenOrderId — adding the same ID
 * twice is a no-op, safe under at-least-once delivery (DN-2,
 * design_notes/dn_0002.md; doc/08_kitchen_read_models.md).
 */
final class InMemoryOrderProgress implements OrderProgressInterface
{
    /** @var array<string, array<string, true>> */
    private array $completed = [];

    public function markCompleted(KitchenOrderId $kitchenOrderId, PizzaTaskId $pizzaTaskId): void
    {
        $this->completed[$kitchenOrderId->toString()][$pizzaTaskId->toString()] = true;
    }

    public function completedCount(KitchenOrderId $kitchenOrderId): int
    {
        return count($this->completed[$kitchenOrderId->toString()] ?? []);
    }
}
