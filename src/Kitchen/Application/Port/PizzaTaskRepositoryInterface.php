<?php

declare(strict_types=1);

namespace Pizzeria\Kitchen\Application\Port;

use Pizzeria\Kitchen\Domain\Aggregate\PizzaTask;
use Pizzeria\Kitchen\Domain\ValueObject\KitchenOrderId;
use Pizzeria\Kitchen\Domain\ValueObject\PizzaTaskId;

interface PizzaTaskRepositoryInterface
{
    public function save(PizzaTask $pizzaTask): void;

    public function get(PizzaTaskId $pizzaTaskId): PizzaTask;

    /** @return PizzaTask[] */
    public function findByKitchenOrderId(KitchenOrderId $kitchenOrderId): array;

    /**
     * Production Queue read model (doc/08_kitchen_read_models.md): every
     * Pending PizzaTask, oldest first — a direct query over PizzaTask, not
     * a separately maintained projection.
     *
     * @return PizzaTask[]
     */
    public function findPendingOldestFirst(): array;

    /**
     * Verification/demo harness only (UI/Console) — not called from any
     * Application-layer handler.
     *
     * @return PizzaTask[]
     */
    public function findAll(): array;
}
