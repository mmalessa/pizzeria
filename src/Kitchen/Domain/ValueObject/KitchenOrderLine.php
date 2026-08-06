<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Domain\ValueObject;

/**
 * One line of a KitchenOrder — { menuItemId, quantity }. No identity,
 * immutable once created (doc/08_kitchen_value_objects.md).
 */
final class KitchenOrderLine
{
    public function __construct(
        private readonly MenuItemId $menuItemId,
        private readonly int $quantity,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('KitchenOrderLine quantity must be at least 1.');
        }
    }

    public function menuItemId(): MenuItemId
    {
        return $this->menuItemId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }
}
