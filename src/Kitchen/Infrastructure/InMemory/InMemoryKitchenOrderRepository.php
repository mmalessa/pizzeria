<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Infrastructure\InMemory;

use Pizzeria\Kitchen\Application\Port\KitchenOrderRepositoryInterface;
use Pizzeria\Kitchen\Domain\Aggregate\KitchenOrder;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;

/**
 * In-memory for this first pass (session decision) — deliberately behind
 * the same interface the Application layer already depends on, so a
 * Doctrine implementation later changes only this class.
 */
final class InMemoryKitchenOrderRepository implements KitchenOrderRepositoryInterface
{
    /** @var array<string, KitchenOrder> */
    private array $kitchenOrders = [];

    public function exists(KitchenOrderId $kitchenOrderId): bool
    {
        return isset($this->kitchenOrders[$kitchenOrderId->toString()]);
    }

    public function save(KitchenOrder $kitchenOrder): void
    {
        $this->kitchenOrders[$kitchenOrder->kitchenOrderId()->toString()] = $kitchenOrder;
    }

    public function get(KitchenOrderId $kitchenOrderId): KitchenOrder
    {
        return $this->kitchenOrders[$kitchenOrderId->toString()]
            ?? throw new \RuntimeException(sprintf('KitchenOrder %s not found.', $kitchenOrderId->toString()));
    }
}
