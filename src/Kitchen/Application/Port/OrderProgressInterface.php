<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Port;

use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Pizzeria\Kitchen\Domain\ValueObject\PizzaTaskId;

/**
 * Order Progress read model (doc/08_kitchen_read_models.md) — per
 * kitchenOrderId, the SET of pizzaTaskIds that reached Ready. A set, not a
 * counter, so a redelivered completion is a no-op under at-least-once
 * delivery (DN-2, design_notes/dn_0002.md).
 */
interface OrderProgressInterface
{
    public function markCompleted(KitchenOrderId $kitchenOrderId, PizzaTaskId $pizzaTaskId): void;

    public function completedCount(KitchenOrderId $kitchenOrderId): int;
}
